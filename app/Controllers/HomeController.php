<?php

namespace App\Controllers;

/**
 * Inicio con institución activa: el docente ve su tablero; coordinación y rectoría, el resumen del colegio; el
 * superadministrador, el estado de la institución. Estudiantes y acudientes tienen su propio inicio.
 */
class HomeController extends BaseController
{
    public function index()
    {
        $tenant = service('tenant');
        if ($tenant->isStudent()) {
            return redirect()->to('estudiante');
        }
        if ($tenant->isGuardian()) {
            return redirect()->to('acudiente');
        }
        $ctx = $this->layoutData() + ['quota' => service('aiQuota')->balance()];
        if (service('auth')->isSuperadmin()) { // el superadministrador ve el estado de la institución, no el tablero de un docente
            return view('home/super', $ctx + ['title' => 'Resumen de la institución', 'o' => $this->overview((int) $tenant->institutionId())]);
        }

        // Coordinación y rectoría: resumen del colegio (no el tablero de un docente con todos los grupos).
        if ($tenant->can('academic.structure.manage', 'tenant.users.manage')) {
            return view('home/institution', $ctx + ['title' => 'Inicio', 'o' => $this->institution()]);
        }

        return view('home/teacher', $ctx + ['title' => 'Inicio', 'data' => $this->dashboard()]);
    }

    /**
     * Inicio de coordinación y rectoría: estudiantes que requieren acompañamiento (alertas de los reportes), docentes con
     * entregas atrasadas o sin publicar (Teachers::list), próximos eventos, últimas noticias y, si dicta, sus grupos.
     */
    private function institution(): array
    {
        $teachers = service('teachers')->list();
        $students = [];
        foreach (service('reports')->offerings() as $off) {
            foreach ((service('reports')->forOffering((int) $off['id'])['alerts'] ?? []) as $a) {
                $students[$a['id']] ??= ['id' => $a['id'], 'name' => $a['name'], 'group' => $off['group'], 'reasons' => []];
                $students[$a['id']]['reasons'][] = $off['subject'] . ': ' . $a['reason'];
            }
        }
        usort($students, static fn ($a, $b) => count($b['reasons']) <=> count($a['reasons']) ?: strcmp($a['name'], $b['name']));
        $flagged = array_values(array_filter($teachers, static fn ($t) => $t['alerts']));
        $late    = count(array_filter($teachers, static fn ($t) => ($d = \App\Libraries\Teachers::daysSince($t['oldest'])) !== null && $d >= \App\Libraries\Teachers::LATE_REVIEW_DAYS));
        $mine    = \App\Libraries\Academic::flatten(service('academic')->catalogFor((int) service('auth')->id(), 'teacher'));

        return [
            'kpis' => [
                ['Requieren acompañamiento', (string) count($students), 'Estudiantes con vencidas o promedio bajo', count($students) ? 'warn' : ''],
                ['Entregas por revisar', (string) array_sum(array_column($teachers, 'to_review')), 'En todo el colegio', ''],
                ['Docentes con revisión atrasada', (string) $late, 'Entregas sin revisar hace ' . \App\Libraries\Teachers::LATE_REVIEW_DAYS . ' días o más', $late ? 'warn' : ''],
                ['Docentes', (string) count($teachers), plural(array_sum(array_column($teachers, 'published')), 'actividad publicada', 'actividades publicadas'), ''],
            ],
            'students' => array_slice($students, 0, 6), 'studentsTotal' => count($students),
            'teachers' => array_slice($flagged, 0, 6), 'teachersTotal' => count($flagged),
            'events'   => array_slice(service('events')->upcoming(45), 0, 4),
            'news'     => array_slice(array_values(array_filter(service('news')->list(6), static fn ($n) => $n['status'] === 'published')), 0, 3),
            'groups'   => array_map(static fn ($i) => ['slug' => $i['slug'], 'title' => $i['subject'] . ' · ' . $i['group'], 'color' => $i['color'], 'students' => $i['students']], $mine),
        ];
    }

