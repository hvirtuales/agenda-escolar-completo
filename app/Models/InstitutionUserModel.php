<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Membresías de personas en la institución activa: rol, sede, vigencia y estado (documento maestro 2.2 y 8.1). */
class InstitutionUserModel extends InstitutionScopedModel
{
    protected $table         = 'institution_users';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id', 'role_id', 'campus_id', 'starts_on', 'ends_on', 'status', 'last_access_at'];

    /** Une la cuenta, el rol y la sede para listar. */
    public function withUsers(): static
    {
        return $this->select('institution_users.*, users.name, users.email, users.doc_type, users.doc_number, users.status AS user_status, users.is_superadmin, users.totp_enabled_at, roles.name AS role_name, roles.template_key, campuses.name AS campus_name')
            ->join('users', 'users.id = institution_users.user_id')
            ->join('roles', 'roles.id = institution_users.role_id', 'left')
            ->join('campuses', 'campuses.id = institution_users.campus_id', 'left');
    }
}
