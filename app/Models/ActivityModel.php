<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Actividades (retos). Pasos, pistas y rúbrica van como JSON en TEXT. */
class ActivityModel extends InstitutionScopedModel
{
    protected $table          = 'activities';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['offering_id', 'kind', 'title', 'objective', 'instructions', 'deliverable', 'steps', 'hints', 'rubric',
        'difficulty', 'mode', 'ai_mode', 'points', 'retries', 'time_limit', 'max_attempts', 'shuffle', 'results_mode', 'status', 'created_by', 'updated_by'];
}
