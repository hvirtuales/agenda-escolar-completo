<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Copias de una página al publicarla (para restaurar). */
class ContentVersionModel extends InstitutionScopedModel
{
    protected $table         = 'content_versions';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['page_id', 'snapshot', 'user_id'];
}
