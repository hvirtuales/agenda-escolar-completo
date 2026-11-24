<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Intentos de evaluación de cada estudiante. */
class AttemptModel extends InstitutionScopedModel
{
    protected $table         = 'attempts';
    protected $useTimestamps = true;
    protected $allowedFields = ['assignment_id', 'user_id', 'number', 'status', 'answers', 'manual', 'auto_score', 'score', 'max_score', 'grade',
        'started_at', 'finished_at', 'over_time', 'feedback', 'reviewed_by'];
}
