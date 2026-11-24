<?php

namespace App\Libraries;

use App\Models\ActivityModel;
use App\Models\AttemptModel;
use App\Models\QuestionModel;

/**
 * Evaluaciones (documento maestro 4.5 y 13.4). Úsela con service('quizzes').
 *
 * - Preguntas: single | multiple | truefalse | numeric (valor ± tolerancia) | short (respuestas aceptadas) | long (abierta).
 * - Intentos: el estudiante inicia dentro de las fechas de la asignación y hasta max_attempts. El tiempo límite se
 *   cuenta desde el inicio del intento y nunca pasa de la fecha de entrega. Las respuestas se guardan solas.
 * - Al terminar se califica lo objetivo; si hay abiertas, queda «pendiente de revisión» hasta que el docente las puntúe.
 * - Nota = 1 + 4 × puntaje / puntaje máximo (escala de 1,0 a 5,0). Cuenta el mejor intento calificado.
 * - Las respuestas correctas nunca se envían al navegador del estudiante mientras presenta.
 */
class Quizzes
{
    public const TYPES = ['single' => 'Selección única', 'multiple' => 'Selección múltiple', 'truefalse' => 'Verdadero o falso',
        'numeric' => 'Respuesta numérica', 'short' => 'Respuesta corta', 'long' => 'Respuesta abierta'];

    public const DIFFICULTIES = ['easy' => 'Fácil', 'medium' => 'Media', 'hard' => 'Difícil'];

    private const GRACE_SECONDS = 60;

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /* ---------- Preguntas ---------- */

    /** Preguntas de una evaluación. $withAnswers = false para el estudiante (sin respuestas correctas). */
    public function questions(int $activityId, bool $withAnswers = true): array
    {
        return array_map(static function ($q) use ($withAnswers) {
            $q['options'] = json_decode((string) $q['options'], true) ?: [];
            $q['correct'] = $withAnswers ? (json_decode((string) $q['correct'], true) ?: []) : null;
            $q['points']  = (float) $q['points'];

            return $q;
        }, model(QuestionModel::class)->where('activity_id', $activityId)->orderBy('sort_order')->findAll());
    }

