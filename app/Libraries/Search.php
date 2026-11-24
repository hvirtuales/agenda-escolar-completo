<?php

namespace App\Libraries;

/**
 * Búsqueda global de la barra superior. Úsela con service('search').
 *
 * El alcance lo decide el servidor, nunca la pantalla:
 * - platform: superadministrador en la consola → instituciones y redes.
 * - network: consola de una red → sus colegios y bancos compartidos.
 * - institución activa, según los permisos de la persona:
 *   docente/coordinación → asignaturas, páginas, actividades, estudiantes, bancos y personas (cada grupo con su permiso);
 *   estudiante → sus asignaturas, páginas publicadas y actividades; acudiente → sus estudiantes y las actividades de ellos.
 * Cada resultado: group, label, sub, icon, url y post (campos para enviar por POST, p. ej. entrar a una institución).
 */
class Search
{
    private const PER_GROUP = 6;

    private function db()
    {
        return db_connect();
    }

    private static function has(string $text, string $q): bool
    {
        return mb_stripos($text, $q) !== false;
    }

    /** Texto de ayuda del campo según el contexto. */
    public static function placeholder(string $scope): string
    {
        $t = service('tenant');

        return match (true) {
            $scope === 'platform' => t('App.searchPlatform'),
            $scope === 'network'  => t('App.searchNetwork'),
            $t->isGuardian() => t('App.searchGuardian'),
            $t->isStudent()  => t('App.searchStudent'),
            $t->can('reports.view')       => t('App.searchStaff'),
            default                       => t('App.searchTeacher'),
        };
    }

    public function run(string $scope, string $q): array
    {
        $q = trim(mb_substr($q, 0, 80));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $auth = service('auth');
        if ($scope === 'platform' && $auth->isSuperadmin()) {
            return $this->platform($q);
        }
        if ($scope === 'network' && ($net = service('network')->active())) {
            return $this->network($net, $q);
        }
        $t = service('tenant');
        if (! $t->institution()) {
            return [];
        }
        if ($t->isGuardian()) {
            return $this->guardian($q);
        }
        if ($t->isStudent()) {
            return $this->student($q);
        }

        return $this->staff($q);
    }

    private function cap(array $rows): array
    {
        return array_slice($rows, 0, self::PER_GROUP);
    }

    /* ---------- Plataforma ---------- */

    private function platform(string $q): array
    {
        $out = [];
        foreach ($this->cap(array_filter(service('tenant')->availableInstitutions(), static fn ($i) => self::has($i['name'] . ' ' . $i['city'], $q))) as $i) {
            $out[] = ['group' => 'Instituciones', 'label' => $i['name'], 'sub' => trim($i['city'] . ' · Plan ' . $i['plan'], ' ·'), 'icon' => 'school',
                'url' => site_url('instituciones/cambiar'), 'post' => ['institution_id' => (int) $i['id']]];
        }
        foreach ($this->cap(array_filter(service('network')->forUser(), static fn ($n) => self::has($n['name'] . ' ' . $n['city'], $q))) as $n) {
            $out[] = ['group' => 'Redes', 'label' => $n['name'], 'sub' => plural((int) $n['schools'], 'colegio', 'colegios'), 'icon' => 'network',
                'url' => site_url('red/entrar'), 'post' => ['network_id' => (int) $n['id']]];
        }

        return $out;
    }

    /* ---------- Red ---------- */

    private function network(array $net, string $q): array
    {
        $out = [];
        foreach ($this->cap(array_filter(service('network')->institutions((int) $net['id']), static fn ($i) => self::has($i['name'] . ' ' . $i['city'], $q))) as $i) {
            $out[] = $net['role'] === 'admin' && $i['status'] !== 'suspended'
                ? ['group' => 'Colegios', 'label' => $i['name'], 'sub' => 'Entrar al colegio', 'icon' => 'school', 'url' => site_url('red/colegios/' . $i['id'] . '/entrar'), 'post' => []]
                : ['group' => 'Colegios', 'label' => $i['name'], 'sub' => (string) $i['city'], 'icon' => 'school', 'url' => site_url('red'), 'post' => null];
        }
        foreach ($this->cap(array_filter(service('network')->sharedBanks((int) $net['id']), static fn ($b) => self::has($b['name'] . ' ' . $b['subject'], $q))) as $b) {
            $out[] = ['group' => 'Bancos compartidos', 'label' => $b['name'], 'sub' => $b['school'], 'icon' => 'library', 'url' => site_url('red/biblioteca'), 'post' => null];
        }

        return $out;
    }

