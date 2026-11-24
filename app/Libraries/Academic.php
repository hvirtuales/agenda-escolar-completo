<?php

namespace App\Libraries;

use App\Models\AcademicPeriodModel;
use App\Models\CampusModel;
use App\Models\CategoryModel;
use App\Models\ClassGroupModel;
use App\Models\LevelModel;
use App\Models\SubjectModel;
use App\Models\SubjectOfferingModel;
use App\Models\TeachingAssignmentModel;

/**
 * Estructura académica de la institución activa (documento maestro 3.1, 3.2 y 4.2). Úsela con service('academic').
 *
 * Todas las lecturas y escrituras pasan por modelos InstitutionScopedModel. Además, antes de guardar se comprueba que
 * cada registro relacionado (categoría, curso, periodo, sede, grupo, asignatura, docente) es de la misma institución:
 * un id cambiado en el formulario no puede enlazar datos de otra institución.
 * Los métodos que modifican devuelven null si salió bien, o un arreglo campo => mensaje.
 */
class Academic
{
    public const TYPES = ['periodo', 'categoria', 'asignatura', 'curso', 'grupo', 'oferta', 'sede'];

    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    /* ---------- Lecturas ---------- */

    public function periods(): array
    {
        return model(AcademicPeriodModel::class)->orderBy('starts_on', 'DESC')->findAll();
    }

    /** Periodo en curso (memorizado por petición). */
    public function currentPeriod(): ?array
    {
        if (! $this->periodLoaded) {
            $this->periodLoaded = true;
            $this->period       = model(AcademicPeriodModel::class)->where('is_current', 1)->first()
                ?? model(AcademicPeriodModel::class)->orderBy('starts_on', 'DESC')->first();
        }

        return $this->period;
    }

    private ?array $period      = null;
    private bool $periodLoaded  = false;
    private ?array $myCatalog   = null;

    /**
     * Catálogo de asignaturas de quien navega como docente: coordinación (academic.structure.manage) ve todas las del
     * periodo; el docente, las que dicta. Memorizado por petición. Úselo en lugar de calcular el modo en cada sitio.
     */
    public function myCatalog(): array
    {
        return $this->myCatalog ??= $this->catalogFor((int) service('auth')->id(), service('tenant')->can('academic.structure.manage') ? 'all' : 'teacher');
    }

    /** Lo mismo que myCatalog(), como lista plana de ofertas. */
    public function myOfferings(): array
    {
        return self::flatten($this->myCatalog());
    }

    /** Aplana un catálogo agrupado por categoría (catalogFor) en una lista de ofertas. */
    public static function flatten(array $catalog): array
    {
        return array_merge(...array_column($catalog, 'items') ?: [[]]);
    }

    public function campuses(): array
    {
        return model(CampusModel::class)->orderBy('name')->findAll();
    }

    public function categories(): array
    {
        return model(CategoryModel::class)->orderBy('sort_order')->orderBy('name')->findAll();
    }

    /** Asignaturas con su categoría y número de ofertas. */
    public function subjects(): array
    {
        return $this->db()->table('subjects s')
            ->select('s.*, c.name AS category, c.color, (SELECT COUNT(*) FROM subject_offerings o WHERE o.subject_id = s.id) AS offerings')
            ->join('categories c', 'c.id = s.category_id')
            ->where('s.institution_id', $this->iid())
            ->orderBy('c.sort_order')->orderBy('c.name')->orderBy('s.name')->get()->getResultArray();
    }

    /** Cursos con sus grupos del periodo indicado y el número de estudiantes. */
    public function levels(?int $periodId): array
    {
        $levels = model(LevelModel::class)->orderBy('sort_order')->orderBy('name')->findAll();
        $groups = $this->db()->table('class_groups g')
            ->select('g.*, cp.name AS campus, (SELECT COUNT(*) FROM class_group_members m WHERE m.group_id = g.id) AS students')
            ->join('campuses cp', 'cp.id = g.campus_id', 'left')
            ->where('g.institution_id', $this->iid())
            ->where('g.period_id', (int) $periodId)
            ->orderBy('g.name')->get()->getResultArray();
        foreach ($levels as &$l) {
            $l['groups'] = array_values(array_filter($groups, static fn ($g) => (int) $g['level_id'] === (int) $l['id']));
        }

        return $levels;
    }

