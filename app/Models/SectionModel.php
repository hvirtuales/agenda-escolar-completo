<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Secciones de un cuaderno. */
class SectionModel extends InstitutionScopedModel
{
    protected $table         = 'sections';
    protected $useTimestamps = true;
    protected $allowedFields = ['workspace_id', 'name', 'sort_order'];
}
