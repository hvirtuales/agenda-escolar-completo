<?php

namespace App\Libraries;

use App\Models\InstitutionModel;

/**
 * Institución activa de la sesión (multitenant) y permisos de la persona en esa institución. Úsela con service('tenant').
 *
 * Un usuario trabaja en las instituciones donde tiene una membresía activa (institution_users), con un rol por institución.
 * El superadmin puede entrar a cualquier institución activa; si no tiene membresía, tiene todos los permisos.
 */
class Tenant
{
    protected ?array $institution = null;
    protected ?array $role  = null;   // ['id', 'name']
    protected array $perms  = [];     // permiso => true
    protected bool $loaded  = false;
    protected ?array $viaNetwork = null; // ['id', 'name'] si entra como administrador de la red del colegio

    public function institution(): ?array
    {
        if (! $this->loaded) {
            $this->loaded = true;
            $id           = (int) session('institution_id');
            if ($id && ! $this->load($id)) {
                session()->remove('institution_id');
            }
        }

        return $this->institution;
    }

    public function institutionId(): ?int
    {
        return $this->institution() ? (int) $this->institution['id'] : null;
    }

    /** ¿Tiene ALGUNO de estos permisos en la institución activa? */
    public function can(string ...$perms): bool
    {
        $this->institution();
        foreach ($perms as $p) {
            if (isset($this->perms[$p])) {
                return true;
            }
        }

        return false;
    }

    /** ¿Trabaja como estudiante en la institución activa? (resuelve actividades y no edita contenido) */
    public function isStudent(): bool
    {
        return $this->can('activities.submit') && ! $this->can('content.edit');
    }

    /** ¿Es acudiente? (ve el progreso de sus estudiantes vinculados y no ve contenido) */
    public function isGuardian(): bool
    {
        return $this->can('progress.view_linked') && ! $this->can('content.view');
    }

    /** Rol de la persona en la institución activa: ['id' => ?int, 'name' => string] o null. */
    public function role(): ?array
    {
        $this->institution();

        return $this->role;
    }

    public function permissions(): array
    {
        $this->institution();

        return array_keys($this->perms);
    }

    public function switchTo(int $institutionId): bool
    {
        $this->loaded = true;
        if (! $this->load($institutionId)) {
            return false;
        }
        session()->set('institution_id', $institutionId);
        db_connect()->table('institution_users')->where(['institution_id' => $institutionId, 'user_id' => service('auth')->id()])
            ->update(['last_access_at' => date('Y-m-d H:i:s')]);

        // La entrada del superadministrador a una institución siempre queda en la auditoría (documento maestro 7.4).
        if (service('auth')->isSuperadmin()) {
            service('audit')->log('superadmin.enter', ['institución' => $this->institution['name']], $institutionId);
        } elseif ($this->viaNetwork) {
            // También la del administrador de red que entra a un colegio donde no tiene membresía propia.
            service('audit')->log('network.enter', ['institución' => $this->institution['name'], 'red' => $this->viaNetwork['name']], $institutionId);
        }

        return true;
    }

    /** Instituciones a los que puede entrar el usuario actual, con el nombre de su rol. */
    public function availableInstitutions(): array
    {
        $auth = service('auth');
        if (! $auth->check()) {
            return [];
        }
        $db = db_connect();

        if ($auth->isSuperadmin()) {
            return $db->table('institutions h')
                ->select("h.id, h.name, h.city, h.accent_color, h.plan, h.status, COALESCE(r.name, 'Superadministrador') AS role_name", false)
                ->join('institution_users hu', 'hu.institution_id = h.id AND hu.user_id = ' . (int) $auth->id(), 'left')
                ->join('roles r', 'r.id = hu.role_id', 'left')
                ->whereIn('h.status', InstitutionModel::OPEN_STATUSES)->where('h.deleted_at', null)
                ->orderBy('h.name')->get()->getResultArray();
        }

        $mine = $db->table('institution_users hu')
            ->select('h.id, h.name, h.city, h.accent_color, h.plan, h.status, r.name AS role_name')
            ->join('institutions h', 'h.id = hu.institution_id')
            ->join('roles r', 'r.id = hu.role_id', 'left')
            ->where('hu.user_id', $auth->id())->where('hu.status', 'active')->where(self::inForce(), null, false)
            ->whereIn('h.status', InstitutionModel::OPEN_STATUSES)->where('h.deleted_at', null)
            ->orderBy('h.name')->get()->getResultArray();

        // Colegios de las redes que administra, donde no tiene membresía propia.
        $have = array_column($mine, 'id');
        $net  = $db->table('institutions h')
            ->select("h.id, h.name, h.city, h.accent_color, h.plan, h.status, 'Administrador de red' AS role_name", false)
            ->join('network_users nu', "nu.network_id = h.network_id AND nu.role = 'admin' AND nu.status = 'active'")
            ->join('networks n', 'n.id = h.network_id')
            ->where('nu.user_id', $auth->id())->where('n.status', 'active')->where('n.deleted_at', null)
            ->whereIn('h.status', InstitutionModel::OPEN_STATUSES)->where('h.deleted_at', null)->get()->getResultArray();
        $all = array_merge($mine, array_values(array_filter($net, static fn ($r) => ! in_array($r['id'], $have, false))));
        usort($all, static fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $all;
    }

    /**
     * Condición SQL de asignación vigente hoy (modelo de tenencia, 2.1): fuera de las fechas de la asignación
     * la persona no entra a la institución, aunque su membresía siga activa.
     */
    public static function inForce(string $alias = 'hu'): string
    {
        $today = db_connect()->escape(date('Y-m-d'));

        return "({$alias}.starts_on IS NULL OR {$alias}.starts_on <= {$today}) AND ({$alias}.ends_on IS NULL OR {$alias}.ends_on >= {$today})";
    }

    protected function load(int $institutionId): bool
    {
        $this->institution = null;
        $this->role  = null;
        $this->perms = [];
        $this->viaNetwork = null;
        $auth        = service('auth');

        if (! $auth->check()) {
            return false;
        }
        $institution = model(InstitutionModel::class)->whereIn('status', InstitutionModel::OPEN_STATUSES)->find($institutionId);
        if (! $institution) {
            return false;
        }

        $db         = db_connect();
        $membership = $db->table('institution_users hu')->select('hu.role_id, r.name')
            ->join('roles r', 'r.id = hu.role_id AND r.institution_id = hu.institution_id', 'left')
            ->where(['hu.institution_id' => $institutionId, 'hu.user_id' => $auth->id(), 'hu.status' => 'active'])->where(self::inForce(), null, false)
            ->get()->getRowArray();

        if ($auth->isSuperadmin()) {
            $this->role  = ['id' => $membership['role_id'] ?? null, 'name' => $membership['name'] ?? 'Superadministrador'];
            $this->perms = array_fill_keys(config('Permissions')->all(), true);
        } elseif ($net = service('network')->adminOfInstitution($institutionId)) {
            // Administrador de la red del colegio: todos los permisos (como el superadministrador, limitado a su red).
            $this->role       = ['id' => $membership['role_id'] ?? null, 'name' => 'Administrador de red'];
            $this->perms      = array_fill_keys(config('Permissions')->all(), true);
            $this->viaNetwork = $membership ? null : $net;
        } elseif ($membership && $membership['role_id'] && $membership['name'] !== null) {
            $this->role  = ['id' => (int) $membership['role_id'], 'name' => $membership['name']];
            $this->perms = array_fill_keys(service('rbac')->permissionsOf((int) $membership['role_id']), true);
        } else {
            return false;
        }

        $this->institution = $institution;

        return true;
    }
}