    /** Grupos de un periodo, para listas desplegables. */
    public function groups(?int $periodId): array
    {
        return $this->db()->table('class_groups g')->select('g.id, g.name, l.name AS level')
            ->join('levels l', 'l.id = g.level_id')
            ->where('g.institution_id', $this->iid())->where('g.period_id', (int) $periodId)
            ->orderBy('l.sort_order')->orderBy('g.name')->get()->getResultArray();
    }

    /** Oferta académica del periodo con asignatura, grupo y docentes. */
    public function offerings(?int $periodId, ?int $levelId = null, ?int $subjectId = null): array
    {
        $q = $this->db()->table('subject_offerings o')
            ->select('o.*, s.name AS subject, c.color, g.name AS group_name, l.name AS level, l.id AS level_id')
            ->join('subjects s', 's.id = o.subject_id')
            ->join('categories c', 'c.id = s.category_id')
            ->join('class_groups g', 'g.id = o.group_id')
            ->join('levels l', 'l.id = g.level_id')
            ->where('o.institution_id', $this->iid())
            ->where('o.period_id', (int) $periodId);
        if ($levelId) {
            $q->where('l.id', $levelId);
        }
        if ($subjectId) {
            $q->where('s.id', $subjectId);
        }
        $rows = $q->orderBy('l.sort_order')->orderBy('g.name')->orderBy('s.name')->get()->getResultArray();

        $teachers = [];
        if ($rows) {
            foreach ($this->db()->table('teaching_assignments t')->select('t.offering_id, u.id, u.name')
                ->join('users u', 'u.id = t.user_id')->where('t.institution_id', $this->iid())
                ->whereIn('t.offering_id', array_column($rows, 'id'))->orderBy('u.name')->get()->getResultArray() as $t) {
                $teachers[$t['offering_id']][] = ['id' => (int) $t['id'], 'name' => $t['name']];
            }
        }
        foreach ($rows as &$r) {
            $r['teachers'] = $teachers[$r['id']] ?? [];
        }

        return $rows;
    }

    /** Personas activas de la institución que pueden dictar (su rol tiene content.edit). */
    public function teachers(): array
    {
        return $this->db()->table('institution_users m')->select('u.id, u.name')
            ->join('users u', 'u.id = m.user_id')
            ->where('m.institution_id', $this->iid())->where('m.status', 'active')->where('u.status', 'active')
            ->where("EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = m.role_id AND rp.permission = 'content.edit')", null, false)
            ->orderBy('u.name')->get()->getResultArray();
    }

    /** Un grupo con su curso, periodo y sede, o null si no es de esta institución. */
    public function group(int $groupId): ?array
    {
        return $this->db()->table('class_groups g')
            ->select('g.*, l.name AS level, p.name AS period, cp.name AS campus')
            ->join('levels l', 'l.id = g.level_id')->join('academic_periods p', 'p.id = g.period_id')
            ->join('campuses cp', 'cp.id = g.campus_id', 'left')
            ->where('g.institution_id', $this->iid())->where('g.id', $groupId)->get()->getRowArray();
    }

    /** Estudiantes matriculados en un grupo. */
    public function groupStudents(int $groupId): array
    {
        return $this->db()->table('class_group_members m')->select('m.id AS member_id, u.id, u.name, u.email, u.doc_type, u.doc_number')
            ->join('users u', 'u.id = m.user_id')
            ->where('m.institution_id', $this->iid())->where('m.group_id', $groupId)
            ->orderBy('u.name')->get()->getResultArray();
    }

    /** Condición: el rol de la membresía es de estudiante (resuelve actividades y no edita contenido). */
    private function studentRoleSql(string $alias = 'm'): string
    {
        return "EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = {$alias}.role_id AND rp.permission = 'activities.submit')"
            . " AND NOT EXISTS (SELECT 1 FROM role_permissions rp2 WHERE rp2.role_id = {$alias}.role_id AND rp2.permission = 'content.edit')";
    }

