<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Preguntas de una evaluación. Opciones y respuesta correcta en JSON (TEXT). */
class QuestionModel extends InstitutionScopedModel
{
    protected $table         = 'questions';
    protected $useTimestamps = true;
    protected $allowedFields = ['activity_id', 'type', 'text', 'options', 'correct', 'points', 'topic', 'sort_order'];
}
