<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Cursos o niveles (p. ej. 7.º). */
class LevelModel extends InstitutionScopedModel
{
    protected $table         = 'levels';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'sort_order'];
}
