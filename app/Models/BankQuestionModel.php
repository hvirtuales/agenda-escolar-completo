<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Preguntas de un banco. Mismo formato que QuestionModel más la dificultad. */
class BankQuestionModel extends InstitutionScopedModel
{
    protected $table         = 'bank_questions';
    protected $useTimestamps = true;
    protected $allowedFields = ['bank_id', 'type', 'text', 'options', 'correct', 'points', 'topic', 'difficulty', 'sort_order'];
}
