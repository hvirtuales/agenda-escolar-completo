<?php

namespace App\Libraries;

/**
 * Docentes (coordinación y rectoría, permiso academic.structure.manage): acompañamiento pedagógico del periodo en curso.
 * Indicadores de carga y de retroalimentación pendiente por docente; sin registro minuto a minuto ni rankings.
 * Úsela con service('teachers').
 */
class Teachers
{
    /** Días sin revisar a partir de los cuales una entrega se marca como atrasada. */
    public const LATE_REVIEW_DAYS = 7;

    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    /** Ofertas del periodo por docente: [user_id => [ofertas]]. */
    private function offeringsByTeacher(): array
    {
        $period = service('academic')->currentPeriod();
        if (! $period) {
            return [];
        }
        $rows = $this->db()->table('teaching_assignments t')
            ->select('t.user_id, o.id, s.name AS subject, g.name AS group_name, g.id AS group_id, l.name AS level, l.id AS level_id, c.color,
                (SELECT COUNT(*) FROM class_group_members m WHERE m.group_id = g.id) AS students', false)
            ->join('subject_offerings o', 'o.id = t.offering_id')->join('subjects s', 's.id = o.subject_id')->join('categories c', 'c.id = s.category_id')
            ->join('class_groups g', 'g.id = o.group_id')->join('levels l', 'l.id = g.level_id')
            ->where('t.institution_id', $this->iid())->where('o.period_id', $period['id'])
            ->orderBy('l.sort_order')->orderBy('g.name')->orderBy('s.name')->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['user_id']][] = $r;
        }

