<?php

namespace App\Libraries;

use App\Models\ActivityAssignmentModel;
use App\Models\ActivityModel;
use App\Models\SubjectOfferingModel;
use App\Models\SubmissionModel;

/**
 * Retos, asignaciones y entregas (documento maestro 4.4 y 4.7). Úsela con service('activities').
 *
 * Reglas:
 *  - Gestiona un reto quien puede editar el cuaderno de su oferta (service('content')->canEditOffering).
 *  - Publicar asigna el reto al grupo de la oferta con fechas. El estudiante solo lo ve publicado, asignado a su
 *    grupo y desde la fecha de inicio. Entrega una vez; si el docente se lo devuelve, puede volver a entregar.
 *  - La nota sale de la rúbrica: cada criterio pesa su porcentaje y su nivel (Superior … Bajo) da 1,0 a 5,0.
 */
class Activities
{
    public const LEVELS = ['Superior', 'Alto', 'Básico', 'Bajo'];

    private function uid(): int
    {
        return (int) service('auth')->id();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function defaultRubric(): array
    {
        return ['criteria' => [
            ['name' => 'Comprensión del problema', 'weight' => 25, 'desc' => ['Identifica todos los datos y lo que se pide', 'Identifica casi todos los datos', 'Identifica algunos datos', 'No identifica los datos clave']],
            ['name' => 'Procedimiento', 'weight' => 35, 'desc' => ['Procedimiento correcto y completo', 'Correcto con errores menores', 'Con errores frecuentes', 'Sin procedimiento']],
            ['name' => 'Resultado', 'weight' => 20, 'desc' => ['Resultado correcto', 'Un error en el resultado', 'Varios errores', 'Resultado sin relación']],
            ['name' => 'Explicación', 'weight' => 20, 'desc' => ['Clara, completa y verificada', 'Clara pero incompleta', 'Confusa', 'No explica']],
        ]];
    }

    /** Estado de una asignación según sus fechas: scheduled | open | closed. */
    public static function assignmentState(?array $a): ?string
    {
        if (! $a) {
            return null;
        }
        $now = date('Y-m-d H:i:s');

        return $now < $a['starts_at'] ? 'scheduled' : ($now > $a['due_at'] ? 'closed' : 'open');
    }

    /** Actividad con JSON decodificado, su oferta y su asignación; null si no es de esta institución. */
    public function get(int $id): ?array
    {
        $a = model(ActivityModel::class)->find($id);
        if (! $a) {
            return null;
        }
        foreach (['steps' => [], 'hints' => [], 'rubric' => self::defaultRubric()] as $k => $def) {
            $a[$k] = json_decode((string) $a[$k], true) ?: $def;
        }
        $a['offering']   = db_connect()->table('subject_offerings o')->select('o.id, o.group_id, s.name AS subject, g.name AS group_name')
            ->join('subjects s', 's.id = o.subject_id')->join('class_groups g', 'g.id = o.group_id')
            ->where('o.institution_id', $this->iid())->where('o.id', $a['offering_id'])->get()->getRowArray();
        $a['assignment'] = model(ActivityAssignmentModel::class)->where('activity_id', $id)->first();

        return $a;
    }

    /**
     * Actividades de una oferta para los bloques «Reto» y «Evaluación» del cuaderno (editor del docente):
     * id, kind (challenge | quiz), título, estado y fechas de la asignación. Solo de la institución activa.
     */
    public function forOffering(int $offeringId): array
    {
        return db_connect()->table('activities a')
            ->select('a.id, a.kind, a.title, a.status, x.starts_at, x.due_at')
            ->join('activity_assignments x', 'x.activity_id = a.id', 'left')
            ->where('a.institution_id', $this->iid())->where('a.offering_id', $offeringId)->where('a.deleted_at', null)
            ->orderBy('a.title')->get()->getResultArray();
    }

    public function canManage(?array $activity): bool
    {
        return $activity !== null && service('content')->canEditOffering((int) $activity['offering_id']);
    }

    /* ---------- Docente ---------- */

    /** Retos de las ofertas visibles (dicta o coordina) con su asignación y conteo de entregas. */
    public function listForTeacher(): array
    {
        $items = service('academic')->myOfferings();
        if (! $items) {
            return [];
        }
        $byId = array_column($items, null, 'id');
        $rows = db_connect()->table('activities a')
            // Retos: cuentan las entregas. Evaluaciones: estudiantes con algún intento terminado, e intentos por revisar.
            ->select("a.*, x.id AS assignment_id, x.starts_at, x.due_at,
                CASE WHEN a.kind = 'quiz'
                    THEN (SELECT COUNT(DISTINCT t.user_id) FROM attempts t WHERE t.assignment_id = x.id AND t.status <> 'in_progress')
                    ELSE (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = x.id AND s.status IN ('submitted','reviewed','returned')) END AS submitted,
                CASE WHEN a.kind = 'quiz'
                    THEN (SELECT COUNT(*) FROM attempts t WHERE t.assignment_id = x.id AND t.status = 'pending_review')
                    ELSE (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = x.id AND s.status = 'submitted') END AS to_review", false)
            ->join('activity_assignments x', 'x.activity_id = a.id', 'left')
            ->where('a.institution_id', $this->iid())->where('a.deleted_at', null)
            ->whereIn('a.offering_id', array_keys($byId))
            ->orderBy('a.updated_at', 'DESC')->get()->getResultArray();
        foreach ($rows as &$r) {
            $o            = $byId[$r['offering_id']];
            $r['label']   = $o['subject'] . ' · ' . $o['group'];
            $r['color']   = $o['color'];
            $r['students'] = $o['students'];
            $r['state']   = self::assignmentState($r['assignment_id'] ? $r : null);
        }

        return $rows;
    }

    /** Crea un reto en borrador. Devuelve [id] o [error]. */
    public function create(int $offeringId, string $title): array
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 160) {
            return ['error' => 'Escriba un título de hasta 160 caracteres.'];
        }
        if (! service('content')->canEditOffering($offeringId)) {
            return ['error' => 'Solo puede crear retos en las asignaturas que dicta.'];
        }
        $id = (int) model(ActivityModel::class)->insert([
            'offering_id' => $offeringId, 'kind' => 'challenge', 'title' => $title, 'status' => 'draft',
            'steps' => json_encode([]), 'hints' => json_encode([]), 'rubric' => json_encode(self::defaultRubric(), JSON_UNESCAPED_UNICODE),
            'created_by' => $this->uid(), 'updated_by' => $this->uid(),
        ]);

        return ['id' => $id];
    }