    /** Tablero del docente: indicadores, alertas de revisión, próximos cierres, sus grupos y páginas recientes. */
    private function dashboard(): array
    {
        $iid      = service('tenant')->institutionId();
        $acts     = service('activities')->listForTeacher();
        $toReview = array_sum(array_map('intval', array_column($acts, 'to_review')));
        $open     = array_values(array_filter($acts, static fn ($a) => $a['state'] === 'open'));
        $soon     = array_values(array_filter($open, static fn ($a) => $a['due_at'] <= date('Y-m-d H:i:s', strtotime('+7 days'))));
        $grades   = $acts ? db_connect()->table('submissions s')->select('AVG(s.grade) AS avg, COUNT(s.grade) AS n')
            ->join('activity_assignments x', 'x.id = s.assignment_id')->whereIn('x.activity_id', array_column($acts, 'id'))
            ->where('s.institution_id', $iid)->get()->getRowArray() : ['avg' => null, 'n' => 0];

        $alerts = [];
        foreach ($acts as $a) {
            if ((int) $a['to_review'] > 0) {
                $alerts[] = ['icon' => 'inbox', 'tone' => 'warn', 'text' => plural((int) $a['to_review'], 'entrega', 'entregas') . ' de «' . $a['title'] . '» (' . $a['label'] . ') esperan su revisión', 'route' => 'entregas/' . $a['assignment_id']];
            }
        }
        $nextBy = [];
        foreach ($open as $a) {
            $nextBy[$a['offering_id']] ??= $a['title'] . ' · vence ' . date_short($a['due_at']);
        }

        return [
            'kpis' => [
                ['label' => 'Entregas por revisar', 'value' => (string) $toReview, 'hint' => $toReview ? 'En ' . plural(count(array_filter($acts, static fn ($a) => (int) $a['to_review'] > 0)), 'actividad', 'actividades') : 'Nada pendiente'],
                ['label' => 'Actividades abiertas', 'value' => (string) count($open), 'hint' => plural(count($soon), 'cierra', 'cierran') . ' en los próximos 7 días'],
                ['label' => 'En borrador', 'value' => (string) count(array_filter($acts, static fn ($a) => $a['status'] === 'draft')), 'hint' => 'Sin publicar todavía'],
                ['label' => 'Promedio de calificaciones', 'value' => $grades['n'] ? str_replace('.', ',', number_format((float) $grades['avg'], 1)) : '—',
                    'hint' => $grades['n'] ? 'Escala de 1 a 5 · ' . plural((int) $grades['n'], 'entrega calificada', 'entregas calificadas') : 'Aún no hay entregas calificadas'],
            ],
            'alerts'   => $alerts,
            'upcoming' => array_map(static fn ($a) => ['date' => $a['due_at'], 'title' => $a['title'], 'group' => $a['label'], 'kind' => $a['kind']], array_slice($open, 0, 5)),
            'groups'   => array_map(static fn ($i) => [
                'slug' => $i['slug'], 'title' => $i['subject'] . ' · ' . $i['group'], 'color' => $i['color'],
                'students' => $i['students'], 'next' => $nextBy[$i['id']] ?? 'Sin retos abiertos',
            ], service('academic')->myOfferings()),
            // Últimas páginas que editó la persona.
            'recent' => array_map(static fn ($p) => [
                'title' => $p['title'], 'where' => $p['subject'] . ' · ' . $p['group_name'] . ' › ' . $p['section'],
                'url' => 'cuadernos/o' . $p['offering_id'] . '/' . $p['id'], 'when' => date_human($p['updated_at']),
            ], db_connect()->table('pages p')->select('p.id, p.title, p.updated_at, w.offering_id, sc.name AS section, s.name AS subject, g.name AS group_name')
                ->join('workspaces w', 'w.id = p.workspace_id')->join('sections sc', 'sc.id = p.section_id')
                ->join('subject_offerings o', 'o.id = w.offering_id')->join('subjects s', 's.id = o.subject_id')->join('class_groups g', 'g.id = o.group_id')
                ->where('p.institution_id', $iid)->where('p.updated_by', (int) service('auth')->id())->where('p.deleted_at', null)
                ->orderBy('p.updated_at', 'DESC')->limit(4)->get()->getResultArray()),
        ];
    }

