<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Bancos de preguntas de la institución. */
class QuestionBankModel extends InstitutionScopedModel
{
    protected $table          = 'question_banks';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['subject_id', 'name', 'description', 'network_shared', 'created_by', 'updated_by'];
}