    /** Guarda el contenido y la configuración. Devuelve null o [campo => mensaje]. */
    public function save(int $id, array $in): ?array
    {
        $a = $this->get($id);
        if (! $this->canManage($a)) {
            return ['_' => 'Ese reto no existe o no puede editarlo.'];
        }
        $v = service('validation');
        $v->reset();
        $data = [
            'title' => trim((string) ($in['title'] ?? '')), 'objective' => trim((string) ($in['objective'] ?? '')),
            'instructions' => trim((string) ($in['instructions'] ?? '')), 'deliverable' => trim((string) ($in['deliverable'] ?? '')),
            'difficulty' => (string) ($in['difficulty'] ?? 'medium'), 'mode' => (string) ($in['mode'] ?? 'individual'),
            'ai_mode' => (string) ($in['ai_mode'] ?? 'guide'), 'points' => (int) ($in['points'] ?? 100), 'retries' => (int) ($in['retries'] ?? 1),
        ];
        if (! $v->setRules([
            'title' => 'required|max_length[160]', 'objective' => 'permit_empty|max_length[255]', 'instructions' => 'permit_empty|max_length[8000]',
            'deliverable' => 'permit_empty|max_length[2000]', 'difficulty' => 'in_list[low,medium,high]', 'mode' => 'in_list[individual,pairs,groups]',
            'ai_mode' => 'in_list[off,guide,explain]', 'points' => 'is_natural|less_than_equal_to[1000]', 'retries' => 'is_natural|less_than_equal_to[10]',
        ])->run($data)) {
            return $v->getErrors();
        }

        $steps = [];
        foreach ((array) json_decode((string) ($in['steps_json'] ?? '[]'), true) as $s) {
            $t = mb_substr(trim((string) ($s['title'] ?? '')), 0, 120);
            if ($t !== '') {
                $steps[] = ['title' => $t, 'text' => mb_substr(trim((string) ($s['text'] ?? '')), 0, 1000)];
            }
        }
        $hints = [];
        foreach ((array) json_decode((string) ($in['hints_json'] ?? '[]'), true) as $h) {
            $t = mb_substr(trim((string) ($h['text'] ?? '')), 0, 500);
            if ($t !== '') {
                $hints[] = ['text' => $t];
            }
        }
        $criteria = [];
        foreach ((array) (json_decode((string) ($in['rubric_json'] ?? '[]'), true)['criteria'] ?? []) as $c) {
            $name = mb_substr(trim((string) ($c['name'] ?? '')), 0, 120);
            if ($name === '') {
                continue;
            }
            $desc = array_map(static fn ($d) => mb_substr(trim((string) $d), 0, 300), array_slice(array_pad((array) ($c['desc'] ?? []), 4, ''), 0, 4));
            $criteria[] = ['name' => $name, 'weight' => max(0, min(100, (int) ($c['weight'] ?? 0))), 'desc' => $desc];
        }
        if ($criteria && array_sum(array_column($criteria, 'weight')) !== 100) {
            return ['rubric' => 'Los pesos de la rúbrica deben sumar 100 %.'];
        }

        model(ActivityModel::class)->update($id, $data + [
            'objective' => $data['objective'] ?: null, 'steps' => json_encode($steps, JSON_UNESCAPED_UNICODE),
            'hints' => json_encode($hints, JSON_UNESCAPED_UNICODE), 'rubric' => json_encode(['criteria' => $criteria ?: self::defaultRubric()['criteria']], JSON_UNESCAPED_UNICODE),
            'updated_by' => $this->uid(),
        ]);

        return null;
    }

