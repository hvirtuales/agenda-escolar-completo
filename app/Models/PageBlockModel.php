<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Bloques de una página; el contenido va en data (JSON en TEXT). */
class PageBlockModel extends InstitutionScopedModel
{
    protected $table         = 'page_blocks';
    protected $useTimestamps = true;
    protected $allowedFields = ['page_id', 'type', 'data', 'sort_order'];
}