    /** Resumen de la institución para el superadministrador: usuarios frente al plan, actividad, IA, administradores y auditoría reciente. */
    private function overview(int $iid): array
    {
        $db    = db_connect();
        $inst  = service('tenant')->institution();
        $plan  = service('aiQuota')->plan($iid);
        $users = (int) $db->table('institution_users')->where('institution_id', $iid)->where('status', 'active')->countAllResults();
        $max   = (int) ($plan['max_users'] ?? 0);
        $acts  = $db->table('activities')->select("SUM(CASE WHEN kind = 'quiz' THEN 1 ELSE 0 END) AS quizzes, SUM(CASE WHEN kind = 'challenge' THEN 1 ELSE 0 END) AS challenges", false)
            ->where('institution_id', $iid)->where('status', 'published')->where('deleted_at', null)->where('updated_at >=', date('Y-m-01 00:00:00'))->get()->getRowArray();
        $rep      = service('aiQuota')->report($iid);
        $students = (int) $db->table('institution_users hu')->join('roles r', 'r.id = hu.role_id')->where('hu.institution_id', $iid)->where('hu.status', 'active')->where('r.template_key', 'student')->countAllResults();
        $admins   = $db->table('institution_users hu')->select('u.name, r.name AS role, hu.last_access_at')->join('users u', 'u.id = hu.user_id')->join('roles r', 'r.id = hu.role_id')
            ->where('hu.institution_id', $iid)->where('hu.status', 'active')->whereIn('r.template_key', ['admin', 'coordinator'])->orderBy('u.name')->get()->getResultArray();

        $events = [];
        foreach ($db->table('audit_logs a')->select('a.action, a.detail, a.created_at, u.name')->join('users u', 'u.id = a.user_id', 'left')
            ->where('a.institution_id', $iid)->where('a.action !=', 'auth.login')->orderBy('a.id', 'DESC')->limit(8)->get()->getResultArray() as $e) {
            $d        = json_decode((string) $e['detail'], true) ?: [];
            $events[] = [$e['created_at'], audit_label($e['action']) . ($e['name'] ? ' · ' . $e['name'] : '') . ($d ? ' · ' . implode(', ', array_map(static fn ($k, $v) => $k . ': ' . (is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE)), array_keys($d), $d)) : '')];
        }

        return [
            'kpis' => [
                ['Usuarios activos', number_format($users, 0, ',', '.'), $max ? 'De ' . number_format($max, 0, ',', '.') . ' del plan' : 'Plan sin límite de usuarios'],
                ['Estudiantes', number_format($students, 0, ',', '.'), 'Con membresía activa'],
                ['Actividades del mes', (string) ((int) $acts['challenges'] + (int) $acts['quizzes']), plural((int) $acts['challenges'], 'reto', 'retos') . ' · ' . plural((int) $acts['quizzes'], 'evaluación', 'evaluaciones')],
                ['Cupo de IA', $rep['limit'] ? (int) round($rep['used'] * 100 / $rep['limit']) . ' %' : '—', $rep['limit'] ? 'Proyecta ' . $rep['projection'] . ' % al cierre del mes' : 'Plan a convenir'],
            ],
            'admins' => array_map(static fn ($a) => [$a['name'], $a['role'], $a['last_access_at']], $admins),
            'events' => $events,
            'plan'   => $plan['name'] ?? ($inst['plan'] ?? ''),
        ];
    }
}