    /** Estudiantes activos de la institución que aún no están en ningún grupo de ese periodo. */
    public function availableStudents(int $periodId): array
    {
        $iid = $this->iid();

        return $this->db()->table('institution_users m')->select('u.id, u.name, u.email, u.doc_type, u.doc_number')
            ->join('users u', 'u.id = m.user_id')
            ->where('m.institution_id', $iid)->where('m.status', 'active')->where('u.status', 'active')
            ->where($this->studentRoleSql('m'), null, false)
            ->where("NOT EXISTS (SELECT 1 FROM class_group_members cm JOIN class_groups g ON g.id = cm.group_id
                WHERE cm.user_id = u.id AND cm.institution_id = {$iid} AND g.period_id = " . (int) $periodId . ')', null, false)
            ->orderBy('u.name')->get()->getResultArray();
    }

    /** Matricula estudiantes en un grupo (solo estudiantes de la institución sin grupo en ese periodo). */
    public function enroll(int $groupId, array $userIds): ?array
    {
        $group = $this->group($groupId);
        if (! $group) {
            return ['_' => 'Ese grupo ya no existe en esta institución.'];
        }
        $allowed = array_map('intval', array_column($this->availableStudents((int) $group['period_id']), 'id'));
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return ['_' => 'Elija al menos un estudiante.'];
        }
        if (array_diff($userIds, $allowed)) {
            return ['_' => 'Solo se pueden matricular estudiantes activos de esta institución que no tengan grupo en este periodo.'];
        }
        foreach ($userIds as $u) {
            model(\App\Models\ClassGroupMemberModel::class)->insert(['group_id' => $groupId, 'user_id' => $u]);
        }

        return null;
    }

    public function unenroll(int $groupId, int $userId): ?array
    {
        if (! $this->group($groupId)) {
            return ['_' => 'Ese grupo ya no existe en esta institución.'];
        }
        $this->db()->table('class_group_members')->where(['institution_id' => $this->iid(), 'group_id' => $groupId, 'user_id' => $userId])->delete();

        return null;
    }

    /** Grupo del periodo en curso de un estudiante, o null. */
    public function studentGroup(int $userId): ?array
    {
        $period = $this->currentPeriod();
        if (! $period) {
            return null;
        }

        return $this->db()->table('class_group_members m')->select('g.id, g.name, l.name AS level')
            ->join('class_groups g', 'g.id = m.group_id')->join('levels l', 'l.id = g.level_id')
            ->where('m.institution_id', $this->iid())->where('m.user_id', $userId)->where('g.period_id', $period['id'])
            ->get()->getRowArray();
    }

    /**
     * Panel de asignaturas del cuaderno (y «Mis grupos» del inicio): ofertas del periodo en curso agrupadas por
     * categoría. Modos: 'teacher' = las que dicta la persona; 'all' = todas (coordinación); 'student' = las de su grupo;
     * 'group' = las del grupo $groupId (vista de estudiante).
     * Cada ítem: id, slug (o{id}), subject, group, color, students.
     */
    public function catalogFor(int $userId, string $mode, ?int $groupId = null): array
    {
        $period = $this->currentPeriod();
        if (! $period) {
            return [];
        }
        $q = $this->db()->table('subject_offerings o')
            ->select('o.id, s.id AS subject_id, s.name AS subject, g.name AS group_name, g.id AS group_id, c.id AS category_id, c.name AS category, c.color,
                (SELECT COUNT(*) FROM class_group_members cm WHERE cm.group_id = g.id) AS students', false)
            ->join('subjects s', 's.id = o.subject_id')->join('categories c', 'c.id = s.category_id')
            ->join('class_groups g', 'g.id = o.group_id')->join('levels l', 'l.id = g.level_id')
            ->where('o.institution_id', $this->iid())->where('o.period_id', $period['id']);
        if ($mode === 'teacher') {
            $q->where('EXISTS (SELECT 1 FROM teaching_assignments t WHERE t.offering_id = o.id AND t.user_id = ' . $userId . ')', null, false);
        } elseif ($mode === 'group') {
            $q->where('g.id', (int) $groupId);
        } elseif ($mode === 'student') {
            $q->where('EXISTS (SELECT 1 FROM class_group_members cm2 WHERE cm2.group_id = g.id AND cm2.user_id = ' . $userId . ')', null, false);
        }
        $rows = $q->orderBy('c.sort_order')->orderBy('c.name')->orderBy('s.name')->orderBy('l.sort_order')->orderBy('g.name')->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['category_id']] ??= ['name' => $r['category'], 'color' => $r['color'], 'items' => []];
            $out[$r['category_id']]['items'][] = [
                'id' => (int) $r['id'], 'slug' => 'o' . $r['id'], 'subject_id' => (int) $r['subject_id'], 'subject' => $r['subject'], 'group' => $r['group_name'], 'group_id' => (int) $r['group_id'],
                'color' => $r['color'], 'category' => $r['category'], 'students' => (int) $r['students'], 'pending' => 0,
            ];
        }

