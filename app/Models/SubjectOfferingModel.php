<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Oferta académica: asignatura × grupo × periodo. */
class SubjectOfferingModel extends InstitutionScopedModel
{
    protected $table         = 'subject_offerings';
    protected $useTimestamps = true;
    protected $allowedFields = ['subject_id', 'group_id', 'period_id', 'hours_per_week'];
}
