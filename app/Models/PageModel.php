<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Páginas: unidad principal de contenido (documento maestro 3.3). */
class PageModel extends InstitutionScopedModel
{
    protected $table         = 'pages';
    protected $useTimestamps = true;
    protected $allowedFields = ['workspace_id', 'section_id', 'title', 'kind', 'status', 'objective', 'sort_order', 'published_at', 'created_by', 'updated_by'];
    protected $useSoftDeletes = true;
}
