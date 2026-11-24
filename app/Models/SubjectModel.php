<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Asignaturas. */
class SubjectModel extends InstitutionScopedModel
{
    protected $table         = 'subjects';
    protected $useTimestamps = true;
    protected $allowedFields = ['category_id', 'name', 'description', 'status'];
}