    /** Publica y asigna al grupo de la oferta con fechas (o actualiza las fechas). */
    public function publish(int $id, string $startsAt, string $dueAt, bool $allowLate): ?array
    {
        $a = $this->get($id);
        if (! $this->canManage($a) || ! $a['offering']) {
            return ['_' => 'Ese reto no existe o no puede editarlo.'];
        }
        $s = strtotime($startsAt);
        $d = strtotime($dueAt);
        if (! $s || ! $d) {
            return ['due_at' => 'Indique las fechas de inicio y de entrega.'];
        }
        if ($d <= $s) {
            return ['due_at' => 'La fecha de entrega debe ser posterior al inicio.'];
        }
        if ($a['kind'] === 'quiz') {
            if (! model(\App\Models\QuestionModel::class)->where('activity_id', $id)->countAllResults()) {
                return ['questions' => 'Antes de publicar, agregue al menos una pregunta.'];
            }
        } elseif (! $a['steps'] && trim((string) $a['instructions']) === '') {
            return ['instructions' => 'Antes de publicar, escriba las instrucciones o al menos un paso.'];
        }
        $data = ['starts_at' => date('Y-m-d H:i:s', $s), 'due_at' => date('Y-m-d H:i:s', $d), 'allow_late' => $allowLate ? 1 : 0];
        $db   = db_connect();
        $db->transStart();
        if ($a['assignment']) {
            model(ActivityAssignmentModel::class)->update($a['assignment']['id'], $data);
        } else {
            model(ActivityAssignmentModel::class)->insert($data + ['activity_id' => $id, 'group_id' => $a['offering']['group_id'], 'created_by' => $this->uid()]);
        }
        model(ActivityModel::class)->update($id, ['status' => 'published', 'updated_by' => $this->uid()]);
        $db->transComplete();
        if (! $db->transStatus()) {
            return ['_' => 'No se pudo publicar. Intente de nuevo.'];
        }
        // Aviso a estudiantes y acudientes (una sola vez); si abre más adelante, lo envía el barrido de Notifier.
        if ($s <= time()) {
            service('notify')->activityPublished($id);
        }

        return null;
    }

    /** Asignaciones de los retos visibles, para la pantalla de entregas. */
    public function assignmentsForReview(): array
    {
        return array_values(array_filter($this->listForTeacher(), static fn ($r) => $r['assignment_id'] !== null));
    }