    /** Crea una evaluación en borrador. Devuelve [id] o [error]. */
    public function create(int $offeringId, string $title): array
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 160) {
            return ['error' => 'Escriba un título de hasta 160 caracteres.'];
        }
        if (! service('content')->canEditOffering($offeringId)) {
            return ['error' => 'Solo puede crear evaluaciones en las asignaturas que dicta.'];
        }
        $uid = (int) service('auth')->id();
        $id  = (int) model(ActivityModel::class)->insert([
            'offering_id' => $offeringId, 'kind' => 'quiz', 'title' => $title, 'status' => 'draft', 'ai_mode' => 'off',
            'time_limit' => 30, 'max_attempts' => 1, 'shuffle' => 0, 'results_mode' => 'after_close',
            'steps' => '[]', 'hints' => '[]', 'rubric' => null, 'created_by' => $uid, 'updated_by' => $uid,
        ]);

        return ['id' => $id];
    }

    /** Guarda configuración y preguntas (reemplaza el conjunto). Devuelve null o [campo => mensaje]. */
    public function save(int $id, array $in): ?array
    {
        $act = service('activities')->get($id);
        if (! service('activities')->canManage($act) || $act['kind'] !== 'quiz') {
            return ['_' => 'Esa evaluación no existe o no puede editarla.'];
        }
        $data = [
            'title' => trim((string) ($in['title'] ?? '')), 'objective' => trim((string) ($in['objective'] ?? '')) ?: null,
            'instructions' => trim((string) ($in['instructions'] ?? '')) ?: null, 'time_limit' => (int) ($in['time_limit'] ?? 0),
            'max_attempts' => (int) ($in['max_attempts'] ?? 1), 'shuffle' => empty($in['shuffle']) ? 0 : 1,
            'results_mode' => (string) ($in['results_mode'] ?? 'after_close'),
        ];
        $v = service('validation');
        $v->reset();
        if (! $v->setRules([
            'title' => 'required|max_length[160]', 'time_limit' => 'is_natural|less_than_equal_to[600]',
            'max_attempts' => 'is_natural_no_zero|less_than_equal_to[10]', 'results_mode' => 'in_list[after_attempt,after_close]',
        ])->run($data)) {
            return $v->getErrors();
        }

        $clean = self::cleanQuestions((string) ($in['questions_json'] ?? '[]'));
        if (isset($clean['error'])) {
            return ['questions' => $clean['error']];
        }

        $db = db_connect();
        $db->transStart();
        model(ActivityModel::class)->update($id, $data + ['updated_by' => (int) service('auth')->id()]);
        $db->table('questions')->where('institution_id', service('tenant')->institutionId())->where('activity_id', $id)->delete();
        foreach ($clean as $i => $q) {
            model(QuestionModel::class)->insert(['activity_id' => $id, 'type' => $q['type'], 'text' => $q['text'], 'options' => json_encode($q['options'], JSON_UNESCAPED_UNICODE),
                'correct' => json_encode($q['correct'], JSON_UNESCAPED_UNICODE), 'points' => $q['points'], 'topic' => $q['topic'], 'sort_order' => $i]);
        }
        $db->transComplete();

        return $db->transStatus() ? null : ['_' => 'No se pudo guardar. Intente de nuevo.'];
    }

    /**
     * Valida y normaliza preguntas enviadas como JSON (editor de evaluaciones y de bancos).
     * Devuelve la lista limpia o ['error' => mensaje]. Las preguntas vacías se descartan.
     */
    public static function cleanQuestions(string $json): array
    {
        $clean = [];
        foreach ((array) json_decode($json, true) as $n => $q) {
            if (! is_array($q)) {
                continue;
            }
            $type = (string) ($q['type'] ?? '');
            $text = trim((string) ($q['text'] ?? ''));
            if (! isset(self::TYPES[$type]) || $text === '') {
                continue;
            }
            $pts = round((float) ($q['points'] ?? 1), 2);
            if ($pts <= 0 || $pts > 1000) {
                return ['error' => 'La pregunta ' . ($n + 1) . ' debe valer más de 0 puntos.'];
            }
            $options = [];
            $correct = [];
            if (in_array($type, ['single', 'multiple'], true)) {
                $options = array_values(array_filter(array_map(static fn ($o) => mb_substr(trim((string) $o), 0, 300), (array) ($q['options'] ?? [])), 'strlen'));
                if (count($options) < 2) {
                    return ['error' => 'La pregunta ' . ($n + 1) . ' necesita al menos dos opciones.'];
                }
                $correct = array_values(array_unique(array_filter(array_map('intval', (array) ($q['correct'] ?? [])), static fn ($i) => $i >= 0 && $i < count($options))));
                if (! $correct || ($type === 'single' && count($correct) !== 1)) {
                    return ['error' => 'Marque la respuesta correcta de la pregunta ' . ($n + 1) . '.'];
                }
            } elseif ($type === 'truefalse') {
                $options = ['Verdadero', 'Falso'];
                $correct = [((int) (($q['correct'] ?? [0])[0] ?? 0)) === 1 ? 1 : 0];
            } elseif ($type === 'numeric') {
                $val = str_replace(',', '.', (string) (($q['correct']['value'] ?? '')));
                if (! is_numeric($val)) {
                    return ['error' => 'Escriba el valor correcto (número) de la pregunta ' . ($n + 1) . '.'];
                }
                $correct = ['value' => (float) $val, 'tolerance' => abs((float) str_replace(',', '.', (string) ($q['correct']['tolerance'] ?? 0)))];
            } elseif ($type === 'short') {
                $correct = array_values(array_filter(array_map(static fn ($a) => mb_substr(trim((string) $a), 0, 120), (array) ($q['correct'] ?? [])), 'strlen'));
                if (! $correct) {
                    return ['error' => 'Escriba al menos una respuesta aceptada en la pregunta ' . ($n + 1) . '.'];
                }
            }
            $clean[] = ['type' => $type, 'text' => mb_substr($text, 0, 4000), 'options' => $options, 'correct' => $correct, 'points' => $pts,
                'topic' => mb_substr(trim((string) ($q['topic'] ?? '')), 0, 120) ?: null,
                'difficulty' => isset(self::DIFFICULTIES[$q['difficulty'] ?? '']) ? $q['difficulty'] : 'medium'];
        }

        return $clean;
    }

    /* ---------- Calificación ---------- */

    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));

        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    }

    /** Puntos de una respuesta objetiva; null para las abiertas (las califica el docente). */
    public static function scoreOf(array $q, $answer): ?float
    {
        $c = $q['correct'];
        switch ($q['type']) {
            case 'single':
            case 'truefalse':
                return ($answer !== null && $answer !== '' && (int) $answer === (int) ($c[0] ?? -1)) ? $q['points'] : 0.0;
            case 'multiple':
                $a = array_map('intval', (array) $answer);
                sort($a);
                $b = array_map('intval', (array) $c);
                sort($b);

                return $a === $b ? $q['points'] : 0.0;
            case 'numeric':
                $val = str_replace(',', '.', trim((string) $answer));

                return (is_numeric($val) && abs((float) $val - (float) $c['value']) <= (float) $c['tolerance'] + 1e-9) ? $q['points'] : 0.0;
            case 'short':
                return in_array(self::norm((string) $answer), array_map([self::class, 'norm'], (array) $c), true) ? $q['points'] : 0.0;
        }

        return null; // long
    }

    private static function gradeOf(float $score, float $max): float
    {
        return $max > 0 ? round(1 + 4 * $score / $max, 1) : 1.0;
    }

    /* ---------- Estudiante ---------- */

    public function attempts(int $assignmentId, int $userId): array
    {
        return model(AttemptModel::class)->where(['assignment_id' => $assignmentId, 'user_id' => $userId])->orderBy('number')->findAll();
    }

    /** Hora límite de un intento: inicio + tiempo límite, sin pasar de la fecha de entrega. */
    public static function deadline(array $attempt, array $activity, array $assignment): string
    {
        $limit = (int) $activity['time_limit'] > 0 ? strtotime($attempt['started_at']) + (int) $activity['time_limit'] * 60 : PHP_INT_MAX;

        return date('Y-m-d H:i:s', min($limit, strtotime($assignment['due_at'])));
    }

    /** Inicia (o retoma) un intento. Devuelve [id] o [error]. */
    public function start(int $assignmentId, int $userId): array
    {
        $v = service('activities')->studentView($assignmentId, $userId);
        if (! $v || $v['activity']['kind'] !== 'quiz') {
            return ['error' => 'Esa evaluación no está disponible para usted.'];
        }
        $att = $this->attempts($assignmentId, $userId);
        foreach ($att as $a) {
            if ($a['status'] === 'in_progress') {
                return ['id' => (int) $a['id']];
            }
        }
        if ($v['state'] !== 'open') {
            return ['error' => $v['state'] === 'closed' ? 'La evaluación ya cerró.' : 'La evaluación aún no abre.'];
        }
        if (count($att) >= (int) $v['activity']['max_attempts']) {
            return ['error' => 'Ya usó todos sus intentos.'];
        }
        $id = (int) model(AttemptModel::class)->insert([
            'assignment_id' => $assignmentId, 'user_id' => $userId, 'number' => count($att) + 1, 'status' => 'in_progress',
            'answers' => '{}', 'started_at' => $this->now(),
        ]);

        return ['id' => $id];
    }

    /** Intento propio con su evaluación y preguntas (sin respuestas correctas si aún no puede verlas). */
    public function attemptView(int $assignmentId, int $attemptId, int $userId): ?array
    {
        $v = service('activities')->studentView($assignmentId, $userId);
        $a = model(AttemptModel::class)->where(['assignment_id' => $assignmentId, 'user_id' => $userId])->find($attemptId);
        if (! $v || ! $a) {
            return null;
        }
        $a['answers'] = json_decode((string) $a['answers'], true) ?: [];
        $a['manual']  = json_decode((string) $a['manual'], true) ?: [];
        $showResults  = $a['status'] !== 'in_progress' && ($v['activity']['results_mode'] === 'after_attempt' || $v['state'] === 'closed');
        $questions    = $this->questions((int) $v['activity']['id'], $showResults);
        if ((int) $v['activity']['shuffle'] && $a['status'] === 'in_progress') {
            mt_srand($attemptId); // mismo orden al recargar
            shuffle($questions);
            mt_srand();
        }

        return $v + ['attempt' => $a, 'questions' => $questions, 'deadline' => self::deadline($a, $v['activity'], $v['assignment']), 'showResults' => $showResults];
    }

    /** Guardado automático de respuestas (JSON). */
    public function saveAnswers(int $assignmentId, int $attemptId, int $userId, array $answers): ?string
    {
        $view = $this->attemptView($assignmentId, $attemptId, $userId);
        if (! $view || $view['attempt']['status'] !== 'in_progress') {
            return 'Ese intento ya terminó o no es suyo.';
        }
        if ($this->now() > date('Y-m-d H:i:s', strtotime($view['deadline']) + self::GRACE_SECONDS)) {
            return 'Se acabó el tiempo del intento.';
        }
        $ids   = array_map('intval', array_column($view['questions'], 'id'));
        $clean = [];
        foreach ($answers as $qid => $val) {
            if (in_array((int) $qid, $ids, true)) {
                $clean[(int) $qid] = is_array($val) ? array_values(array_map('strval', $val)) : mb_substr((string) $val, 0, 8000);
            }
        }
        model(AttemptModel::class)->update($attemptId, ['answers' => json_encode($clean, JSON_UNESCAPED_UNICODE)]);

        return null;
    }

    /** Termina el intento y califica lo objetivo. */
    public function finish(int $assignmentId, int $attemptId, int $userId, ?array $answers = null): ?string
    {
        if ($answers !== null && ($e = $this->saveAnswers($assignmentId, $attemptId, $userId, $answers)) && $e !== 'Se acabó el tiempo del intento.') {
            return $e;
        }
        $view = $this->attemptView($assignmentId, $attemptId, $userId);
        if (! $view || $view['attempt']['status'] !== 'in_progress') {
            return 'Ese intento ya terminó o no es suyo.';
        }

        $over = $this->now() > date('Y-m-d H:i:s', strtotime($view['deadline']) + self::GRACE_SECONDS);
        $e    = $this->grade($view['attempt'], (int) $view['activity']['id'], [], null, $over);
        // Con respuestas abiertas queda por revisar: aviso a los docentes.
        if (! $e && (model(AttemptModel::class)->find($attemptId)['status'] ?? '') === 'pending_review') {
            service('notify')->submitted($assignmentId, $userId);
        }

        return $e;
    }

    /** Calcula puntaje y nota de un intento con los puntos manuales dados ($manual: id de pregunta => puntos). */
    private function grade(array $attempt, int $activityId, array $manual, ?string $feedback = null, bool $overTime = false): ?string
    {
        $qs      = $this->questions($activityId, true);
        $answers = is_array($attempt['answers']) ? $attempt['answers'] : (json_decode((string) $attempt['answers'], true) ?: []);
        $auto    = 0.0;
        $max     = 0.0;
        $open    = 0;
        $man     = 0.0;
        foreach ($qs as $q) {
            $max += $q['points'];
            $s = self::scoreOf($q, $answers[$q['id']] ?? null);
            if ($s === null) {
                if (isset($manual[$q['id']]) && $manual[$q['id']] !== '') {
                    $man += max(0, min($q['points'], (float) str_replace(',', '.', (string) $manual[$q['id']])));
                } else {
                    $open++;
                }
            } else {
                $auto += $s;
            }
        }
        $score  = $auto + $man;
        $finish = $attempt['finished_at'] ?? null;
        $data   = [
            'auto_score' => $auto, 'max_score' => $max, 'manual' => json_encode((object) $manual),
            'status' => $open > 0 ? 'pending_review' : 'graded', 'score' => $open > 0 ? null : $score,
            'grade' => $open > 0 ? null : self::gradeOf($score, $max),
        ];
        if (! $finish) {
            $data['finished_at'] = $this->now();
            $data['over_time']   = $overTime ? 1 : 0;
        }
        if ($feedback !== null) {
            $data['feedback']    = mb_substr(trim($feedback), 0, 4000) ?: null;
            $data['reviewed_by'] = (int) service('auth')->id();
        }
        model(AttemptModel::class)->update($attempt['id'], $data);

        return null;
    }

    /* ---------- Docente ---------- */

    /** Estudiantes del grupo con su último intento terminado (o sin presentar). */
    public function reviewList(int $assignmentId): array
    {
        $x = model(\App\Models\ActivityAssignmentModel::class)->find($assignmentId);
        if (! $x) {
            return [];
        }
        $iid  = (int) service('tenant')->institutionId();
        $rows = db_connect()->table('class_group_members m')->select('u.id AS user_id, u.name')
            ->join('users u', 'u.id = m.user_id')->where('m.institution_id', $iid)->where('m.group_id', $x['group_id'])
            ->orderBy('u.name')->get()->getResultArray();
        $att  = model(AttemptModel::class)->where('assignment_id', $assignmentId)->orderBy('number')->findAll();
        foreach ($rows as &$r) {
            $mine     = array_values(array_filter($att, static fn ($a) => (int) $a['user_id'] === (int) $r['user_id']));
            $finished = array_values(array_filter($mine, static fn ($a) => $a['status'] !== 'in_progress'));
            $best     = null;
            foreach ($finished as $a) {
                if ($best === null || ($a['grade'] !== null && ($best['grade'] === null || (float) $a['grade'] > (float) $best['grade']))) {
                    $best = $a;
                }
            }
            $pending      = array_values(array_filter($finished, static fn ($a) => $a['status'] === 'pending_review'));
            $r['attempt'] = $pending[0] ?? $best;
            $r['count']   = count($mine);
            $r['working'] = (bool) array_filter($mine, static fn ($a) => $a['status'] === 'in_progress');
        }

        return $rows;
    }

    /** El docente puntúa las abiertas y deja retroalimentación. */
    public function review(int $assignmentId, int $attemptId, array $manual, string $feedback): ?string
    {
        $x   = model(\App\Models\ActivityAssignmentModel::class)->find($assignmentId);
        $act = $x ? service('activities')->get((int) $x['activity_id']) : null;
        if (! service('activities')->canManage($act)) {
            return 'No puede revisar esa evaluación.';
        }
        $a = model(AttemptModel::class)->where('assignment_id', $assignmentId)->find($attemptId);
        if (! $a || $a['status'] === 'in_progress') {
            return 'Ese intento no existe o aún no termina.';
        }

        $e = $this->grade($a, (int) $act['id'], $manual, $feedback);
        $after = model(AttemptModel::class)->find($attemptId);
        if (! $e && ($after['status'] ?? '') === 'graded') {
            service('notify')->graded($assignmentId, (int) $a['user_id'], (float) $after['grade']);
        }

        return $e;
    }
}
