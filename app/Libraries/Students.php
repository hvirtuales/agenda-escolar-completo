<?php

namespace App\Libraries;

/**
 * Estudiantes vistos por el docente y la coordinación: lista por grupo y ficha individual. Úsela con service('students').
 *
 * Alcance: la coordinación (academic.structure.manage) ve todos los grupos y asignaturas del periodo; el docente ve los
 * grupos donde dicta y, en la ficha, solo las asignaturas que dicta. Reutiliza los cálculos de Reports por oferta.
 * Las observaciones las ven los docentes del estudiante y la coordinación; nunca el estudiante ni la red.
 */
class Students
{
    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    private function isCoordination(): bool
    {
        return service('tenant')->can('academic.structure.manage');
    }

    /** Ofertas visibles (Reports::offerings) agrupadas por grupo. */
    private function offeringsByGroup(): array
    {
        $out = [];
        foreach (service('reports')->offerings() as $o) {
            $out[$o['group_id']][] = $o;
        }

        return $out;
    }

    /** Grupos visibles con su número de estudiantes. */
    public function groups(): array
    {
        $by = $this->offeringsByGroup();
        if (! $by) {
            return [];
        }

        return $this->db()->table('class_groups g')->select('g.id, g.name, l.name AS level, (SELECT COUNT(*) FROM class_group_members m WHERE m.group_id = g.id) AS students', false)
            ->join('levels l', 'l.id = g.level_id')->where('g.institution_id', $this->iid())->whereIn('g.id', array_keys($by))
            ->orderBy('l.sort_order')->orderBy('g.name')->get()->getResultArray();
    }

    /** Lista de un grupo: promedio, entregas, último acceso y alertas por estudiante. Null si no puede verlo. */
    public function roster(int $groupId): ?array
    {
        $offs = $this->offeringsByGroup()[$groupId] ?? null;
        if (! $offs) {
            return null;
        }
        $group = $this->db()->table('class_groups g')->select('g.id, g.name, l.name AS level')->join('levels l', 'l.id = g.level_id')
            ->where('g.institution_id', $this->iid())->where('g.id', $groupId)->get()->getRowArray();
        $members = $this->db()->table('class_group_members m')->select('u.id, u.name, hu.last_access_at')->join('users u', 'u.id = m.user_id')
            ->join('institution_users hu', 'hu.user_id = u.id AND hu.institution_id = m.institution_id', 'left')
            ->where('m.institution_id', $this->iid())->where('m.group_id', $groupId)->orderBy('u.name')->get()->getResultArray();

        $acc = [];
        foreach ($members as $m) {
            $acc[$m['id']] = ['id' => (int) $m['id'], 'name' => $m['name'], 'last_access' => $m['last_access_at'], 'grades' => [], 'missing' => 0, 'done' => 0, 'total' => 0, 'alerts' => []];
        }
        foreach ($offs as $o) {
            $r = service('reports')->forOffering((int) $o['id']);
            if (! $r) {
                continue;
            }
            foreach ($r['students'] as $s) {
                if (! isset($acc[$s['id']])) {
                    continue;
                }
                $acc[$s['id']]['grades']  = array_merge($acc[$s['id']]['grades'], array_values($s['grades']));
                $acc[$s['id']]['missing'] += $s['missing'];
                $acc[$s['id']]['done']    += $s['submitted'];
                $acc[$s['id']]['total']   += count($r['activities']);
            }
            foreach ($r['alerts'] as $a) {
                if (isset($acc[$a['id']])) {
                    $acc[$a['id']]['alerts'][] = $o['subject'] . ': ' . $a['reason'];
                }
            }
        }
        foreach ($acc as &$row) {
            $row['avg'] = $row['grades'] ? round(array_sum($row['grades']) / count($row['grades']), 1) : null;
        }
        unset($row);

        return ['group' => $group, 'subjects' => array_column($offs, 'subject'), 'students' => array_values($acc), 'all' => $this->isCoordination()];
    }

