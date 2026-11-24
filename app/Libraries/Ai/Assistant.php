<?php

namespace App\Libraries\Ai;

use App\Libraries\Quizzes;

/**
 * Funciones de IA de la plataforma (documento maestro 5.1). Úsela con service('assistant').
 *
 * - tutor(): tutor del estudiante en un reto, con el modo que fijó el docente y memoria acotada de la conversación.
 * - questions(): copiloto docente que propone preguntas (salida estructurada); el docente las revisa antes de guardar.
 * - copilot(): asistente del cuaderno; conversa con el docente sobre la página abierta (borradores, no publica).
 * - study(): tutor del cuaderno; explica al estudiante una página publicada de su grupo.
 * - feedback(): propone al docente la retroalimentación de una entrega según los niveles de rúbrica elegidos.
 * Cada función arma su contexto solo con datos autorizados (Context Builder) y llama al Gateway.
 */
class Assistant
{
    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    /** Nombre del curso (nivel) de un grupo, p. ej. «7.º». */
    private function levelOfGroup(int $groupId): string
    {
        return (string) ($this->db()->table('class_groups g')->select('l.name')->join('levels l', 'l.id = g.level_id')
            ->where('g.institution_id', $this->iid())->where('g.id', $groupId)->get()->getRow()->name ?? 'educación básica');
    }

    /* ---------- Tutor del estudiante ---------- */

    private function ctxKey(int $assignmentId): string
    {
        return 'assignment:' . $assignmentId;
    }

    /** Conversación guardada del estudiante en una asignación. */
    public function thread(int $assignmentId, int $userId): array
    {
        return $this->db()->table('ai_messages')->select('role, content, simulated, created_at')
            ->where('institution_id', $this->iid())->where('user_id', $userId)->where('context', $this->ctxKey($assignmentId))
            ->orderBy('id')->get()->getResultArray();
    }

    /** Responde una pregunta del estudiante. Devuelve [reply, simulated] o [error]. */
    public function tutor(int $assignmentId, string $message): array
    {
        $uid = (int) service('auth')->id();
        $v   = service('activities')->studentView($assignmentId, $uid);
        if (! $v || $v['activity']['kind'] !== 'challenge') {
            return ['error' => 'Ese reto no está disponible para ti.'];
        }
        $a    = $v['activity'];
        $mode = $a['ai_mode'];
        if (! in_array($mode, ['guide', 'explain'], true)) {
            return ['error' => 'Tu docente desactivó el tutor en este reto.'];
        }
        $message = trim($message);
        if ($message === '') {
            return ['error' => 'Escribe tu pregunta.'];
        }

        $steps = implode("\n", array_map(static fn ($i, $s) => ($i + 1) . '. ' . ($s['title'] ?? '') . ': ' . ($s['text'] ?? ''), array_keys($a['steps']), $a['steps'])) ?: '(sin pasos)';
        $system = Prompts::tutor($mode, [
            'level' => $this->levelOfGroup((int) $a['offering']['group_id']), 'subject' => $a['offering']['subject'], 'title' => $a['title'],
            'objective' => (string) $a['objective'], 'instructions' => (string) $a['instructions'], 'steps' => $steps, 'deliverable' => (string) $a['deliverable'],
        ]);

        // Memoria acotada: los últimos turnos de esta asignación.
        $history  = array_slice($this->thread($assignmentId, $uid), -2 * config('Ai')->features['tutor']['history']);
        $messages = array_map(static fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $history);
        $messages[] = ['role' => 'user', 'content' => $message];

        $r = service('ai')->run('tutor', $system, $messages, ['prompt_version' => Prompts::TUTOR_VERSION]);
        if (isset($r['error'])) {
            return $r;
        }
        $now = date('Y-m-d H:i:s');
        foreach ([['user', $message, 0], ['assistant', $r['text'], $r['simulated'] ? 1 : 0]] as [$role, $content, $sim]) {
            $this->db()->table('ai_messages')->insert(['institution_id' => $this->iid(), 'user_id' => $uid, 'context' => $this->ctxKey($assignmentId),
                'role' => $role, 'content' => $content, 'simulated' => $sim, 'created_at' => $now, 'updated_at' => $now]);
        }

        return ['reply' => $r['text'], 'simulated' => $r['simulated']];
    }

    /* ---------- Tutor del cuaderno (estudiante) ---------- */

