<?php

namespace App\Libraries;

/**
 * Acudientes (documento maestro 2.1). Úsela con service('guardians').
 *
 * - Un acudiente es una persona de la institución cuyo rol tiene progress.view_linked.
 * - La administración (tenant.users.manage) lo vincula a uno o varios estudiantes de la institución.
 * - El acudiente solo ve a sus estudiantes vinculados, en lectura: progreso, actividades y retroalimentación. Nunca las
 *   observaciones internas de los docentes ni datos de otros estudiantes.
 */
class Guardians
{
    public const RELATIONSHIPS = ['Madre', 'Padre', 'Acudiente', 'Abuela', 'Abuelo', 'Tía', 'Tío', 'Otro'];

    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    /** Estudiantes vinculados a un acudiente en la institución activa, con su grupo. */
    public function studentsOf(int $guardianId): array
    {
        $period = service('academic')->currentPeriod();

        return $this->db()->table('guardian_links gl')
            ->select('u.id, u.name, gl.relationship, (SELECT g.name FROM class_group_members m JOIN class_groups g ON g.id = m.group_id WHERE m.user_id = u.id AND m.institution_id = gl.institution_id'
                . ($period ? ' AND g.period_id = ' . (int) $period['id'] : '') . ' LIMIT 1) AS group_name', false)
            ->join('users u', 'u.id = gl.student_id')->where('gl.institution_id', $this->iid())->where('gl.guardian_id', $guardianId)
            ->orderBy('u.name')->get()->getResultArray();
    }

    public function canView(int $guardianId, int $studentId): bool
    {
        return $this->db()->table('guardian_links')->where(['institution_id' => $this->iid(), 'guardian_id' => $guardianId, 'student_id' => $studentId])->countAllResults() > 0;
    }

    /** Acudientes de un estudiante (para la ficha). */
    public function linksOf(int $studentId): array
    {
        return $this->db()->table('guardian_links gl')->select('gl.id, gl.relationship, gl.created_at, u.name, u.email, u.doc_type, u.doc_number')->join('users u', 'u.id = gl.guardian_id')
            ->where('gl.institution_id', $this->iid())->where('gl.student_id', $studentId)->orderBy('u.name')->get()->getResultArray();
    }

    /** ¿La persona es acudiente en la institución (su rol tiene progress.view_linked)? Devuelve su fila de usuario o null. */
    private function guardianByEmail(string $email): ?array
    {
        $row = $this->db()->table('users u')->select('u.id, u.name, hu.role_id')
            ->join('institution_users hu', 'hu.user_id = u.id AND hu.institution_id = ' . $this->iid())
            ->where('u.email', mb_strtolower(trim($email)))->where('u.status', 'active')->where('hu.status', 'active')->get()->getRowArray();
        if (! $row || ! $row['role_id'] || ! in_array('progress.view_linked', service('rbac')->permissionsOf((int) $row['role_id']), true)) {
            return null;
        }

        return $row;
    }

    /** Vincula. Devuelve null o el mensaje de error. */
    public function link(int $studentId, string $email, string $relationship): ?string
    {
        if (! service('tenant')->can('tenant.users.manage')) {
            return 'Solo la administración vincula acudientes.';
        }
        $isStudent = $this->db()->table('institution_users hu')->join('roles r', 'r.id = hu.role_id')
            ->where('hu.institution_id', $this->iid())->where('hu.user_id', $studentId)->where('r.template_key', 'student')->countAllResults() > 0;
        if (! $isStudent) {
            return 'Esa persona no es estudiante de la institución.';
        }
        $g = $this->guardianByEmail($email);
        if (! $g) {
            return 'No hay un acudiente con ese correo en la institución. Invítelo primero en Ajustes › Usuarios con el rol Acudiente.';
        }
        if ($this->canView((int) $g['id'], $studentId)) {
            return 'Ese acudiente ya está vinculado.';
        }
        $rel = in_array($relationship, self::RELATIONSHIPS, true) ? $relationship : 'Acudiente';
        $now = date('Y-m-d H:i:s');
        $this->db()->table('guardian_links')->insert(['institution_id' => $this->iid(), 'guardian_id' => $g['id'], 'student_id' => $studentId, 'relationship' => $rel,
            'created_by' => (int) service('auth')->id(), 'created_at' => $now, 'updated_at' => $now]);
        $student = (string) ($this->db()->table('users')->select('name')->where('id', $studentId)->get()->getRow()->name ?? '');
        service('audit')->log('guardian.link', ['acudiente' => $g['name'], 'estudiante' => $student, 'parentesco' => $rel]);
        service('notify')->guardianLinked((int) $g['id'], $studentId);

        return null;
    }

    public function unlink(int $studentId, int $linkId): ?string
    {
        if (! service('tenant')->can('tenant.users.manage')) {
            return 'Solo la administración desvincula acudientes.';
        }
        $l = $this->db()->table('guardian_links gl')->select('gl.id, u.name')->join('users u', 'u.id = gl.guardian_id')
            ->where('gl.institution_id', $this->iid())->where('gl.student_id', $studentId)->where('gl.id', $linkId)->get()->getRowArray();
        if (! $l) {
            return 'Ese vínculo no existe.';
        }
        $this->db()->table('guardian_links')->where('id', $linkId)->delete();
        service('audit')->log('guardian.unlink', ['acudiente' => $l['name']]);

        return null;
    }
}