    /** Ficha del estudiante. Null si no es estudiante de un grupo visible. */
    public function profile(int $studentId): ?array
    {
        $iid    = $this->iid();
        $period = service('academic')->currentPeriod();
        $groups = $period ? $this->db()->table('class_group_members m')->select('g.id, g.name, l.name AS level')->join('class_groups g', 'g.id = m.group_id')
            ->join('levels l', 'l.id = g.level_id')->where('m.institution_id', $iid)->where('m.user_id', $studentId)->where('g.period_id', $period['id'])
            ->get()->getResultArray() : [];
        $byGroup = $this->offeringsByGroup();
        $offs    = [];
        foreach ($groups as $g) {
            $offs = array_merge($offs, $byGroup[$g['id']] ?? []);
        }
        if (! $offs) {
            return null;
        }
        $student = $this->db()->table('users u')->select('u.id, u.name, u.email, u.doc_type, u.doc_number, hu.last_access_at')
            ->join('institution_users hu', 'hu.user_id = u.id AND hu.institution_id = ' . $iid)->where('u.id', $studentId)->get()->getRowArray();

        $subjects = [];
        $all      = [];
        foreach ($offs as $o) {
            $r = service('reports')->forOffering((int) $o['id']);
            if (! $r) {
                continue;
            }
            $me   = current(array_filter($r['students'], static fn ($s) => $s['id'] === $studentId)) ?: ['avg' => null, 'grades' => [], 'missing' => 0];
            $aids = array_map('intval', array_column($r['activities'], 'assignment_id'));
            $subs = $aids ? array_column($this->db()->table('submissions')->select('assignment_id, status, grade, feedback, submitted_at, is_late')
                ->where('institution_id', $iid)->where('user_id', $studentId)->whereIn('assignment_id', $aids)->get()->getResultArray(), null, 'assignment_id') : [];
            $atts = [];
            foreach ($aids ? $this->db()->table('attempts')->select('assignment_id, status, grade, finished_at')->where('institution_id', $iid)
                ->where('user_id', $studentId)->whereIn('assignment_id', $aids)->get()->getResultArray() : [] as $t) {
                $atts[$t['assignment_id']][] = $t;
            }
            $tutor = $aids ? array_column($this->db()->table('ai_messages')->select('context, COUNT(*) AS n', false)->where('institution_id', $iid)
                ->where('user_id', $studentId)->where('role', 'user')->whereIn('context', array_map(static fn ($a) => 'assignment:' . $a, $aids))
                ->groupBy('context')->get()->getResultArray(), 'n', 'context') : [];

            $items = [];
            foreach ($r['activities'] as $a) {
                $x = (int) $a['assignment_id'];
                if ($a['kind'] === 'quiz') {
                    $tries  = $atts[$x] ?? [];
                    $graded = array_filter($tries, static fn ($t) => $t['status'] === 'graded');
                    $status = match (true) {
                        (bool) array_filter($tries, static fn ($t) => $t['status'] === 'in_progress') => 'in_progress',
                        (bool) array_filter($tries, static fn ($t) => $t['status'] === 'pending_review') => 'submitted',
                        (bool) $graded => 'reviewed',
                        default => null,
                    };
                    $detail = $tries ? plural(count($tries), 'intento', 'intentos') : '';
                    $feedback = null;
                } else {
                    $s        = $subs[$x] ?? null;
                    $status   = $s['status'] ?? null;
                    $detail   = $s && $s['is_late'] ? 'Entrega tardía' : '';
                    $feedback = $s['feedback'] ?? null;
                }
                $items[] = $a + ['status' => $status, 'grade' => $me['grades'][$x] ?? null, 'detail' => $detail, 'feedback' => $feedback,
                    'tutor' => (int) ($tutor['assignment:' . $x] ?? 0), 'late' => ! $status && $a['closed']];
            }
            $teacher = $this->db()->table('teaching_assignments t')->select('u.name')->join('users u', 'u.id = t.user_id')
                ->where('t.institution_id', $iid)->where('t.offering_id', $o['id'])->get()->getRow()->name ?? null;
            $subjects[] = ['offering' => $o, 'teacher' => $teacher, 'avg' => $me['avg'], 'missing' => $me['missing'], 'items' => $items];
            $all        = array_merge($all, array_values($me['grades']));
        }

        // Solo las consultas de las actividades que la persona puede ver.
        $tutorTotal = array_sum(array_map(static fn ($sub) => array_sum(array_column($sub['items'], 'tutor')), $subjects));

        return [
            'student'  => $student,
            'groups'   => $groups,
            'subjects' => $subjects,
            'avg'      => $all ? round(array_sum($all) / count($all), 1) : null,
            'missing'  => array_sum(array_column($subjects, 'missing')),
            'tutor'    => $tutorTotal,
            'notes'    => $this->notes($studentId),
            'all'      => $this->isCoordination(),
        ];
    }

    /* ---------- Observaciones ---------- */

    public function notes(int $studentId): array
    {
        return $this->db()->table('student_notes n')->select('n.*, u.name AS author, s.name AS subject')
            ->join('users u', 'u.id = n.author_id', 'left')
            ->join('subject_offerings o', 'o.id = n.offering_id', 'left')->join('subjects s', 's.id = o.subject_id', 'left')
            ->where('n.institution_id', $this->iid())->where('n.student_id', $studentId)->orderBy('n.id', 'DESC')->get()->getResultArray();
    }

    public function addNote(int $studentId, string $body, ?int $offeringId): ?string
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            return 'Escriba la observación (hasta 2000 caracteres).';
        }
        if (! $this->profile($studentId)) {
            return 'Ese estudiante no está en sus grupos.';
        }
        if ($offeringId && ! in_array($offeringId, array_column(service('reports')->offerings(), 'id'), true)) {
            $offeringId = null;
        }
        $now = date('Y-m-d H:i:s');
        $this->db()->table('student_notes')->insert(['institution_id' => $this->iid(), 'student_id' => $studentId, 'author_id' => (int) service('auth')->id(),
            'offering_id' => $offeringId ?: null, 'body' => $body, 'created_at' => $now, 'updated_at' => $now]);

        return null;
    }

    public function deleteNote(int $studentId, int $noteId): ?string
    {
        $n = $this->db()->table('student_notes')->where('institution_id', $this->iid())->where('student_id', $studentId)->where('id', $noteId)->get()->getRowArray();
        if (! $n) {
            return 'Esa observación no existe.';
        }
        if ((int) $n['author_id'] !== (int) service('auth')->id() && ! $this->isCoordination()) {
            return 'Solo quien la escribió o la coordinación puede eliminarla.';
        }
        $this->db()->table('student_notes')->where('id', $noteId)->delete();

        return null;
    }
}