        return array_values($out);
    }

    /* ---------- Escrituras ---------- */

    /** Crea ($id null) o actualiza un registro del tipo indicado. */
    public function save(string $type, array $in, ?int $id = null): ?array
    {
        [$model, $rules, $data] = match ($type) {
            'periodo' => [model(AcademicPeriodModel::class), [
                'name' => 'required|max_length[80]', 'starts_on' => 'required|valid_date[Y-m-d]', 'ends_on' => 'required|valid_date[Y-m-d]',
            ], ['name' => $in['name'] ?? '', 'starts_on' => $in['starts_on'] ?? '', 'ends_on' => $in['ends_on'] ?? '', 'is_current' => ! empty($in['is_current']) ? 1 : 0]],
            'categoria' => [model(CategoryModel::class), ['name' => 'required|max_length[80]', 'color' => 'required|regex_match[/^#[0-9a-fA-F]{6}$/]'],
                ['name' => $in['name'] ?? '', 'color' => strtoupper($in['color'] ?? ''), 'sort_order' => (int) ($in['sort_order'] ?? 0)]],
            'asignatura' => [model(SubjectModel::class), ['name' => 'required|max_length[120]', 'category_id' => 'required|is_natural_no_zero', 'description' => 'permit_empty|max_length[255]'],
                ['name' => $in['name'] ?? '', 'category_id' => (int) ($in['category_id'] ?? 0), 'description' => ($in['description'] ?? '') ?: null]],
            'curso' => [model(LevelModel::class), ['name' => 'required|max_length[60]'], ['name' => $in['name'] ?? '', 'sort_order' => (int) ($in['sort_order'] ?? 0)]],
            'grupo' => [model(ClassGroupModel::class), ['name' => 'required|max_length[60]', 'level_id' => 'required|is_natural_no_zero', 'period_id' => 'required|is_natural_no_zero'],
                ['name' => $in['name'] ?? '', 'level_id' => (int) ($in['level_id'] ?? 0), 'period_id' => (int) ($in['period_id'] ?? 0), 'campus_id' => ((int) ($in['campus_id'] ?? 0)) ?: null]],
            'oferta' => [model(SubjectOfferingModel::class), ['subject_id' => 'required|is_natural_no_zero', 'group_id' => 'required|is_natural_no_zero', 'hours_per_week' => 'permit_empty|is_natural|less_than[41]'],
                ['subject_id' => (int) ($in['subject_id'] ?? 0), 'group_id' => (int) ($in['group_id'] ?? 0), 'hours_per_week' => (int) ($in['hours_per_week'] ?? 0)]],
            'sede' => [model(CampusModel::class), ['name' => 'required|max_length[120]', 'address' => 'permit_empty|max_length[200]'],
                ['name' => $in['name'] ?? '', 'address' => ($in['address'] ?? '') ?: null]],
        };

        $v = service('validation');
        $v->reset();
        if (! $v->setRules($rules)->run($data)) {
            return $v->getErrors();
        }
        if ($id !== null && ! $model->find($id)) {
            return ['_' => 'Ese registro ya no existe en esta institución.'];
        }
        if ($error = $this->checkRelations($type, $data, $id)) {
            return $error;
        }

        $db = $this->db();
        $db->transStart();
        if ($type === 'periodo' && $data['is_current']) {
            // Solo un periodo en curso.
            $db->table('academic_periods')->where('institution_id', $this->iid())->update(['is_current' => 0]);
        }
        if ($type === 'oferta') {
            $data['period_id'] = (int) model(ClassGroupModel::class)->find($data['group_id'])['period_id'];
        }
        $id === null ? $model->insert($data) : $model->update($id, $data);
        $db->transComplete();

        return $db->transStatus() ? null : ['_' => 'No se pudo guardar. Intente de nuevo.'];
    }

    /** Pertenencia y reglas que dependen de otros registros. */
    private function checkRelations(string $type, array $d, ?int $id): ?array
    {
        $mine = static fn (string $modelClass, ?int $rid): bool => $rid !== null && $rid > 0 && model($modelClass)->find($rid) !== null;

        switch ($type) {
            case 'periodo':
                return $d['ends_on'] < $d['starts_on'] ? ['ends_on' => 'La fecha final no puede ser anterior a la inicial.'] : null;

            case 'asignatura':
                return $mine(CategoryModel::class, $d['category_id']) ? null : ['category_id' => 'Elija una categoría de esta institución.'];

            case 'grupo':
                if (! $mine(LevelModel::class, $d['level_id'])) {
                    return ['level_id' => 'Elija un curso de esta institución.'];
                }
                if (! $mine(AcademicPeriodModel::class, $d['period_id'])) {
                    return ['period_id' => 'Elija un periodo de esta institución.'];
                }
                if ($d['campus_id'] !== null && ! $mine(CampusModel::class, $d['campus_id'])) {
                    return ['campus_id' => 'Elija una sede de esta institución.'];
                }
                $dup = model(ClassGroupModel::class)->where(['level_id' => $d['level_id'], 'period_id' => $d['period_id'], 'name' => $d['name']]);
                if ($id) {
                    $dup->where('id !=', $id);
                }

                return $dup->countAllResults() > 0 ? ['name' => 'Ya existe un grupo con ese nombre en ese curso y periodo.'] : null;

            case 'oferta':
                if (! $mine(SubjectModel::class, $d['subject_id'])) {
                    return ['subject_id' => 'Elija una asignatura de esta institución.'];
                }
                $group = model(ClassGroupModel::class)->find($d['group_id']);
                if (! $group) {
                    return ['group_id' => 'Elija un grupo de esta institución.'];
                }
                $dup = model(SubjectOfferingModel::class)->where(['subject_id' => $d['subject_id'], 'group_id' => $d['group_id'], 'period_id' => $group['period_id']]);
                if ($id) {
                    $dup->where('id !=', $id);
                }

                return $dup->countAllResults() > 0 ? ['subject_id' => 'Esa asignatura ya está ofertada para ese grupo.'] : null;
        }

        return null;
    }

    /** Elimina si no tiene dependencias; si las tiene, explica qué hacer. */
    public function delete(string $type, int $id): ?array
    {
        $db  = $this->db();
        $iid = $this->iid();
        [$model, $deps] = match ($type) {
            'periodo'    => [model(AcademicPeriodModel::class), [['class_groups', 'period_id', 'tiene grupos']]],
            'categoria'  => [model(CategoryModel::class), [['subjects', 'category_id', 'tiene asignaturas']]],
            'asignatura' => [model(SubjectModel::class), [['subject_offerings', 'subject_id', 'está ofertada en algún grupo']]],
            'curso'      => [model(LevelModel::class), [['class_groups', 'level_id', 'tiene grupos']]],
            'grupo'      => [model(ClassGroupModel::class), [['class_group_members', 'group_id', 'tiene estudiantes matriculados'], ['subject_offerings', 'group_id', 'tiene oferta académica']]],
            'oferta'     => [model(SubjectOfferingModel::class), []],
            'sede'       => [model(CampusModel::class), [['class_groups', 'campus_id', 'tiene grupos'], ['institution_users', 'campus_id', 'tiene personas asignadas']]],
        };
        if (! $model->find($id)) {
            return ['_' => 'Ese registro ya no existe en esta institución.'];
        }
        foreach ($deps as [$table, $col, $why]) {
            if ($db->table($table)->where('institution_id', $iid)->where($col, $id)->countAllResults() > 0) {
                return ['_' => 'No se puede eliminar porque ' . $why . '. Quite esa relación primero.'];
            }
        }
        $model->delete($id);

        return null;
    }

    /** Reemplaza los docentes de una oferta. Cada docente debe ser de la institución y poder dictar. */
    public function setTeachers(int $offeringId, array $userIds): ?array
    {
        if (! model(SubjectOfferingModel::class)->find($offeringId)) {
            return ['_' => 'Esa oferta ya no existe en esta institución.'];
        }
        $allowed = array_map('intval', array_column($this->teachers(), 'id'));
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (array_diff($userIds, $allowed)) {
            return ['user_ids' => 'Solo se pueden asignar docentes activos de esta institución.'];
        }
        $db = $this->db();
        $db->transStart();
        $db->table('teaching_assignments')->where('institution_id', $this->iid())->where('offering_id', $offeringId)->delete();
        foreach ($userIds as $u) {
            model(TeachingAssignmentModel::class)->insert(['offering_id' => $offeringId, 'user_id' => $u]);
        }
        $db->transComplete();

        return null;
    }
}
