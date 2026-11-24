<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Entregas: una por estudiante y asignación. */
class SubmissionModel extends InstitutionScopedModel
{
    protected $table         = 'submissions';
    protected $useTimestamps = true;
    protected $allowedFields = ['assignment_id', 'user_id', 'status', 'answer', 'steps_done', 'attempt', 'submitted_at', 'is_late',
        'levels', 'grade', 'feedback', 'reviewed_by', 'reviewed_at'];
}