    /** Página publicada que el estudiante puede leer, con su oferta (o null). */
    private function readablePage(int $pageId): ?array
    {
        $p = $this->db()->table('pages p')->select('p.id, p.workspace_id, w.offering_id, s.name AS subject, o.group_id')
            ->join('workspaces w', 'w.id = p.workspace_id')->join('subject_offerings o', 'o.id = w.offering_id')->join('subjects s', 's.id = o.subject_id')
            ->where('p.institution_id', $this->iid())->where('p.id', $pageId)->where('p.deleted_at', null)->get()->getRowArray();
        if (! $p || ! service('content')->canReadOffering((int) $p['offering_id'])) {
            return null;
        }
        $page = service('content')->publishedPage($pageId, (int) $p['workspace_id']);

        return $page ? $p + ['page' => $page] : null;
    }

    /** Texto plano de los bloques de una página (para el contexto). */
    private static function blocksText(array $blocks, int $max = 6000): string
    {
        return mb_substr(implode("\n", array_filter(array_map(static fn ($b) => trim((string) ($b['data']['text'] ?? $b['data']['caption'] ?? '')), $blocks))), 0, $max);
    }

    public function studyThread(int $pageId, int $userId): array
    {
        return $this->db()->table('ai_messages')->select('role, content, simulated, created_at')
            ->where('institution_id', $this->iid())->where('user_id', $userId)->where('context', 'study:' . $pageId)
            ->orderBy('id')->get()->getResultArray();
    }

    /** Responde al estudiante sobre una página publicada. Devuelve [reply, simulated] o [error]. */
    public function study(int $pageId, string $message): array
    {
        $uid = (int) service('auth')->id();
        $p   = $this->readablePage($pageId);
        if (! $p) {
            return ['error' => 'Esa página no está disponible para ti.'];
        }
        $message = trim($message);
        if ($message === '') {
            return ['error' => 'Escribe tu pregunta.'];
        }
        $page    = $p['page'];
        $content = self::blocksText((array) $page['blocks']);
        $system  = Prompts::study([
            'subject' => $p['subject'], 'level' => $this->levelOfGroup((int) $p['group_id']), 'title' => $page['title_published'] ?? $page['title'],
            'objective' => (string) ($page['objective'] ?? ''), 'content' => $content !== '' ? $content : '(la página no tiene texto)',
        ]);
        $history  = array_slice($this->studyThread($pageId, $uid), -2 * config('Ai')->features['study']['history']);
        $messages = array_map(static fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $history);
        $messages[] = ['role' => 'user', 'content' => $message];

        $r = service('ai')->run('study', $system, $messages, ['prompt_version' => Prompts::STUDY_VERSION]);
        if (isset($r['error'])) {
            return $r;
        }
        $now = date('Y-m-d H:i:s');
        foreach ([['user', $message, 0], ['assistant', $r['text'], $r['simulated'] ? 1 : 0]] as [$role, $text, $sim]) {
            $this->db()->table('ai_messages')->insert(['institution_id' => $this->iid(), 'user_id' => $uid, 'context' => 'study:' . $pageId,
                'role' => $role, 'content' => $text, 'simulated' => $sim, 'created_at' => $now, 'updated_at' => $now]);
        }

        return ['reply' => $r['text'], 'simulated' => $r['simulated']];
    }

    /* ---------- Retroalimentación de una entrega (docente) ---------- */

    /**
     * Propone la retroalimentación de una entrega con los niveles de rúbrica que eligió el docente (aún sin guardar).
     * No envía el nombre del estudiante. Devuelve [reply, simulated] o [error].
     */
    public function feedback(int $assignmentId, int $submissionId, array $levels): array
    {
        $x   = model(\App\Models\ActivityAssignmentModel::class)->find($assignmentId);
        $act = $x ? service('activities')->get((int) $x['activity_id']) : null;
        if (! service('activities')->canManage($act)) {
            return ['error' => 'No puedes revisar esa actividad.'];
        }
        $sub = model(\App\Models\SubmissionModel::class)->where('assignment_id', $assignmentId)->find($submissionId);
        if (! $sub || ! in_array($sub['status'], ['submitted', 'reviewed'], true)) {
            return ['error' => 'Esa entrega no existe o aún no se ha entregado.'];
        }
        $names  = \App\Libraries\Activities::LEVELS;
        $rubric = [];
        foreach ($act['rubric']['criteria'] as $i => $c) {
            $l        = max(0, min(count($names) - 1, (int) ($levels[$i] ?? count($names) - 1)));
            $rubric[] = '- ' . $c['name'] . ' (' . (int) $c['weight'] . ' %): nivel ' . $names[$l] . (! empty($c['desc'][$l]) ? ' — ' . $c['desc'][$l] : '');
        }
        $answer = trim((string) $sub['answer']);
        $system = Prompts::feedback([
            'subject' => $act['offering']['subject'] ?? '', 'level' => $this->levelOfGroup((int) ($act['offering']['group_id'] ?? 0)),
            'title' => $act['title'], 'instructions' => mb_substr((string) $act['instructions'], 0, 3000), 'deliverable' => (string) $act['deliverable'],
            'rubric' => implode("\n", $rubric) ?: '(sin rúbrica)', 'answer' => $answer !== '' ? mb_substr($answer, 0, 5000) : '(el estudiante no escribió texto)',
        ]);
        $r = service('ai')->run('feedback', $system, [['role' => 'user', 'content' => 'Propón la retroalimentación para esta entrega.']], ['prompt_version' => Prompts::FEEDBACK_VERSION]);

        return isset($r['error']) ? $r : ['reply' => trim($r['text']), 'simulated' => $r['simulated']];
    }

