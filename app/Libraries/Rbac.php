<?php

namespace App\Libraries;

use App\Models\RoleModel;

/**
 * Roles y permisos.
 *
 *  - Plantillas globales (institution_id NULL): las define el superadmin; se copian al crear una institución o cuando una institución
 *    crea un rol «desde plantilla». Cambiar una plantilla no cambia los roles ya copiados.
 *  - Roles de institución: cada institución los ajusta a su medida. Cada persona tiene un solo rol por institución.
 *  - Protección: una institución nunca se queda sin al menos una persona activa con los permisos «guardianes»
 *    (usuarios y roles); así siempre hay quien pueda corregir los accesos.
 * Los métodos que modifican devuelven null si salió bien, o el mensaje de error.
 */
class Rbac
{
    protected \Config\Permissions $cfg;

    public function __construct()
    {
        $this->cfg = config('Permissions');
    }

    public function catalog(): array
    {
        return $this->cfg->groups;
    }

    /** Permisos de un rol. */
    public function permissionsOf(int $roleId): array
    {
        return array_column(db_connect()->table('role_permissions')->select('permission')->where('role_id', $roleId)
            ->get()->getResultArray(), 'permission');
    }

    /** Roles de una institución (o plantillas con null) con conteo de personas y de permisos. */
    public function roles(?int $institutionId): array
    {
        $q = db_connect()->table('roles r')
            ->select("r.*, (SELECT COUNT(*) FROM role_permissions p WHERE p.role_id = r.id) AS perms_count,
                (SELECT COUNT(*) FROM institution_users hu WHERE hu.role_id = r.id) AS users_count,
                (SELECT COUNT(*) FROM institution_users hu WHERE hu.role_id = r.id AND hu.status = 'active') AS active_users", false);
        $institutionId === null ? $q->where('r.institution_id', null) : $q->where('r.institution_id', $institutionId);

        return $q->orderBy('r.name')->get()->getResultArray();
    }

    public function find(int $roleId, ?int $institutionId): ?array
    {
        $q = model(RoleModel::class)->where('id', $roleId);
        $institutionId === null ? $q->where('institution_id', null) : $q->where('institution_id', $institutionId);

        return $q->first();
    }

    /** Filtra contra el catálogo (descarta claves desconocidas). */
    public function sanitize(array $perms): array
    {
        return array_values(array_intersect($this->cfg->all(), array_unique(array_map('strval', $perms))));
    }

    /* ------------------------------------------------------------------ */

    /** Copia todas las plantillas globales a la institución. Devuelve [template_key|id => role_id]. */
    public function copyTemplatesToInstitution(int $institutionId): array
    {
        $map = [];
        foreach ($this->roles(null) as $t) {
            $id = $this->insertRole($institutionId, $t['name'], $t['description'], $this->permissionsOf((int) $t['id']), (int) $t['id'], $t['template_key']);
            $map[$t['template_key'] ?: $t['id']] = $id;
        }
        if (! isset($map['admin'])) {
            // Si alguien borró la plantilla de administrador, la institución igual nace con un rol de acceso total.
            $map['admin'] = $this->insertRole($institutionId, 'Administrador', 'Acceso total a la institución.', $this->cfg->all(), null, 'admin');
        } elseif (! $this->isGuardianRole($map['admin'])) {
            // Quien crea la institución debe poder gestionar usuarios y roles aunque la plantilla se haya recortado.
            $this->writePermissions($map['admin'], array_merge($this->permissionsOf($map['admin']), $this->cfg->guardian));
        }

        return $map;
    }

    /** Crea las plantillas base si no hay ninguna (instalación nueva o seeder). */
    public function ensureBaseTemplates(): void
    {
        if (model(RoleModel::class)->where('institution_id', null)->countAllResults() > 0) {
            return;
        }
        foreach ($this->cfg->baseTemplates as $key => $t) {
            $this->insertRole(null, $t['name'], $t['description'], $t['permissions'] === 'all' ? $this->cfg->all() : $t['permissions'], null, $key);
        }
    }

    protected function insertRole(?int $institutionId, string $name, ?string $desc, array $perms, ?int $sourceId, ?string $key): int
    {
        $id = (int) model(RoleModel::class)->insert([
            'institution_id' => $institutionId, 'name' => $name, 'description' => $desc ?: null,
            'source_template_id' => $sourceId, 'template_key' => $key,
        ]);
        $this->writePermissions($id, $perms);

        return $id;
    }

    protected function writePermissions(int $roleId, array $perms): void
    {
        $db = db_connect();
        $db->table('role_permissions')->where('role_id', $roleId)->delete();
        $perms = $this->sanitize($perms);
        if ($perms) {
            $db->table('role_permissions')->insertBatch(array_map(static fn ($p) => ['role_id' => $roleId, 'permission' => $p], $perms));
        }
    }

    /* ------------------------------------------------------------------ */

    public function nameTaken(?int $institutionId, string $name, ?int $exceptId = null): bool
    {
        $q = model(RoleModel::class)->where('name', $name);
        $institutionId === null ? $q->where('institution_id', null) : $q->where('institution_id', $institutionId);
        if ($exceptId) {
            $q->where('id !=', $exceptId);
        }

        return $q->countAllResults() > 0;
    }

    /** Crea un rol (de institución o plantilla). $from: id de plantilla o rol a copiar (opcional, solo informativo). */
    public function create(?int $institutionId, string $name, ?string $desc, array $perms, int $userId, ?int $sourceTemplateId = null): array
    {
        if ($this->nameTaken($institutionId, $name)) {
            return ['error' => 'Ya existe un rol con ese nombre.'];
        }
        $db = db_connect();
        $db->transStart();
        $id = $this->insertRole($institutionId, $name, $desc, $perms, $sourceTemplateId, null);
        $this->audit($institutionId, $id, $name, $userId, 'created', ['permisos' => $this->sanitize($perms)]);
        $db->transComplete();

        return ['id' => $id];
    }

    public function update(int $roleId, ?int $institutionId, string $name, ?string $desc, array $perms, int $userId): ?string
    {
        $role = $this->find($roleId, $institutionId);
        if (! $role) {
            return 'Ese rol ya no existe.';
        }
        if ($this->nameTaken($institutionId, $name, $roleId)) {
            return 'Ya existe otro rol con ese nombre.';
        }
        $perms  = $this->sanitize($perms);
        $before = $this->permissionsOf($roleId);

        if ($institutionId !== null) {
            $lost = array_diff($this->cfg->guardian, $perms);
            $hasActive = db_connect()->table('institution_users')->where(['role_id' => $roleId, 'status' => 'active'])->countAllResults() > 0;
            if ($lost && $hasActive && ! array_diff($this->cfg->guardian, $before) && ! $this->guardianRemainsWithout($institutionId, $roleId)) {
                return 'Este rol es el único con permiso para gestionar usuarios y roles de la institución. Déle ese permiso a otro rol con personas activas antes de quitárselo.';
            }
        }

        $db = db_connect();
        $db->transStart();
        model(RoleModel::class)->update($roleId, ['name' => $name, 'description' => $desc ?: null]);
        $this->writePermissions($roleId, $perms);
        $added   = array_values(array_diff($perms, $before));
        $removed = array_values(array_diff($before, $perms));
        if ($added || $removed || $name !== $role['name']) {
            $this->audit($institutionId, $roleId, $name, $userId, 'updated', array_filter([
                'nombre'  => $name !== $role['name'] ? $role['name'] . ' → ' . $name : null,
                'agregó'  => $added, 'quitó' => $removed,
            ]));
        }
        $db->transComplete();

        return $db->transStatus() ? null : 'No se pudo guardar el rol. Intente de nuevo.';
    }

    public function delete(int $roleId, ?int $institutionId, int $userId): ?string
    {
        $role = $this->find($roleId, $institutionId);
        if (! $role) {
            return 'Ese rol ya no existe.';
        }
        if ($institutionId !== null && db_connect()->table('institution_users')->where('role_id', $roleId)->countAllResults() > 0) {
            return 'No se puede eliminar: hay personas con este rol. Cámbielas de rol primero (incluidas las inactivas).';
        }
        model(RoleModel::class)->delete($roleId);
        $this->audit($institutionId, null, $role['name'], $userId, 'deleted', []);

        return null;
    }

    /**
     * ¿Queda alguna persona activa con los permisos guardianes si $roleId los pierde
     * (o si la membresía $exceptMembership deja de tenerlos)?
     */
    public function guardianRemainsWithout(int $institutionId, ?int $exceptRoleId = null, ?int $exceptMembership = null): bool
    {
        $q = db_connect()->table('institution_users hu')
            ->join('users u', 'u.id = hu.user_id')
            ->where('hu.institution_id', $institutionId)->where('hu.status', 'active')->where('u.status', 'active')
            ->where(Tenant::inForce('hu'), null, false); // una asignación vencida no cuenta
        foreach ($this->cfg->guardian as $p) {
            $q->where("EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = hu.role_id AND rp.permission = " . db_connect()->escape($p) . ')', null, false);
        }
        if ($exceptRoleId) {
            $q->where('hu.role_id !=', $exceptRoleId);
        }
        if ($exceptMembership) {
            $q->where('hu.id !=', $exceptMembership);
        }

        return $q->countAllResults() > 0;
    }

    /**
     * ¿Puede la persona actual otorgar o gestionar este conjunto de permisos?
     * Regla anti-escalada: nadie (salvo el superadmin) asigna, edita ni gestiona algo con permisos que él no tiene.
     */
    public function canGrant(array $perms): bool
    {
        if (service('auth')->isSuperadmin()) {
            return true;
        }

        return array_diff($this->sanitize($perms), service('tenant')->permissions()) === [];
    }

    /** Igual que canGrant, para un rol existente. */
    public function canGrantRole(?int $roleId): bool
    {
        return $roleId !== null && $this->canGrant($this->permissionsOf($roleId));
    }

    /** ¿El rol tiene todos los permisos guardianes? */
    public function isGuardianRole(int $roleId): bool
    {
        return ! array_diff($this->cfg->guardian, $this->permissionsOf($roleId));
    }

    public function audit(?int $institutionId, ?int $roleId, string $roleName, int $userId, string $action, array $detail): void
    {
        db_connect()->table('role_audit')->insert([
            'institution_id' => $institutionId, 'role_id' => $roleId, 'role_name' => $roleName, 'user_id' => $userId,
            'action' => $action, 'detail' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function history(?int $institutionId, int $limit = 30): array
    {
        $q = db_connect()->table('role_audit a')->select('a.*, u.name AS user_name')->join('users u', 'u.id = a.user_id', 'left');
        $institutionId === null ? $q->where('a.institution_id', null) : $q->where('a.institution_id', $institutionId);

        return $q->orderBy('a.id', 'DESC')->limit($limit)->get()->getResultArray();
    }
}
