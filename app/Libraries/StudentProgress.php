<?php

namespace App\Libraries;

/**
 * Lo que ve el propio estudiante: sus actividades por estado y su progreso por asignatura. Úsela con
 * service('myProgress'). Todo sale de sus entregas e intentos reales (Activities::forStudent).
 * Con $group (vista de estudiante): las actividades del grupo, todas «por hacer» y sin notas.
 *
 * Estados de una actividad para el estudiante:
 * todo (por hacer) · late (vencida, aún acepta entrega tardía) · returned (devuelta para corregir) · in_progress ·
 * submitted (entregada, por revisar) · graded (calificada) · missed (vencida sin entregar y ya no la acepta).
 */
class StudentProgress
{
    public const GROUPS = [
        'pending' => ['Por hacer', ['todo', 'late', 'returned', 'in_progress']],
        'sent'    => ['Entregadas', ['submitted']],
        'graded'  => ['Calificadas', ['graded']],
        'missed'  => ['Vencidas', ['missed']],
    ];

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** Colores de asignatura del estudiante (por nombre). */
    private function colors(int $uid, ?int $group = null): array
    {
        $items = \App\Libraries\Academic::flatten(service('academic')->catalogFor($uid, $group === null ? 'student' : 'group', $group));

        return array_column($items, 'color', 'subject');
    }

    /** Actividades del estudiante con su estado, enlace y color de asignatura. */
    public function activities(int $uid, ?int $group = null): array
    {
        $colors = $this->colors($uid, $group);
        $now    = $this->now();

        return array_map(function ($a) use ($colors, $now) {
            $closed = $now > $a['due_at'];
            $a['state'] = match (true) {
                $a['status'] === 'reviewed'  => 'graded',
                $a['status'] === 'submitted' => 'submitted',
                $a['status'] === 'returned'  => $closed && ! $a['allow_late'] ? 'missed' : 'returned',
                $closed                      => $a['kind'] === 'challenge' && $a['allow_late'] ? 'late' : 'missed',
                $a['status'] === 'in_progress' => 'in_progress',
                default                      => 'todo',
            };
            $a['url']   = ($a['kind'] === 'quiz' ? 'estudiante/evaluaciones/' : 'estudiante/retos/') . $a['assignment_id'];
            $a['color'] = $colors[$a['subject']] ?? '#64748B';

            return $a;
        }, service('activities')->forStudent($uid, $group));
    }

    /** Progreso por asignatura, retroalimentación recibida y logros (cifras reales). */
    public function progress(int $uid, ?int $group = null): array
    {
        $acts     = $this->activities($uid, $group);
        $subjects = [];
        foreach ($this->colors($uid, $group) as $name => $color) {
            $subjects[$name] = ['name' => $name, 'color' => $color, 'total' => 0, 'done' => 0, 'grades' => [], 'missed' => 0];
        }
        foreach ($acts as $a) {
            $subjects[$a['subject']] ??= ['name' => $a['subject'], 'color' => $a['color'], 'total' => 0, 'done' => 0, 'grades' => [], 'missed' => 0];
            $s = &$subjects[$a['subject']];
            $s['total']++;
            $s['done']   += in_array($a['state'], ['submitted', 'graded'], true) ? 1 : 0;
            $s['missed'] += $a['state'] === 'missed' ? 1 : 0;
            if ($a['state'] === 'graded' && $a['grade'] !== null) {
                $s['grades'][] = (float) $a['grade'];
            }
            unset($s);
        }
        $all = [];
        foreach ($subjects as &$s) {
            $s['avg']      = $s['grades'] ? round(array_sum($s['grades']) / count($s['grades']), 1) : null;
            $s['progress'] = $s['total'] ? (int) round($s['done'] * 100 / $s['total']) : 0;
            $all           = array_merge($all, $s['grades']);
        }
        unset($s);

        $feedback = array_values(array_filter($acts, static fn ($a) => $a['state'] === 'graded'));
        usort($feedback, static fn ($a, $b) => strcmp((string) ($b['reviewed_at'] ?? $b['due_at']), (string) ($a['reviewed_at'] ?? $a['due_at'])));

        $db   = db_connect();
        $iid  = (int) service('tenant')->institutionId();
        $onTime = $group !== null ? 0 : (int) $db->table('submissions')->where('institution_id', $iid)->where('user_id', $uid)->whereIn('status', ['submitted', 'reviewed'])->where('is_late', 0)->countAllResults();
        $tutorChallenges = $group !== null ? 0 : (int) ($db->table('ai_messages')->select('COUNT(DISTINCT context) AS n', false)->where('institution_id', $iid)->where('user_id', $uid)->where('role', 'user')->get()->getRow()->n ?? 0);

        return [
            'subjects' => array_values($subjects),
            'avg'      => $all ? round(array_sum($all) / count($all), 1) : null,
            'feedback' => array_slice($feedback, 0, 8),
            'counts'   => ['total' => count($acts), 'done' => count(array_filter($acts, static fn ($a) => in_array($a['state'], ['submitted', 'graded'], true))),
                'missed' => count(array_filter($acts, static fn ($a) => $a['state'] === 'missed'))],
            'achievements' => [
                ['A tiempo', 'clock', $onTime ? plural($onTime, 'reto entregado a tiempo', 'retos entregados a tiempo') : 'Entrega tus retos antes de la fecha', $onTime > 0],
                ['Con el tutor', 'sparkles', $tutorChallenges ? 'Pediste guía al tutor en ' . plural($tutorChallenges, 'reto', 'retos') : 'Pide pistas al tutor cuando te atasques', $tutorChallenges > 0],
                ['Mejor nota', 'star', $all ? 'Tu mejor nota es ' . number_format(max($all), 1, ',', '') : 'Aún no tienes notas', (bool) $all],
            ],
        ];
    }
}