    /* ---------- Asistente del cuaderno ---------- */

    /** Página del cuaderno que el docente puede editar, con su oferta (o null). */
    private function editablePage(int $pageId): ?array
    {
        $p = $this->db()->table('pages p')->select('p.id, p.workspace_id, w.offering_id, s.name AS subject, o.group_id')
            ->join('workspaces w', 'w.id = p.workspace_id')->join('subject_offerings o', 'o.id = w.offering_id')->join('subjects s', 's.id = o.subject_id')
            ->where('p.institution_id', $this->iid())->where('p.id', $pageId)->where('p.deleted_at', null)->get()->getRowArray();

        return $p && service('content')->canEditOffering((int) $p['offering_id']) ? $p : null;
    }

    /** Conversación del docente con el asistente sobre una página. */
    public function copilotThread(int $pageId, int $userId): array
    {
        return $this->db()->table('ai_messages')->select('role, content, simulated, created_at')
            ->where('institution_id', $this->iid())->where('user_id', $userId)->where('context', 'page:' . $pageId)
            ->orderBy('id')->get()->getResultArray();
    }

    /** Responde al docente sobre la página (lo guardado). Devuelve [reply, simulated] o [error]. */
    public function copilot(int $pageId, string $message): array
    {
        $uid = (int) service('auth')->id();
        $p   = $this->editablePage($pageId);
        if (! $p) {
            return ['error' => 'Esa página no existe o no puedes editarla.'];
        }
        $message = trim($message);
        if ($message === '') {
            return ['error' => 'Escribe tu pedido.'];
        }
        $page    = service('content')->page($pageId, (int) $p['workspace_id']);
        $content = self::blocksText((array) $page['blocks']);
        $system  = Prompts::copilot([
            'subject' => $p['subject'], 'level' => $this->levelOfGroup((int) $p['group_id']), 'title' => $page['title'],
            'objective' => (string) $page['objective'], 'content' => $content !== '' ? $content : '(la página aún está vacía)',
        ]);
        $history  = array_slice($this->copilotThread($pageId, $uid), -2 * config('Ai')->features['copilot']['history']);
        $messages = array_map(static fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $history);
        $messages[] = ['role' => 'user', 'content' => $message];

        $r = service('ai')->run('copilot', $system, $messages, ['prompt_version' => Prompts::COPILOT_VERSION]);
        if (isset($r['error'])) {
            return $r;
        }
        $now = date('Y-m-d H:i:s');
        foreach ([['user', $message, 0], ['assistant', $r['text'], $r['simulated'] ? 1 : 0]] as [$role, $text, $sim]) {
            $this->db()->table('ai_messages')->insert(['institution_id' => $this->iid(), 'user_id' => $uid, 'context' => 'page:' . $pageId,
                'role' => $role, 'content' => $text, 'simulated' => $sim, 'created_at' => $now, 'updated_at' => $now]);
        }

        return ['reply' => $r['text'], 'simulated' => $r['simulated']];
    }

    /* ---------- Copiloto docente: preguntas ---------- */

