<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Sedes de la institución. */
class CampusModel extends InstitutionScopedModel
{
    protected $table         = 'campuses';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'address', 'status'];
}
