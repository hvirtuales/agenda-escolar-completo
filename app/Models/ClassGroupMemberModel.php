<?php

namespace App\Models;

use App\Models\Base\InstitutionScopedModel;

/** Estudiantes matriculados en un grupo. */
class ClassGroupMemberModel extends InstitutionScopedModel
{
    protected $table         = 'class_group_members';
    protected $useTimestamps = true;
    protected $allowedFields = ['group_id', 'user_id'];
}
