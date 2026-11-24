<?php

namespace App\Libraries;

/**
 * Reportes por oferta académica (documento maestro 4.8). Úsela con service('reports').
 *
 * Todo se calcula con datos reales: asignaciones de la oferta, entregas de retos (nota de la revisión) e intentos de
 * evaluaciones (mejor nota calificada). Las alertas de acompañamiento son señales con su razón, no diagnósticos.
 * Nota mínima aprobatoria: 3,0 (preferencia regional de la institución cuando exista en sus ajustes).
 */
class Reports
{
    public const PASSING = 3.0;

    /** Nota con un decimal y coma: 4 → «4,0». */
    public static function fmt(float $v): string
    {
        return number_format($v, 1, ',', '');
    }

    /** Oferta que se abre por defecto: la de la actividad asignada más reciente, o la primera. */
    public function defaultOffering(array $offerings): int
    {
        $ids = array_column($offerings, 'id');
        $row = $this->db()->table('activities a')->select('a.offering_id')->join('activity_assignments x', 'x.activity_id = a.id')
            ->where('a.institution_id', $this->iid())->whereIn('a.offering_id', $ids)->where('a.status', 'published')->where('a.deleted_at', null)
            ->orderBy('x.created_at', 'DESC')->limit(1)->get()->getRow();

        return $row ? (int) $row->offering_id : (int) $ids[0];
    }

    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    /** Ofertas sobre las que la persona puede ver reportes: coordinación todas; el docente las que dicta. */
    public function offerings(): array
    {
        return service('academic')->myOfferings();
    }

    /** Reporte completo de una oferta, o null si la persona no puede verla. */
    public function forOffering(int $offeringId): ?array
    {
        $offering = null;
        foreach ($this->offerings() as $o) {
            if ($o['id'] === $offeringId) {
                $offering = $o;
            }
        }
        if (! $offering) {
            return null;
        }
        $now = date('Y-m-d H:i:s');
        $iid = $this->iid();

        // Estudiantes del grupo de la oferta.
        $groupId  = (int) $this->db()->table('subject_offerings')->select('group_id')->where('institution_id', $iid)->where('id', $offeringId)->get()->getRow()->group_id;
        $students = $this->db()->table('class_group_members m')->select('u.id, u.name')->join('users u', 'u.id = m.user_id')
            ->where('m.institution_id', $iid)->where('m.group_id', $groupId)->orderBy('u.name')->get()->getResultArray();
        $sids = array_map('intval', array_column($students, 'id'));

        // Actividades publicadas y asignadas de la oferta.
        $acts = $this->db()->table('activities a')->select('a.id, a.title, a.kind, x.id AS assignment_id, x.starts_at, x.due_at')
            ->join('activity_assignments x', 'x.activity_id = a.id')
            ->where('a.institution_id', $iid)->where('a.offering_id', $offeringId)->where('a.status', 'published')->where('a.deleted_at', null)
            ->orderBy('x.due_at')->get()->getResultArray();
        $aids = array_map('intval', array_column($acts, 'assignment_id'));

        // Matriz nota[estudiante][asignación] y estado.
        $grades = [];
        $done   = [];
        $toReview = 0;
        if ($aids) {
            foreach ($this->db()->table('submissions')->select('assignment_id, user_id, status, grade')->where('institution_id', $iid)->whereIn('assignment_id', $aids)->get()->getResultArray() as $s) {
                if (in_array($s['status'], ['submitted', 'reviewed', 'returned'], true)) {
                    $done[$s['user_id']][$s['assignment_id']] = true;
                }
                if ($s['status'] === 'reviewed' && $s['grade'] !== null) {
                    $grades[$s['user_id']][$s['assignment_id']] = (float) $s['grade'];
                }
                $toReview += $s['status'] === 'submitted' ? 1 : 0;
            }
            foreach ($this->db()->table('attempts')->select('assignment_id, user_id, status, grade')->where('institution_id', $iid)->whereIn('assignment_id', $aids)->get()->getResultArray() as $t) {
                if ($t['status'] !== 'in_progress') {
                    $done[$t['user_id']][$t['assignment_id']] = true;
                }
                if ($t['status'] === 'graded' && $t['grade'] !== null) {
                    $grades[$t['user_id']][$t['assignment_id']] = max($grades[$t['user_id']][$t['assignment_id']] ?? 0, (float) $t['grade']);
                }
                $toReview += $t['status'] === 'pending_review' ? 1 : 0;
            }
        }

        // Promedio por actividad.
        $byActivity = [];
        foreach ($acts as $a) {
            $vals = array_values(array_filter(array_map(static fn ($sid) => $grades[$sid][$a['assignment_id']] ?? null, $sids), static fn ($v) => $v !== null));
            $byActivity[] = $a + [
                'avg'       => $vals ? round(array_sum($vals) / count($vals), 1) : null,
                'graded'    => count($vals),
                'submitted' => count(array_filter($sids, static fn ($sid) => ! empty($done[$sid][$a['assignment_id']]))),
                'closed'    => $now > $a['due_at'],
            ];
        }

        // Por estudiante: promedio, entregas faltantes vencidas y alertas.
        $rows   = [];
        $alerts = [];
        foreach ($students as $s) {
            $sid     = (int) $s['id'];
            $g       = $grades[$sid] ?? [];
            $avg     = $g ? round(array_sum($g) / count($g), 1) : null;
            $missing = array_values(array_filter($acts, static fn ($a) => $now > $a['due_at'] && empty($done[$sid][$a['assignment_id']])));
            $rows[]  = ['id' => $sid, 'name' => $s['name'], 'avg' => $avg, 'grades' => $g, 'missing' => count($missing),
                'submitted' => count($done[$sid] ?? [])];
            $reasons = [];
            if ($missing) {
                $reasons[] = 'No entregó ' . plural(count($missing), 'actividad vencida', 'actividades vencidas') . ' (' . implode(', ', array_slice(array_column($missing, 'title'), 0, 2)) . (count($missing) > 2 ? '…' : '') . ')';
            }
            if ($avg !== null && $avg < self::PASSING) {
                $reasons[] = 'Promedio ' . self::fmt($avg) . ', por debajo de ' . self::fmt(self::PASSING);
            }
            if ($reasons) {
                $alerts[] = ['id' => $sid, 'name' => $s['name'], 'avg' => $avg, 'reason' => implode(' · ', $reasons)];
            }
        }

        $allGrades = array_merge(...array_map('array_values', array_column($rows, 'grades')) ?: [[]]);
        $active    = count(array_filter($rows, static fn ($r) => $r['submitted'] > 0));

        return [
            'offering'   => $offering,
            'students'   => $rows,
            'activities' => $byActivity,
            'alerts'     => $alerts,
            'topics'     => $this->topics($aids),
            'questions'  => $this->hardestQuestions($aids),
            'kpis'       => [
                'participation' => $sids ? (int) round($active * 100 / count($sids)) : null,
                'active'        => $active,
                'total'         => count($sids),
                'avg'           => $allGrades ? round(array_sum($allGrades) / count($allGrades), 1) : null,
                'graded'        => count($allGrades),
                'closed'        => count(array_filter($byActivity, static fn ($a) => $a['closed'])),
                'activities'    => count($byActivity),
                'to_review'     => $toReview,
            ],
        ];
    }