    /* ---------- Docente y coordinación ---------- */

    private function staff(string $q): array
    {
        $t    = service('tenant');
        $uid  = (int) service('auth')->id();
        $iid  = (int) $t->institutionId();
        $out  = [];
        $offs = service('academic')->myOfferings();

        if ($t->can('content.edit')) {
            foreach ($this->cap(array_filter($offs, static fn ($o) => self::has($o['subject'] . ' ' . $o['group'], $q))) as $o) {
                $out[] = ['group' => 'Asignaturas', 'label' => $o['subject'] . ' · ' . $o['group'], 'sub' => 'Abrir el cuaderno', 'icon' => 'notebook-tabs', 'url' => site_url('cuadernos/o' . $o['id']), 'post' => null];
            }
            if ($offs) {
                $pages = $this->db()->table('pages p')->select('p.id, p.title, w.offering_id')->join('workspaces w', 'w.id = p.workspace_id')
                    ->where('p.institution_id', $iid)->whereIn('w.offering_id', array_column($offs, 'id'))->where('p.deleted_at', null)->where('p.status !=', 'archived')
                    ->like('p.title', $q)->orderBy('p.updated_at', 'DESC')->limit(self::PER_GROUP)->get()->getResultArray();
                $byId = array_column($offs, null, 'id');
                foreach ($pages as $p) {
                    $o     = $byId[$p['offering_id']] ?? null;
                    $out[] = ['group' => 'Páginas', 'label' => $p['title'], 'sub' => $o ? $o['subject'] . ' · ' . $o['group'] : '', 'icon' => 'file-text',
                        'url' => site_url('cuadernos/o' . $p['offering_id'] . '/' . $p['id']), 'post' => null];
                }
            }
        }
        if ($t->can('activities.create')) {
            foreach ($this->cap(array_filter(service('activities')->listForTeacher(), static fn ($a) => self::has($a['title'], $q))) as $a) {
                $out[] = ['group' => 'Actividades', 'label' => $a['title'], 'sub' => ($a['kind'] === 'quiz' ? 'Evaluación' : 'Reto') . ' · ' . $a['label'],
                    'icon' => $a['kind'] === 'quiz' ? 'clipboard-list' : 'target', 'url' => site_url('actividades/' . $a['id']), 'post' => null];
            }
        }
        if ($t->can('reports.view')) {
            $groups = array_column(service('students')->groups(), 'name', 'id');
            if ($groups) {
                $rows = $this->db()->table('class_group_members m')->select('u.id, u.name, m.group_id')->join('users u', 'u.id = m.user_id')
                    ->where('m.institution_id', $iid)->whereIn('m.group_id', array_keys($groups))->like('u.name', $q)
                    ->orderBy('u.name')->limit(self::PER_GROUP)->get()->getResultArray();
                foreach ($rows as $s) {
                    $out[] = ['group' => 'Estudiantes', 'label' => $s['name'], 'sub' => $groups[$s['group_id']] ?? '', 'icon' => 'user', 'url' => site_url('estudiantes/' . $s['id']), 'post' => null];
                }
            }
        }
        if ($t->can('activities.create')) {
            foreach ($this->cap(service('banks')->list(['q' => $q])) as $b) {
                $out[] = ['group' => 'Bancos de preguntas', 'label' => $b['name'], 'sub' => $b['subject'] ?: 'General', 'icon' => 'library', 'url' => site_url('biblioteca/bancos/' . $b['id']), 'post' => null];
            }
        }
        if ($t->can('tenant.users.view', 'tenant.users.manage')) {
            $rows = $this->db()->table('institution_users hu')->select('u.name, u.email, r.name AS role')->join('users u', 'u.id = hu.user_id')->join('roles r', 'r.id = hu.role_id', 'left')
                ->where('hu.institution_id', $iid)->groupStart()->like('u.name', $q)->orLike('u.email', $q)->groupEnd()->orderBy('u.name')->limit(self::PER_GROUP)->get()->getResultArray();
            foreach ($rows as $u) {
                $out[] = ['group' => 'Personas', 'label' => $u['name'], 'sub' => trim(($u['role'] ?? '') . ' · ' . $u['email'], ' ·'), 'icon' => 'users',
                    'url' => site_url('ajustes/usuarios?q=' . rawurlencode($u['email'])), 'post' => null];
            }
        }

        return $out;
    }

