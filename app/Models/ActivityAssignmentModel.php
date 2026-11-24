<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Asignación de una actividad al grupo de su oferta, con fechas. */
class ActivityAssignmentModel extends InstitutionScopedModel
{
    protected $table         = 'activity_assignments';
    protected $useTimestamps = true;
    protected $allowedFields = ['activity_id', 'group_id', 'starts_at', 'due_at', 'allow_late', 'created_by'];
}
