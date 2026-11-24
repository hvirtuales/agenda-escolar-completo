<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Categorías: agrupan asignaturas; su color se usa solo como acento. */
class CategoryModel extends InstitutionScopedModel
{
    protected $table         = 'categories';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'color', 'sort_order', 'status'];
}
