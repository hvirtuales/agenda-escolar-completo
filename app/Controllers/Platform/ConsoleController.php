<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use App\Libraries\Rbac;
use App\Models\InstitutionModel;
use App\Models\UserModel;

/**
 * Consola de plataforma (solo superadministrador; filtro superadmin en Routes): instituciones, planes, consumo de IA,
 * auditoría y configuración, con datos reales. Todo cambio queda en la auditoría.
 */
class ConsoleController extends BaseController
{
    private const STATUSES = ['active' => 'Activa', 'trial' => 'En prueba', 'suspended' => 'Suspendida'];

    private function ctx(array $data = []): array
    {
        return $data + $this->platformLayout();
    }

    private function db()
    {
        return db_connect();
    }

    /** Planes por nombre. */
    private function plans(): array
    {
        return array_column($this->db()->table('plans')->orderBy('sort_order')->orderBy('name')->get()->getResultArray(), null, 'name');
    }

    /** Consumo de IA del mes por institución (tokens, costo, llamadas) y % del plan. */
    private function aiByInstitution(): array
    {
        $from  = date('Y-m-01 00:00:00');
        $rows  = $this->db()->table('ai_usage')->select("institution_id, SUM(CASE WHEN status = 'done' THEN tokens ELSE 0 END) AS tokens,
            SUM(cost_usd) AS cost, SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS calls, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed", false)
            ->where('created_at >=', $from)->groupBy('institution_id')->get()->getResultArray();

        return array_column($rows, null, 'institution_id');
    }

    /* ---------- Instituciones ---------- */

    public function institutions()
    {
        $f     = ['q' => trim((string) $this->request->getGet('q')), 'estado' => (string) $this->request->getGet('estado'), 'plan' => (string) $this->request->getGet('plan')];
        $plans = $this->plans();
        $ai    = $this->aiByInstitution();
        $q     = $this->db()->table('institutions i')
            ->select("i.*, n.name AS network, (SELECT COUNT(*) FROM institution_users m WHERE m.institution_id = i.id AND m.status = 'active') AS users", false)
            ->join('networks n', 'n.id = i.network_id', 'left')->where('i.deleted_at', null);
        if ($f['q'] !== '') {
            $q->groupStart()->like('i.name', $f['q'])->orLike('i.city', $f['q'])->groupEnd();
        }
        if (isset(self::STATUSES[$f['estado']])) {
            $q->where('i.status', $f['estado']);
        }
        if ($f['plan'] !== '') {
            $q->where('i.plan', $f['plan']);
        }
        $list = [];
        foreach ($q->orderBy('i.name')->get()->getResultArray() as $i) {
            $limit  = (int) ($plans[$i['plan']]['monthly_tokens'] ?? 0);
            $used   = service('aiQuota')->used((int) $i['id'], 'month');
            $list[] = $i + ['ai_used' => $used, 'ai_limit' => $limit, 'ai_pct' => $limit ? (int) round($used * 100 / $limit) : null,
                'max_users' => (int) ($plans[$i['plan']]['max_users'] ?? 0)];
        }

        $all    = $this->db()->table('institutions')->select('status, COUNT(*) AS n', false)->where('deleted_at', null)->groupBy('status')->get()->getResultArray();
        $counts = array_column($all, 'n', 'status');
        $alerts = [];
        foreach ($list as $i) {
            if ($i['ai_pct'] !== null && $i['ai_pct'] >= 80 && $i['status'] !== 'suspended') {
                $alerts[] = $i['name'] . ' va en el ' . $i['ai_pct'] . ' % de su cupo de IA del mes (plan ' . $i['plan'] . ').';
            }
            if ($i['max_users'] && $i['users'] >= $i['max_users']) {
                $alerts[] = $i['name'] . ' llegó al límite de usuarios de su plan (' . $i['max_users'] . ').';
            }
        }

        // «Crear institución» desde una solicitud de demostración: el alta se abre con sus datos.
        $prefill = null;
        if ($sid = (int) $this->request->getGet('solicitud')) {
            $d = $this->db()->table('demo_requests')->where('id', $sid)->where('status !=', 'won')->get()->getRowArray();
            $prefill = $d ? ['id' => null, 'name' => $d['institution'], 'tax_id' => '', 'city' => (string) $d['city'], 'timezone' => 'America/Bogota', 'plan' => 'Prueba',
                'admin_name' => $d['name'], 'admin_email' => $d['email'], 'request_id' => (int) $d['id']] : null;
        }

        return view('platform/institutions', $this->ctx([
            'prefill' => $prefill,
            'title' => 'Instituciones', 'list' => $list, 'filters' => $f, 'plans' => $plans, 'statuses' => self::STATUSES, 'alerts' => $alerts,
            'kpi'   => [
                'active' => (int) ($counts['active'] ?? 0), 'trial' => (int) ($counts['trial'] ?? 0), 'suspended' => (int) ($counts['suspended'] ?? 0),
                'users'  => (int) $this->db()->table('institution_users')->select('COUNT(DISTINCT user_id) AS n', false)->where('status', 'active')->get()->getRow()->n,
                'tokens' => array_sum(array_column($ai, 'tokens')), 'calls' => array_sum(array_column($ai, 'calls')), 'cost' => array_sum(array_column($ai, 'cost')),
            ],
        ]));
    }

    private function validInstitution(array $in, bool $create = true): array
    {
        $err = [];
        if ($in['name'] === '' || mb_strlen($in['name']) > 160) {
            $err[] = 'Escriba el nombre de la institución (hasta 160 caracteres).';
        }
        if (! isset($this->plans()[$in['plan']])) {
            $err[] = 'Elija un plan existente.';
        }
        if ($create && ! isset(\App\Models\InstitutionModel::TIMEZONES[$in['timezone']])) {
            $err[] = 'Elija una zona horaria válida.';
        }

        return $err;
    }

    public function createInstitution()
    {
        $in  = $this->postFields(['name', 'tax_id', 'city', 'timezone', 'plan', 'admin_name', 'admin_email']);
        $err = $this->validInstitution($in);
        $email = mb_strtolower($in['admin_email']);
        if ($in['admin_name'] === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err[] = 'Escriba el nombre y un correo válido del administrador.';
        }
        if ($err) {
            return redirect()->back()->withInput()->with('toast', ['type' => 'error', 'msg' => $err[0]]);
        }
        $db = $this->db();
        $db->transStart();
        $m   = model(InstitutionModel::class);
        $iid = (int) $m->insert(['name' => $in['name'], 'slug' => $m->uniqueSlug($in['name']), 'tax_id' => $in['tax_id'] ?: null, 'city' => $in['city'] ?: null,
            'timezone' => $in['timezone'], 'plan' => $in['plan'], 'status' => $in['plan'] === 'Prueba' ? 'trial' : 'active']);
        $roles = service('rbac')->copyTemplatesToInstitution($iid);

        // Administrador: si ya tiene cuenta se usa; si no, se crea con una contraseña temporal (se muestra una sola vez).
        $user = model(UserModel::class)->findByEmail($email);
        $temp = null;
        if (! $user) {
            $temp = 'Agenda-' . substr(bin2hex(random_bytes(4)), 0, 8);
            $uid  = (int) model(UserModel::class)->insert(['name' => $in['admin_name'], 'email' => $email, 'password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'is_superadmin' => 0, 'status' => 'active', 'must_change_password' => 1]);
        } else {
            $uid = (int) $user['id'];
        }
        $now = date('Y-m-d H:i:s');
        $db->table('institution_users')->insert(['institution_id' => $iid, 'user_id' => $uid, 'role_id' => $roles['admin'], 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('toast', ['type' => 'error', 'msg' => 'No se pudo crear la institución. Intente de nuevo.']);
        }
        service('audit')->log('platform.institution.create', ['institución' => $in['name'], 'plan' => $in['plan'], 'administrador' => $email], $iid);
        if ($rid = (int) $this->request->getPost('request_id')) { // venía de una solicitud de demostración: queda convertida
            $db->table('demo_requests')->where('id', $rid)->update(['status' => 'won', 'institution_id' => $iid, 'handled_by' => service('auth')->id(), 'updated_at' => date('Y-m-d H:i:s')]);
        }

        return redirect()->to('plataforma/instituciones')->with('toast', ['type' => 'ok', 'msg' => 'Institución creada.'])
            ->with('created', ['name' => $in['name'], 'email' => $email, 'temp' => $temp]);
    }

    public function updateInstitution(int $id)
    {
        $inst = model(InstitutionModel::class)->find($id);
        if (! $inst) {
            return $this->backWith('error', 'Esa institución no existe.');
        }
        // Desde la consola solo se cambian el nombre y el plan; NIT, ciudad, zona horaria y contacto los edita el colegio
        // en Ajustes › Institución (así no hay dos lugares que editan lo mismo).
        $in  = $this->postFields(['name', 'plan']);
        $err = $this->validInstitution($in, false);
        if ($err) {
            return $this->backWith('error', $err[0]);
        }
        model(InstitutionModel::class)->update($id, ['name' => $in['name'], 'plan' => $in['plan']]);
        $changes = [];
        foreach (['name', 'plan'] as $k) {
            if ((string) $inst[$k] !== (string) $in[$k]) {
                $changes[$k] = $inst[$k] . ' → ' . $in[$k];
            }
        }
        if ($changes) {
            service('audit')->log('platform.institution.update', $changes, $id);
        }

        return redirect()->to('plataforma/instituciones')->with('toast', ['type' => 'ok', 'msg' => 'Institución actualizada.']);
    }

    public function setStatus(int $id)
    {
        $inst   = model(InstitutionModel::class)->find($id);
        $status = (string) $this->request->getPost('status');
        if (! $inst || ! isset(self::STATUSES[$status])) {
            return $this->backWith('error', 'Datos no válidos.');
        }
        model(InstitutionModel::class)->update($id, ['status' => $status]);
        service('audit')->log('platform.institution.status', ['institución' => $inst['name'], 'estado' => self::STATUSES[$inst['status']] . ' → ' . self::STATUSES[$status]], $id);
        $msg = $status === 'suspended' ? 'Institución suspendida: nadie puede entrar hasta reactivarla. Sus datos se conservan.' : 'Institución reactivada.';

        return redirect()->to('plataforma/instituciones')->with('toast', ['type' => 'ok', 'msg' => $msg]);
    }

    /* ---------- Planes ---------- */

    public function plansPage()
    {
        $plans = array_values($this->plans());
        $count = array_column($this->db()->table('institutions')->select('plan, COUNT(*) AS n', false)->where('deleted_at', null)->groupBy('plan')->get()->getResultArray(), 'n', 'plan');
        foreach ($plans as &$p) {
            $p['institutions'] = (int) ($count[$p['name']] ?? 0);
        }

        return view('platform/plans', $this->ctx(['title' => 'Planes', 'plans' => $plans]));
    }

    public function savePlan(?int $id = null)
    {
        $in   = $this->postFields(['name', 'price', 'max_users', 'storage_gb', 'monthly_tokens', 'advanced_model', 'sort_order']);
        $name = $in['name'];
        if ($name === '' || mb_strlen($name) > 40) {
            return $this->backWith('error', 'Escriba el nombre del plan (hasta 40 caracteres).');
        }
        $dup = $this->db()->table('plans')->where('name', $name);
        if ($id) {
            $dup->where('id !=', $id);
        }
        if ($dup->countAllResults() > 0) {
            return $this->backWith('error', 'Ya existe un plan con ese nombre.');
        }
        $data = ['name' => $name, 'price' => $in['price'] === '' ? null : max(0, (float) str_replace(',', '.', $in['price'])),
            'max_users' => max(0, (int) $in['max_users']), 'storage_gb' => max(0, (int) $in['storage_gb']), 'monthly_tokens' => max(0, (int) $in['monthly_tokens']),
            'advanced_model' => 1, 'sort_order' => (int) $in['sort_order'], 'updated_at' => date('Y-m-d H:i:s')]; // advanced_model: todos los planes tienen ambos niveles
        if ($id) {
            $old = $this->db()->table('plans')->where('id', $id)->get()->getRowArray();
            if (! $old) {
                return $this->backWith('error', 'Ese plan no existe.');
            }
            $this->db()->table('plans')->where('id', $id)->update($data);
            if ($old['name'] !== $name) { // las instituciones guardan el nombre del plan
                $this->db()->table('institutions')->where('plan', $old['name'])->update(['plan' => $name]);
            }
        } else {
            $this->db()->table('plans')->insert($data + ['created_at' => date('Y-m-d H:i:s')]);
        }
        service('audit')->log('platform.plan.save', ['plan' => $name, 'tokens' => $data['monthly_tokens'], 'usuarios' => $data['max_users']]);

        return redirect()->to('plataforma/planes')->with('toast', ['type' => 'ok', 'msg' => 'Plan guardado. Rige desde la próxima consulta de cada institución.']);
    }

    public function deletePlan(int $id)
    {
        $p = $this->db()->table('plans')->where('id', $id)->get()->getRowArray();
        if (! $p) {
            return $this->backWith('error', 'Ese plan no existe.');
        }
        if ($this->db()->table('institutions')->where('plan', $p['name'])->where('deleted_at', null)->countAllResults() > 0) {
            return $this->backWith('error', 'No se puede eliminar: hay instituciones con este plan. Cámbielas de plan primero.');
        }
        $this->db()->table('plans')->where('id', $id)->delete();
        service('audit')->log('platform.plan.delete', ['plan' => $p['name']]);

        return redirect()->to('plataforma/planes')->with('toast', ['type' => 'ok', 'msg' => 'Plan eliminado.']);
    }

    /* ---------- Consumo de IA ---------- */

    private function usageData(): array
    {
        $from  = date('Y-m-01 00:00:00');
        $plans = $this->plans();
        $ai    = $this->aiByInstitution();
        $rows  = [];
        foreach ($this->db()->table('institutions')->where('deleted_at', null)->orderBy('name')->get()->getResultArray() as $i) {
            $u      = $ai[$i['id']] ?? ['tokens' => 0, 'cost' => 0, 'calls' => 0, 'failed' => 0];
            $limit  = (int) ($plans[$i['plan']]['monthly_tokens'] ?? 0);
            $rows[] = ['id' => (int) $i['id'], 'name' => $i['name'], 'plan' => $i['plan'], 'status' => $i['status'], 'tokens' => (int) $u['tokens'], 'cost' => (float) $u['cost'],
                'calls' => (int) $u['calls'], 'failed' => (int) $u['failed'], 'limit' => $limit, 'pct' => $limit ? (int) round(service('aiQuota')->used((int) $i['id'], 'month') * 100 / $limit) : null];
        }
        $models   = $this->db()->table('ai_usage')->select("model, SUM(tokens) AS tokens, SUM(cost_usd) AS cost, COUNT(*) AS calls", false)
            ->where('created_at >=', $from)->where('status', 'done')->groupBy('model')->orderBy('tokens', 'DESC')->get()->getResultArray();
        $features = $this->db()->table('ai_usage')->select("feature, SUM(tokens) AS tokens, COUNT(*) AS calls", false)
            ->where('created_at >=', $from)->where('status', 'done')->groupBy('feature')->orderBy('tokens', 'DESC')->get()->getResultArray();
        $months = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return [
            'month'    => ucfirst($months[(int) date('n')]) . ' de ' . date('Y'),
            'rows'     => $rows, 'models' => $models, 'features' => $features,
            'totals'   => ['tokens' => array_sum(array_column($rows, 'tokens')), 'cost' => array_sum(array_column($rows, 'cost')), 'calls' => array_sum(array_column($rows, 'calls')),
                'failed' => array_sum(array_column($rows, 'failed')), 'active' => count(array_filter($rows, static fn ($r) => $r['tokens'] > 0)), 'all' => count($rows)],
        ];
    }

    public function usage()
    {
        return view('platform/usage', $this->ctx(['title' => 'Inteligencia artificial', 'u' => $this->usageData(),
            'ai' => ['available' => service('ai')->available(), 'simulated' => service('ai')->simulated(), 'cfg' => config('Ai'), 'key' => (new \App\Libraries\Ai\KeyStore())->describe()]]));
    }

    public function usageCsv()
    {
        $rows = array_map(static fn ($r) => [$r['name'], $r['plan'], $r['status'], $r['tokens'], $r['calls'], $r['failed'],
            str_replace('.', ',', number_format($r['cost'], 4, '.', '')), $r['limit'] ?: '', $r['pct'] ?? ''], $this->usageData()['rows']);

        return $this->csvResponse('consumo-ia-' . date('Y-m') . '.csv', ['Institución', 'Plan', 'Estado', 'Tokens', 'Llamadas', 'Fallidas', 'Costo USD', 'Cupo del plan', '% usado'], $rows);
    }

    /* ---------- Auditoría ---------- */

    /** Tipos de acción del filtro: etiqueta y prefijos de `action` que agrupa. */
    private const AUDIT_TYPES = [
        'acceso'     => ['Accesos e inicios de sesión', ['auth.', 'superadmin.', 'network.enter']],
        'plataforma' => ['Instituciones y planes', ['platform.']],
        'ia'         => ['Ajustes y cupos de IA', ['ai.']],
        'personas'   => ['Personas y acudientes', ['users.', 'guardian.', 'institution.']],
        'contenido'  => ['Bancos de preguntas', ['bank.']],
        'redes'      => ['Redes educativas', ['network.save', 'network.delete', 'network.member.']],
    ];

    private const AUDIT_PAGE = 50;

    /** Filtros de la auditoría desde la URL (validados). */
    private function auditFilters(): array
    {
        $g    = fn (string $k): string => trim((string) $this->request->getGet($k));
        $date = static fn (string $d): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? $d : '';
        $inst = $g('institucion');

        return [
            'q'           => mb_substr($g('q'), 0, 100),
            'institucion' => ctype_digit($inst) || $inst === 'plataforma' ? $inst : '',
            'tipo'        => isset(self::AUDIT_TYPES[$g('tipo')]) ? $g('tipo') : '',
            'desde'       => $date($g('desde')),
            'hasta'       => $date($g('hasta')),
        ];
    }

    /** Consulta filtrada sobre audit_logs (sin orden ni límite). */
    private function auditQuery(array $f)
    {
        $q = $this->db()->table('audit_logs a')
            ->join('users u', 'u.id = a.user_id', 'left')->join('institutions i', 'i.id = a.institution_id', 'left');
        if ($f['q'] !== '') {
            $q->groupStart()->like('u.name', $f['q'])->orLike('u.email', $f['q'])->orLike('a.detail', $f['q'])->orLike('a.ip_address', $f['q'])->groupEnd();
        }
        if ($f['institucion'] === 'plataforma') {
            $q->where('a.institution_id', null);
        } elseif ($f['institucion'] !== '') {
            $q->where('a.institution_id', (int) $f['institucion']);
        }
        if ($f['tipo'] !== '') {
            $q->groupStart();
            foreach (self::AUDIT_TYPES[$f['tipo']][1] as $n => $prefix) {
                $n ? $q->orLike('a.action', $prefix, 'after') : $q->like('a.action', $prefix, 'after');
            }
            $q->groupEnd();
        }
        if ($f['desde'] !== '') {
            $q->where('a.created_at >=', $f['desde'] . ' 00:00:00');
        }
        if ($f['hasta'] !== '') {
            $q->where('a.created_at <=', $f['hasta'] . ' 23:59:59');
        }

        return $q;
    }

    /** Filas de la auditoría listas para mostrar o exportar. */
    private function auditRows(array $f, int $limit, int $offset = 0): array
    {
        $rows = $this->auditQuery($f)
            ->select("a.*, u.name AS who, u.email, u.is_superadmin, i.name AS place,
                (SELECT r.name FROM institution_users m JOIN roles r ON r.id = m.role_id WHERE m.user_id = a.user_id AND m.institution_id = a.institution_id LIMIT 1) AS role", false)
            ->orderBy('a.id', 'DESC')->limit($limit, $offset)->get()->getResultArray();

        return array_map(static function (array $r): array {
            $a    = $r['action'];
            $tone = match (true) {
                in_array($a, ['superadmin.enter', 'network.enter'], true)            => 'accent',
                (bool) preg_match('/\.(delete|revoke|unlink|status)$/', $a)          => 'danger',
                str_starts_with($a, 'platform.') || str_starts_with($a, 'ai.grant') => 'warn',
                default                                                              => 'neutral',
            };
            $detail = json_decode((string) $r['detail'], true);
            $detail = is_array($detail)
                ? implode(' · ', array_map(static fn ($k, $v) => (is_int($k) ? '' : $k . ': ') . (is_array($v) ? implode(', ', array_map(static fn ($x) => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE), $v)) : (string) $v), array_keys($detail), $detail))
                : (string) $r['detail'];

            return [
                'when'   => $r['created_at'], 'who' => $r['who'] ?? 'Sistema', 'email' => (string) $r['email'],
                'role'   => $r['is_superadmin'] ? 'Superadministrador' : ($r['role'] ?? '—'),
                'where'  => $r['place'] ?? ($r['institution_id'] ? 'Institución eliminada' : 'Plataforma'),
                'action' => audit_label($a), 'code' => $a, 'tone' => $tone, 'detail' => $detail, 'ip' => (string) $r['ip_address'],
            ];
        }, $rows);
    }

    public function audit()
    {
        $f     = $this->auditFilters();
        $total = $this->auditQuery($f)->countAllResults();
        $pages = max(1, (int) ceil($total / self::AUDIT_PAGE));
        $page  = min($pages, max(1, (int) $this->request->getGet('pagina')));

        return view('platform/audit', $this->ctx([
            'title' => 'Auditoría', 'filters' => $f, 'types' => array_map(static fn ($t) => $t[0], self::AUDIT_TYPES),
            'log'   => $this->auditRows($f, self::AUDIT_PAGE, ($page - 1) * self::AUDIT_PAGE),
            'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => self::AUDIT_PAGE,
            'insts' => $this->db()->table('institutions')->select('id, name')->orderBy('name')->get()->getResultArray(),
        ]));
    }

    /** Exporta la auditoría con los mismos filtros de la pantalla (hasta 10 000 eventos). La exportación también queda auditada. */
    public function auditCsv()
    {
        $f   = $this->auditFilters();
        $rows = array_map(static fn ($r) => [$r['when'], $r['who'], $r['email'], $r['role'], $r['where'], $r['action'], $r['code'], $r['detail'], $r['ip']], $this->auditRows($f, 10000));
        service('audit')->log('platform.audit.export', array_filter($f), null);

        return $this->csvResponse('auditoria-' . date('Y-m-d') . '.csv', ['Fecha', 'Persona', 'Correo', 'Rol', 'Institución', 'Acción', 'Código', 'Detalle', 'IP'], $rows);
    }

    /* ---------- Configuración ---------- */

    public function config()
    {
        $rbac  = service('rbac');

        return view('platform/config', $this->ctx([
            'title'  => 'Configuración',
            'mail'   => (new \App\Libraries\Mailer())->describe(),
            'brand'  => \App\Libraries\Brand::get(),
            'roles'  => $rbac->roles(null),
            'env'    => ENVIRONMENT,
            'tz'     => config('App')->appTimezone,
            'assistant' => service('helpAssistant')->settings() + ['file' => service('helpAssistant')->avatarFile()],
            'dbState'   => $this->dbState(),
            'contact'   => (new \App\Libraries\Contact())->get(),
        ]));
    }

    /** Marca de la plataforma: color de acento y fuente (Libraries/Brand). */
    public function saveBrand()
    {
        $back   = redirect()->to('plataforma/configuracion#marca');
        $accent = (string) $this->request->getPost('accent');
        $font   = (string) $this->request->getPost('font');
        $before = \App\Libraries\Brand::get();
        if ($e = \App\Libraries\Brand::save($accent, $font)) {
            return $back->with('toast', ['type' => 'error', 'msg' => $e]);
        }
        service('audit')->log('platform.brand.update', ['color' => $before['accent'] . ' → ' . strtoupper($accent), 'fuente' => $before['font'] . ' → ' . $font], null);

        return $back->with('toast', ['type' => 'ok', 'msg' => 'Marca guardada. Se aplica en toda la plataforma.']);
    }

    /* ---------- Correo saliente ---------- */

    private function passwordOk(): bool
    {
        return password_verify((string) $this->request->getPost('password'), (string) service('auth')->user()['password_hash']);
    }

    /** Guarda el servidor SMTP. Pide la contraseña del superadministrador; la del correo se guarda cifrada. */
    public function saveMail()
    {
        $back = redirect()->to('plataforma/configuracion#correo');
        if (! $this->passwordOk()) {
            service('audit')->log('platform.mail.denied', ['motivo' => 'contraseña incorrecta'], null);

            return $back->withInput()->with('toast', ['type' => 'error', 'msg' => 'Su contraseña no es correcta. No se guardó nada.']);
        }
        $in = $this->postFields(['host', 'port', 'crypto', 'user', 'from', 'from_name']);
        $in['crypto'] = (string) $this->request->getPost('crypto'); // puede venir vacío («sin cifrado»)
        if ($e = (new \App\Libraries\Mailer())->save($in, (string) $this->request->getPost('smtp_pass'))) {
            return $back->withInput()->with('toast', ['type' => 'error', 'msg' => $e]);
        }
        service('audit')->log('platform.mail.update', ['servidor' => $in['host'] . ':' . $in['port'], 'remitente' => $in['from'],
            'contraseña' => (string) $this->request->getPost('smtp_pass') !== '' ? 'cambiada' : 'sin cambios'], null);

        return $back->with('toast', ['type' => 'ok', 'msg' => 'Correo saliente guardado. Envíe un correo de prueba para confirmarlo.']);
    }

    /** Envía un correo de prueba a la dirección indicada (o a la del superadministrador). */
    public function testMail()
    {
        $back = redirect()->to('plataforma/configuracion#correo');
        $to   = trim((string) $this->request->getPost('to')) ?: (string) service('auth')->user()['email'];
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $back->with('toast', ['type' => 'error', 'msg' => 'Escriba un correo válido para la prueba.']);
        }
        if (service('throttler')->check('mailtest-' . service('auth')->id(), 5, MINUTE * 10) === false) {
            return $back->with('toast', ['type' => 'error', 'msg' => 'Envió varias pruebas seguidas. Espere unos minutos.']);
        }
        $app = config('AgendaEscolar')->appName;
        $err = (new \App\Libraries\Mailer())->send($to, 'Prueba de correo · ' . $app,
            "Hola:\n\nEste es un correo de prueba de {$app}. Si lo recibe, el correo saliente está bien configurado.\n\nEnviado el " . date('Y-m-d H:i') . '.');
        service('audit')->log('platform.mail.test', ['para' => $to, 'resultado' => $err ? 'falló' : 'enviado'], null);

        return $back->with('toast', $err ? ['type' => 'error', 'msg' => $err] : ['type' => 'ok', 'msg' => 'Correo de prueba enviado a ' . $to . '. Revise también la carpeta de spam.']);
    }

    /** Quita la configuración guardada en la consola (vuelve a usarse la del .env, si existe). */
    public function clearMail()
    {
        $back = redirect()->to('plataforma/configuracion#correo');
        if (! $this->passwordOk()) {
            return $back->with('toast', ['type' => 'error', 'msg' => 'Su contraseña no es correcta. No se quitó la configuración.']);
        }
        (new \App\Libraries\Mailer())->clear();
        service('audit')->log('platform.mail.clear', [], null);

        return $back->with('toast', ['type' => 'ok', 'msg' => 'Se quitó la configuración de correo de la consola.']);
    }

    /** Contacto comercial del botón flotante de las páginas públicas (WhatsApp y correo). */
    public function saveContact()
    {
        $back = redirect()->to('plataforma/configuracion#contacto');
        if ($e = (new \App\Libraries\Contact())->save((string) $this->request->getPost('whatsapp'), (string) $this->request->getPost('email'))) {
            return $back->with('toast', ['type' => 'error', 'msg' => $e]);
        }
        service('audit')->log('platform.contact.update', [], null);

        return $back->with('toast', ['type' => 'ok', 'msg' => t('App.contactSaved')]);
    }

    /** Estado de la base para la tarjeta «Base de datos». Si no se puede leer, la pantalla sigue funcionando. */
    private function dbState(): array
    {
        try {
            $u = new \App\Libraries\DbUpdater();

            return ['pending' => $u->pending(), 'last' => $u->last(), 'error' => null];
        } catch (\Throwable $e) {
            log_message('error', 'Estado de migraciones: ' . $e->getMessage());

            return ['pending' => [], 'last' => null, 'error' => 'No se pudo leer el estado de la base de datos.'];
        }
    }

    /**
     * «Actualizar base de datos»: aplica las migraciones pendientes (como «php spark migrate») para hosting sin
     * terminal. Exige la contraseña del superadministrador y queda en la auditoría, con éxito o con error.
     */
    public function migrate()
    {
        $back = redirect()->to('plataforma/configuracion#base-de-datos');
        if (! password_verify((string) $this->request->getPost('password'), (string) service('auth')->user()['password_hash'])) {
            service('audit')->log('platform.db.denied', ['motivo' => 'contraseña incorrecta'], null);

            return $back->with('toast', ['type' => 'error', 'msg' => 'La contraseña no es correcta. No se actualizó nada.']);
        }
        $r = (new \App\Libraries\DbUpdater())->run();
        service('audit')->log($r['ok'] ? 'platform.db.migrate' : 'platform.db.failed', array_filter([
            'aplicadas' => $r['applied'] ? implode(', ', $r['applied']) : 'ninguna pendiente', 'error' => $r['error'],
        ]), null);
        if (! $r['ok']) {
            return $back->with('toast', ['type' => 'error', 'msg' => $r['error']]);
        }

        return $back->with('toast', ['type' => 'ok', 'msg' => $r['applied']
            ? 'Base de datos actualizada: ' . plural(count($r['applied']), 'cambio aplicado', 'cambios aplicados') . '.'
            : 'La base de datos ya estaba al día.']);
    }

    /**
     * Guarda la clave de API de IA (cifrada). Exige la contraseña actual del superadministrador (confirmación
     * reforzada: una sesión robada no basta para cambiar a dónde y con qué cuenta se factura la IA), valida el formato
     * y la prueba contra el proveedor antes de guardarla. La clave nunca va a la auditoría ni a los registros.
     */
    public function saveAiKey()
    {
        $back = redirect()->to('plataforma/consumo#proveedor-ia');
        $user = service('auth')->user();
        if (! password_verify((string) $this->request->getPost('password'), (string) $user['password_hash'])) {
            service('audit')->log('platform.ai_key.denied', ['motivo' => 'contraseña incorrecta'], null);

            return $back->with('toast', ['type' => 'error', 'msg' => 'La contraseña no es correcta. La clave no se cambió.']);
        }
        $key = trim((string) $this->request->getPost('api_key'));
        if (! \App\Libraries\Ai\KeyStore::looksValid($key)) {
            return $back->with('toast', ['type' => 'error', 'msg' => 'Eso no parece una clave de Anthropic (empieza por «sk-ant-»).']);
        }
        $store = new \App\Libraries\Ai\KeyStore();
        if (($t = $store->test($key)) === 'invalid') {
            return $back->with('toast', ['type' => 'error', 'msg' => 'Anthropic rechazó la clave. Revísela en la consola de Anthropic. No se guardó.']);
        }
        $store->save($key);
        service('audit')->log('platform.ai_key.update', ['clave' => '…' . substr($key, -4), 'verificada' => $t === null ? 'sí' : 'no (sin conexión)'], null);

        return $back->with('toast', $t === null
            ? ['type' => 'ok', 'msg' => 'Clave guardada y verificada con Anthropic.']
            : ['type' => 'info', 'msg' => 'Clave guardada, pero no se pudo verificar ahora (sin conexión con Anthropic).']);
    }

    /** Quita la clave guardada en la consola; si hay ANTHROPIC_API_KEY en el .env, se vuelve a usar esa. */
    public function clearAiKey()
    {
        $user = service('auth')->user();
        if (! password_verify((string) $this->request->getPost('password'), (string) $user['password_hash'])) {
            service('audit')->log('platform.ai_key.denied', ['motivo' => 'contraseña incorrecta'], null);

            return redirect()->to('plataforma/consumo#proveedor-ia')->with('toast', ['type' => 'error', 'msg' => 'La contraseña no es correcta. La clave no se quitó.']);
        }
        (new \App\Libraries\Ai\KeyStore())->clear();
        service('audit')->log('platform.ai_key.clear', [], null);

        return redirect()->to('plataforma/consumo#proveedor-ia')->with('toast', ['type' => 'ok', 'msg' => 'Se quitó la clave guardada en la consola.']);
    }
}
