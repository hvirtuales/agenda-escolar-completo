<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Cuadernos: uno por oferta académica (o libres). */
class WorkspaceModel extends InstitutionScopedModel
{
    protected $table         = 'workspaces';
    protected $useTimestamps = true;
    protected $allowedFields = ['offering_id', 'title', 'status'];
}
