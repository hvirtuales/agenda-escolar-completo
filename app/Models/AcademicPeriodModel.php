<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Periodos académicos (año lectivo, semestre…). */
class AcademicPeriodModel extends InstitutionScopedModel
{
    protected $table         = 'academic_periods';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'starts_on', 'ends_on', 'is_current'];
}