    /** Estudiantes del grupo de una asignación, cada uno con su entrega (o sin entregar). */
    public function reviewList(int $assignmentId): array
    {
        $x = model(ActivityAssignmentModel::class)->find($assignmentId);
        if (! $x) {
            return [];
        }

        return db_connect()->table('class_group_members m')
            ->select('u.id AS user_id, u.name, s.id, s.status, s.submitted_at, s.is_late, s.grade, s.attempt')
            ->join('users u', 'u.id = m.user_id')
            ->join('submissions s', 's.user_id = m.user_id AND s.assignment_id = ' . (int) $assignmentId, 'left')
            ->where('m.institution_id', $this->iid())->where('m.group_id', $x['group_id'])
            ->orderBy("CASE WHEN s.status = 'submitted' THEN 0 WHEN s.status IS NULL THEN 2 ELSE 1 END", '', false)
            ->orderBy('u.name')->get()->getResultArray();
    }

    /** Califica o devuelve una entrega. $action: grade | return. */
    public function grade(int $assignmentId, int $submissionId, array $levels, string $feedback, string $action): ?string
    {
        $x   = model(ActivityAssignmentModel::class)->find($assignmentId);
        $act = $x ? $this->get((int) $x['activity_id']) : null;
        if (! $this->canManage($act)) {
            return 'No puede revisar esa actividad.';
        }
        $sub = model(SubmissionModel::class)->where('assignment_id', $assignmentId)->find($submissionId);
        if (! $sub || ! in_array($sub['status'], ['submitted', 'reviewed'], true)) {
            return 'Esa entrega no existe o aún no se ha entregado.';
        }
        $criteria = $act['rubric']['criteria'];
        $max      = count(self::LEVELS) - 1;
        // Cada nivel se toma por el índice de su criterio (levels[i]), no por el orden en que llega; sin dato = Bajo.
        $levels   = array_map(static fn ($i) => max(0, min($max, (int) ($levels[$i] ?? $max))), array_keys($criteria));
        $score    = 0.0;
        foreach ($criteria as $i => $c) {
            $score += $c['weight'] * ($max - max(0, min($max, $levels[$i]))) / $max;
        }
        $grade = round(1 + $score * 4 / 100, 1);
        model(SubmissionModel::class)->update($submissionId, [
            'levels' => json_encode($levels), 'grade' => $action === 'return' ? null : $grade, 'feedback' => mb_substr(trim($feedback), 0, 4000) ?: null,
            'status' => $action === 'return' ? 'returned' : 'reviewed', 'reviewed_by' => $this->uid(), 'reviewed_at' => $this->now(),
        ]);
        service('notify')->graded($assignmentId, (int) $sub['user_id'], $action === 'return' ? null : $grade, $action === 'return');

        return null;
    }

    /* ---------- Estudiante ---------- */

