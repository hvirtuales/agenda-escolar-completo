<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Docentes asignados a una oferta. */
class TeachingAssignmentModel extends InstitutionScopedModel
{
    protected $table         = 'teaching_assignments';
    protected $useTimestamps = true;
    protected $allowedFields = ['offering_id', 'user_id'];
}