    /* ---------- Estudiante ---------- */

    private function student(string $q): array
    {
        $uid  = (int) service('auth')->id();
        $iid  = (int) service('tenant')->institutionId();
        $out  = [];
        $offs = \App\Libraries\Academic::flatten(service('academic')->catalogFor($uid, 'student'));
        foreach ($this->cap(array_filter($offs, static fn ($o) => self::has($o['subject'], $q))) as $o) {
            $out[] = ['group' => 'Asignaturas', 'label' => $o['subject'], 'sub' => 'Abrir la asignatura', 'icon' => 'notebook-tabs', 'url' => site_url('estudiante/asignaturas/o' . $o['id']), 'post' => null];
        }
        if ($offs) {
            // Solo páginas publicadas (las que el estudiante puede leer).
            $pages = $this->db()->table('pages p')->select('p.id, p.title, w.offering_id')->join('workspaces w', 'w.id = p.workspace_id')
                ->where('p.institution_id', $iid)->whereIn('w.offering_id', array_column($offs, 'id'))->where('p.deleted_at', null)
                ->where('p.published_at IS NOT NULL', null, false)->where('p.status !=', 'archived')->like('p.title', $q)->limit(self::PER_GROUP)->get()->getResultArray();
            $byId = array_column($offs, null, 'id');
            foreach ($pages as $p) {
                $out[] = ['group' => 'Páginas', 'label' => $p['title'], 'sub' => $byId[$p['offering_id']]['subject'] ?? '', 'icon' => 'file-text',
                    'url' => site_url('estudiante/asignaturas/o' . $p['offering_id'] . '/' . $p['id']), 'post' => null];
            }
        }
        foreach ($this->cap(array_filter(service('myProgress')->activities($uid), static fn ($a) => self::has($a['title'], $q))) as $a) {
            $out[] = ['group' => 'Actividades', 'label' => $a['title'], 'sub' => $a['subject'] . ' · ' . ($a['kind'] === 'quiz' ? 'Evaluación' : 'Reto'),
                'icon' => $a['kind'] === 'quiz' ? 'clipboard-list' : 'target', 'url' => site_url($a['url']), 'post' => null];
        }

        return $out;
    }

    /* ---------- Acudiente ---------- */

    private function guardian(string $q): array
    {
        $out  = [];
        $kids = service('guardians')->studentsOf((int) service('auth')->id());
        foreach ($this->cap(array_filter($kids, static fn ($k) => self::has($k['name'], $q))) as $k) {
            $out[] = ['group' => 'Mis estudiantes', 'label' => $k['name'], 'sub' => (string) $k['group_name'], 'icon' => 'user', 'url' => site_url('acudiente/' . $k['id']), 'post' => null];
        }
        $acts = [];
        foreach ($kids as $k) {
            foreach (service('myProgress')->activities((int) $k['id']) as $a) {
                if (self::has($a['title'], $q)) {
                    $acts[] = ['group' => 'Actividades', 'label' => $a['title'], 'sub' => $k['name'] . ' · ' . $a['subject'], 'icon' => $a['kind'] === 'quiz' ? 'clipboard-list' : 'target',
                        'url' => site_url('acudiente/' . $k['id']), 'post' => null];
                }
            }
        }

        return array_merge($out, $this->cap($acts));
    }
}