    /** Respuestas objetivas de intentos terminados: [pregunta, respuesta] para calcular aciertos. */
    private function answered(array $assignmentIds): array
    {
        if (! $assignmentIds) {
            return [];
        }
        $iid      = $this->iid();
        $attempts = $this->db()->table('attempts t')->select('t.answers, x.activity_id')->join('activity_assignments x', 'x.id = t.assignment_id')
            ->where('t.institution_id', $iid)->whereIn('t.assignment_id', $assignmentIds)->where('t.status !=', 'in_progress')->get()->getResultArray();
        if (! $attempts) {
            return [];
        }
        $questions = [];
        foreach (array_unique(array_column($attempts, 'activity_id')) as $actId) {
            foreach (service('quizzes')->questions((int) $actId) as $q) {
                $questions[$actId][$q['id']] = $q;
            }
        }
        $out = [];
        foreach ($attempts as $t) {
            $answers = json_decode((string) $t['answers'], true) ?: [];
            foreach ($questions[$t['activity_id']] ?? [] as $qid => $q) {
                if ($q['type'] === 'long') {
                    continue;
                }
                $out[] = ['q' => $q, 'ok' => Quizzes::scoreOf($q, $answers[$qid] ?? null) >= $q['points'] - 1e-9];
            }
        }

        return $out;
    }

    /** Porcentaje de acierto por tema de las preguntas. */
    private function topics(array $assignmentIds): array
    {
        $acc = [];
        foreach ($this->answered($assignmentIds) as $r) {
            $topic = $r['q']['topic'] ?: 'Sin tema';
            $acc[$topic]['n']  = ($acc[$topic]['n'] ?? 0) + 1;
            $acc[$topic]['ok'] = ($acc[$topic]['ok'] ?? 0) + ($r['ok'] ? 1 : 0);
        }
        $out = [];
        foreach ($acc as $topic => $v) {
            $out[] = ['topic' => $topic, 'pct' => (int) round($v['ok'] * 100 / $v['n']), 'n' => $v['n']];
        }
        usort($out, static fn ($a, $b) => $a['pct'] <=> $b['pct']);

        return $out;
    }

    /** Preguntas con mayor porcentaje de error (mínimo una respuesta). */
    private function hardestQuestions(array $assignmentIds): array
    {
        $acc = [];
        foreach ($this->answered($assignmentIds) as $r) {
            $id = $r['q']['id'];
            $acc[$id] ??= ['q' => $r['q'], 'n' => 0, 'bad' => 0];
            $acc[$id]['n']++;
            $acc[$id]['bad'] += $r['ok'] ? 0 : 1;
        }
        $ids    = array_unique(array_map(static fn ($v) => (int) $v['q']['activity_id'], $acc));
        $titles = $ids ? array_column($this->db()->table('activities')->select('id, title')->whereIn('id', $ids)->get()->getResultArray(), 'title', 'id') : [];
        $out    = [];
        foreach ($acc as $v) {
            $actId = (int) $v['q']['activity_id'];
            $titles[$actId] ??= '';
            $out[] = ['text' => $v['q']['text'], 'where' => $titles[$actId] . ' · P' . ($v['q']['sort_order'] + 1), 'pct' => (int) round($v['bad'] * 100 / $v['n']), 'n' => $v['n']];
        }
        usort($out, static fn ($a, $b) => $b['pct'] <=> $a['pct']);

        return array_slice(array_values(array_filter($out, static fn ($q) => $q['pct'] > 0)), 0, 5);
    }

    /** Encabezado y filas del CSV de notas (el formato lo pone csv_build). */
    public function csvTable(array $report): array
    {
        $num  = static fn ($v) => $v === null ? '' : self::fmt($v);
        $rows = [];
        foreach ($report['students'] as $s) {
            $line = [$s['name']];
            foreach ($report['activities'] as $a) {
                $line[] = $num($s['grades'][$a['assignment_id']] ?? null);
            }
            $rows[] = array_merge($line, [$num($s['avg']), (string) $s['missing']]);
        }

        return [array_merge(['Estudiante'], array_column($report['activities'], 'title'), ['Promedio', 'Actividades vencidas sin entregar']), $rows];
    }
}
