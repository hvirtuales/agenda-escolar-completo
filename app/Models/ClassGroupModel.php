<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Grupos: estudiantes de un curso en un periodo (p. ej. 7.º A, 2026). */
class ClassGroupModel extends InstitutionScopedModel
{
    protected $table         = 'class_groups';
    protected $useTimestamps = true;
    protected $allowedFields = ['level_id', 'period_id', 'campus_id', 'name'];
}