    /**
     * Propone preguntas. $in: topic, count (1-10), types[], difficulty, notes, offering_id o subject_id (contexto).
     * Devuelve [questions (formato del editor), simulated, discarded] o [error].
     */
    public function questions(array $in): array
    {
        if (! service('tenant')->can('ai.teacher.use')) {
            return ['error' => 'Su rol no puede usar el copiloto docente.'];
        }
        $topic = trim((string) ($in['topic'] ?? ''));
        $count = (int) ($in['count'] ?? 5);
        $types = array_values(array_intersect((array) ($in['types'] ?? []), array_keys(Quizzes::TYPES))) ?: ['single'];
        $diff  = isset(Quizzes::DIFFICULTIES[$in['difficulty'] ?? '']) ? $in['difficulty'] : '';
        if ($topic === '') {
            return ['error' => 'Escriba el tema de las preguntas.'];
        }
        if ($count < 1 || $count > 10) {
            return ['error' => 'Pida entre 1 y 10 preguntas.'];
        }

        // Contexto autorizado: asignatura y curso de una oferta que puede editar, o una asignatura de la institución.
        $subject = 'la asignatura';
        $level   = 'educación básica';
        if (($oid = (int) ($in['offering_id'] ?? 0)) && service('content')->canEditOffering($oid)) {
            $o = $this->db()->table('subject_offerings o')->select('s.name AS subject, o.group_id')->join('subjects s', 's.id = o.subject_id')
                ->where('o.institution_id', $this->iid())->where('o.id', $oid)->get()->getRowArray();
            $subject = $o['subject'] ?? $subject;
            $level   = $o ? $this->levelOfGroup((int) $o['group_id']) : $level;
        } elseif ($sid = (int) ($in['subject_id'] ?? 0)) {
            $subject = (string) ($this->db()->table('subjects')->select('name')->where('institution_id', $this->iid())->where('id', $sid)->get()->getRow()->name ?? $subject);
        }

        $names   = implode(', ', array_map(static fn ($t) => $t . ' (' . Quizzes::TYPES[$t] . ')', $types));
        $request = "<pedido>\nTema: {$topic}\nCantidad: {$count}\nTipos permitidos: {$names}\n"
            . ($diff ? 'Dificultad: ' . $diff . "\n" : "Dificultad: mezcla de easy, medium y hard\n")
            . (trim((string) ($in['notes'] ?? '')) !== '' ? 'Indicaciones del docente: ' . trim((string) $in['notes']) . "\n" : '') . '</pedido>';

        $r = service('ai')->run('questions', Prompts::questions(['subject' => $subject, 'level' => $level]), [['role' => 'user', 'content' => $request]], [
            'schema' => Prompts::questionsSchema(), 'prompt_version' => Prompts::QUESTIONS_VERSION,
            'meta'   => ['topic' => $topic, 'count' => $count, 'types' => $types, 'difficulty' => $diff],
        ]);
        if (isset($r['error'])) {
            return $r;
        }
        $data = json_decode($r['text'], true);
        if (! is_array($data['questions'] ?? null)) {
            log_message('error', 'IA: respuesta de preguntas no es JSON válido.');

            return ['error' => 'La IA devolvió una respuesta que no se pudo leer. Intente de nuevo.'];
        }

        // Al formato del editor y validación con las mismas reglas que una evaluación; se descartan las inválidas.
        $out = [];
        foreach ($data['questions'] as $q) {
            $type = (string) ($q['type'] ?? '');
            if (! in_array($type, $types, true)) {
                continue;
            }
            $ed = ['type' => $type, 'text' => (string) ($q['text'] ?? ''), 'points' => (float) ($q['points'] ?? 1), 'topic' => (string) ($q['topic'] ?? $topic),
                'difficulty' => (string) ($q['difficulty'] ?? 'medium'), 'options' => array_values((array) ($q['options'] ?? [])),
                'correct' => match ($type) {
                    'numeric' => ['value' => $q['numeric_value'] ?? '', 'tolerance' => $q['tolerance'] ?? 0],
                    'short'   => array_values((array) ($q['accepted_answers'] ?? [])),
                    'long'    => [],
                    default   => array_values(array_map('intval', (array) ($q['correct_indexes'] ?? []))),
                }];
            $clean = Quizzes::cleanQuestions(json_encode([$ed]));
            if (! isset($clean['error']) && $clean) {
                $c     = $clean[0];
                $out[] = ['type' => $c['type'], 'text' => $c['text'], 'options' => $c['options'], 'correct' => $c['correct'], 'points' => $c['points'],
                    'topic' => (string) $c['topic'], 'difficulty' => $c['difficulty'], 'ai' => true];
            }
        }
        if (! $out) {
            return ['error' => 'La IA no produjo preguntas válidas. Ajuste el tema o los tipos e intente de nuevo.'];
        }

        return ['questions' => $out, 'simulated' => $r['simulated'], 'discarded' => count($data['questions']) - count($out)];
    }
}
