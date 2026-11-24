<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Roles. institution_id NULL = plantilla global. No extiende InstitutionScopedModel porque las plantillas no son de una
 * institución: cada consulta filtra explícitamente por institution_id (ver App\Libraries\Rbac).
 */
class RoleModel extends Model
{
    protected $table         = 'roles';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['institution_id', 'template_key', 'source_template_id', 'name', 'description'];
}