    /**
     * Retos visibles para el estudiante (publicados, de su grupo, ya iniciados) con el estado de su entrega.
     * Con $previewGroup (vista de estudiante): las del grupo, sin entregas ni notas de nadie.
     */
    public function forStudent(int $userId, ?int $previewGroup = null): array
    {
        $preview = $previewGroup !== null;
        $q       = db_connect()->table('activity_assignments x')
            ->select('x.id AS assignment_id, x.starts_at, x.due_at, x.allow_late, a.id AS activity_id, a.title, a.kind, sj.name AS subject, '
                . ($preview ? 'NULL AS status, NULL AS grade, NULL AS feedback, NULL AS reviewed_at' : 'sb.status, sb.grade, sb.feedback, sb.reviewed_at'), false)
            ->join('activities a', 'a.id = x.activity_id')
            ->join('subject_offerings o', 'o.id = a.offering_id')->join('subjects sj', 'sj.id = o.subject_id');
        if ($preview) {
            $q->where('x.group_id', $previewGroup);
        } else {
            $q->join('class_group_members m', 'm.group_id = x.group_id AND m.user_id = ' . $userId)
                ->join('submissions sb', 'sb.assignment_id = x.id AND sb.user_id = ' . $userId, 'left');
        }
        $rows = $q->where('x.institution_id', $this->iid())->where('a.status', 'published')->where('a.deleted_at', null)
            ->where('x.starts_at <=', $this->now())
            ->orderBy('x.due_at')->get()->getResultArray();
        if ($preview) {
            return $rows;
        }

        // En las evaluaciones el estado sale de los intentos: calificado (mejor nota), por revisar, en curso o pendiente.
        foreach ($rows as &$r) {
            if ($r['kind'] !== 'quiz') {
                continue;
            }
            $att = model(\App\Models\AttemptModel::class)->where(['assignment_id' => $r['assignment_id'], 'user_id' => $userId])->findAll();
            $graded = array_filter($att, static fn ($a) => $a['status'] === 'graded');
            $r['grade']    = $graded ? max(array_map(static fn ($a) => (float) $a['grade'], $graded)) : null;
            $r['feedback'] = null;
            $r['status']   = match (true) {
                (bool) array_filter($att, static fn ($a) => $a['status'] === 'in_progress') => 'in_progress',
                (bool) array_filter($att, static fn ($a) => $a['status'] === 'pending_review') => 'submitted',
                (bool) $graded => 'reviewed',
                default => null,
            };
        }

        return $rows;
    }

    /** Asignación + reto + entrega del estudiante, o null si no le corresponde. Con $previewGroup, sin entrega. */
    public function studentView(int $assignmentId, int $userId, ?int $previewGroup = null): ?array
    {
        foreach ($this->forStudent($userId, $previewGroup) as $row) {
            if ((int) $row['assignment_id'] === $assignmentId) {
                $act = $this->get((int) $row['activity_id']);
                $sub = $previewGroup === null ? model(SubmissionModel::class)->where(['assignment_id' => $assignmentId, 'user_id' => $userId])->first() : null;
                if ($sub) {
                    $sub['steps_done'] = json_decode((string) $sub['steps_done'], true) ?: [];
                    $sub['levels']     = json_decode((string) $sub['levels'], true) ?: [];
                }

                return ['assignment' => $act['assignment'], 'activity' => $act, 'submission' => $sub, 'state' => self::assignmentState($act['assignment'])];
            }
        }

        return null;
    }

    /** Guarda el borrador o entrega. $submit: true = entregar. Devuelve null o el mensaje de error. */
    public function studentSave(int $assignmentId, int $userId, string $answer, array $stepsDone, bool $submit): ?string
    {
        $v = $this->studentView($assignmentId, $userId);
        if (! $v) {
            return 'Ese reto no está disponible para usted.';
        }
        $sub = $v['submission'];
        if ($sub && in_array($sub['status'], ['submitted', 'reviewed'], true)) {
            return 'Ya entregó este reto. Si su docente se lo devuelve, podrá volver a entregarlo.';
        }
        $late = $this->now() > $v['assignment']['due_at'];
        if ($submit && $late && ! $v['assignment']['allow_late']) {
            return 'La fecha de entrega ya pasó y este reto no acepta entregas tardías.';
        }
        if ($submit && trim($answer) === '') {
            return 'Escriba su respuesta antes de entregar.';
        }
        // Un valor por paso del reto, por su índice (las casillas sin marcar no llegan en el formulario).
        $done = array_map(static fn ($i) => ! empty($stepsDone[$i]), array_keys($v['activity']['steps']));
        $data = [
            'answer' => mb_substr($answer, 0, 20000), 'steps_done' => json_encode($done),
            'status' => $submit ? 'submitted' : 'in_progress',
        ];
        if ($submit) {
            $data += ['submitted_at' => $this->now(), 'is_late' => $late ? 1 : 0];
        }
        if ($sub) {
            if ($sub['status'] === 'returned' && $submit) {
                $data['attempt'] = (int) $sub['attempt'] + 1;
            }
            model(SubmissionModel::class)->update($sub['id'], $data);
        } else {
            model(SubmissionModel::class)->insert($data + ['assignment_id' => $assignmentId, 'user_id' => $userId]);
        }
        if ($submit) {
            service('notify')->submitted($assignmentId, $userId);
        }

        return null;
    }
}