        return $out;
    }

    /**
     * Indicadores por oferta: actividades publicadas/borrador/abiertas, entregas e intentos por revisar (y el más antiguo),
     * páginas publicadas (y la última). Clave: offering_id.
     */
    private function statsByOffering(array $offeringIds): array
    {
        if (! $offeringIds) {
            return [];
        }
        $db  = $this->db();
        $iid = $this->iid();
        $now = date('Y-m-d H:i:s');
        $out = array_fill_keys($offeringIds, ['published' => 0, 'drafts' => 0, 'open' => 0, 'to_review' => 0, 'oldest' => null, 'pages' => 0, 'last_page' => null, 'avg' => null]);

        foreach ($db->table('activities a')->select("a.offering_id, SUM(CASE WHEN a.status = 'published' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) AS drafts,
                SUM(CASE WHEN a.status = 'published' AND x.starts_at <= '{$now}' AND x.due_at >= '{$now}' THEN 1 ELSE 0 END) AS open", false)
            ->join('activity_assignments x', 'x.activity_id = a.id', 'left')
            ->where('a.institution_id', $iid)->where('a.deleted_at', null)->whereIn('a.offering_id', $offeringIds)->groupBy('a.offering_id')->get()->getResultArray() as $r) {
            $out[(int) $r['offering_id']] = ['published' => (int) $r['published'], 'drafts' => (int) $r['drafts'], 'open' => (int) $r['open']] + $out[(int) $r['offering_id']];
        }
        // Retos entregados sin revisar y evaluaciones con respuestas abiertas por revisar.
        $pending = array_merge(
            $db->table('submissions s')->select('a.offering_id, s.submitted_at AS at')->join('activity_assignments x', 'x.id = s.assignment_id')->join('activities a', 'a.id = x.activity_id')
                ->where('s.institution_id', $iid)->where('s.status', 'submitted')->whereIn('a.offering_id', $offeringIds)->get()->getResultArray(),
            $db->table('attempts t')->select('a.offering_id, t.finished_at AS at')->join('activity_assignments x', 'x.id = t.assignment_id')->join('activities a', 'a.id = x.activity_id')
                ->where('t.institution_id', $iid)->where('t.status', 'pending_review')->whereIn('a.offering_id', $offeringIds)->get()->getResultArray()
        );
        foreach ($pending as $p) {
            $o = &$out[(int) $p['offering_id']];
            $o['to_review']++;
            $o['oldest'] = $o['oldest'] === null || $p['at'] < $o['oldest'] ? $p['at'] : $o['oldest'];
            unset($o);
        }
        foreach ($db->table('pages p')->select('w.offering_id, COUNT(*) AS n, MAX(p.published_at) AS last', false)->join('workspaces w', 'w.id = p.workspace_id')
            ->where('p.institution_id', $iid)->where('p.deleted_at', null)->where('p.published_at IS NOT NULL', null, false)->whereIn('w.offering_id', $offeringIds)
            ->groupBy('w.offering_id')->get()->getResultArray() as $r) {
            $out[(int) $r['offering_id']]['pages']     = (int) $r['n'];
            $out[(int) $r['offering_id']]['last_page'] = $r['last'];
        }
        foreach ($db->table('submissions s')->select('a.offering_id, AVG(s.grade) AS avg', false)->join('activity_assignments x', 'x.id = s.assignment_id')->join('activities a', 'a.id = x.activity_id')
            ->where('s.institution_id', $iid)->where('s.grade IS NOT NULL', null, false)->whereIn('a.offering_id', $offeringIds)->groupBy('a.offering_id')->get()->getResultArray() as $r) {
            $out[(int) $r['offering_id']]['avg'] = round((float) $r['avg'], 1);
        }

        return $out;
    }

    /** Días enteros desde una fecha (null si no hay fecha). */
    public static function daysSince(?string $dt): ?int
    {
        return $dt ? (int) floor((time() - strtotime($dt)) / 86400) : null;
    }

    /**
     * Docentes de la institución: los que dictan en el periodo y quienes tienen rol docente sin asignaturas.
     * Cada uno: id, name, email, role, last_access, offerings[], totales (published, drafts, open, to_review, oldest, pages,
     * last_page), ai_tokens (mes) y alerts[].
     */
    public function list(): array
    {
        $iid    = $this->iid();
        $byUser = $this->offeringsByTeacher();
        $people = $this->db()->table('institution_users m')->select('u.id, u.name, u.email, r.name AS role, m.last_access_at')
            ->join('users u', 'u.id = m.user_id')->join('roles r', 'r.id = m.role_id', 'left')
            ->where('m.institution_id', $iid)->where('m.status', 'active')
            ->groupStart()->where('r.template_key', 'teacher')->orWhereIn('u.id', array_keys($byUser) ?: [0])->groupEnd()
            ->orderBy('u.name')->get()->getResultArray();
        $stats = $this->statsByOffering(array_values(array_unique(array_map('intval', array_merge(...array_map(static fn ($l) => array_column($l, 'id'), $byUser ?: [[]]))))));
        $ai    = array_column($this->db()->table('ai_usage')->select("user_id, SUM(CASE WHEN status = 'done' THEN tokens ELSE 0 END) AS tokens", false)
            ->where('institution_id', $iid)->where('created_at >=', date('Y-m-01 00:00:00'))->groupBy('user_id')->get()->getResultArray(), 'tokens', 'user_id');

        $out = [];
        foreach ($people as $p) {
            $offs = $byUser[(int) $p['id']] ?? [];
            $t    = ['published' => 0, 'drafts' => 0, 'open' => 0, 'to_review' => 0, 'oldest' => null, 'pages' => 0, 'last_page' => null];
            foreach ($offs as $o) {
                $s = $stats[(int) $o['id']] ?? null;
                if (! $s) {
                    continue;
                }
                foreach (['published', 'drafts', 'open', 'to_review', 'pages'] as $k) {
                    $t[$k] += $s[$k];
                }
                $t['oldest']    = $s['oldest'] && (! $t['oldest'] || $s['oldest'] < $t['oldest']) ? $s['oldest'] : $t['oldest'];
                $t['last_page'] = $s['last_page'] && (! $t['last_page'] || $s['last_page'] > $t['last_page']) ? $s['last_page'] : $t['last_page'];
            }
            $alerts = [];
            if (! $offs) {
                $alerts[] = 'Sin asignaturas en el periodo';
            }
            if (($d = self::daysSince($t['oldest'])) !== null && $d >= self::LATE_REVIEW_DAYS) {
                $alerts[] = 'Entregas sin revisar hace ' . $d . ' días';
            }
            if ($offs && ! $t['published']) {
                $alerts[] = 'Aún no publica actividades';
            }
            $out[] = $p + $t + ['offerings' => $offs, 'students' => array_sum(array_map('intval', array_column($offs, 'students'))),
                'ai_tokens' => (int) ($ai[$p['id']] ?? 0), 'alerts' => $alerts];
        }

        return $out;
    }

    /** Ficha de un docente: sus datos, sus asignaturas con indicadores, próximos cierres y entregas pendientes. */
    public function profile(int $userId): ?array
    {
        $t = current(array_filter($this->list(), static fn ($x) => (int) $x['id'] === $userId));
        if (! $t) {
            return null;
        }
        $ids   = array_map('intval', array_column($t['offerings'], 'id'));
        $stats = $this->statsByOffering($ids);
        foreach ($t['offerings'] as &$o) {
            $o += $stats[(int) $o['id']] ?? [];
        }
        unset($o);
        $now = date('Y-m-d H:i:s');
        $t['upcoming'] = $ids ? $this->db()->table('activity_assignments x')->select('x.id AS assignment_id, x.due_at, a.title, a.kind, s.name AS subject, g.name AS group_name')
            ->join('activities a', 'a.id = x.activity_id')->join('subject_offerings o', 'o.id = a.offering_id')->join('subjects s', 's.id = o.subject_id')->join('class_groups g', 'g.id = o.group_id')
            ->where('x.institution_id', $this->iid())->where('a.status', 'published')->where('a.deleted_at', null)->whereIn('a.offering_id', $ids)
            ->where('x.due_at >=', $now)->orderBy('x.due_at')->limit(6)->get()->getResultArray() : [];
        $t['pending'] = $ids ? $this->db()->table('activity_assignments x')
            ->select("x.id AS assignment_id, a.title, a.kind, s.name AS subject, g.name AS group_name,
                (SELECT COUNT(*) FROM submissions sb WHERE sb.assignment_id = x.id AND sb.status = 'submitted') + (SELECT COUNT(*) FROM attempts at WHERE at.assignment_id = x.id AND at.status = 'pending_review') AS n,
                LEAST(COALESCE((SELECT MIN(sb.submitted_at) FROM submissions sb WHERE sb.assignment_id = x.id AND sb.status = 'submitted'), '9999-12-31'),
                      COALESCE((SELECT MIN(at.finished_at) FROM attempts at WHERE at.assignment_id = x.id AND at.status = 'pending_review'), '9999-12-31')) AS oldest", false)
            ->join('activities a', 'a.id = x.activity_id')->join('subject_offerings o', 'o.id = a.offering_id')->join('subjects s', 's.id = o.subject_id')->join('class_groups g', 'g.id = o.group_id')
            ->where('x.institution_id', $this->iid())->where('a.deleted_at', null)->whereIn('a.offering_id', $ids)
            ->having('n >', 0)->orderBy('oldest')->get()->getResultArray() : [];

        return $t;
    }
}
