<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Libraries\Academic;

/**
 * Ajustes › Estructura académica (documento maestro 3.1, 3.2 y 4.2): periodos, categorías y asignaturas, cursos y
 * grupos, sedes y oferta académica con sus docentes. Las reglas y la pertenencia viven en service('academic').
 */
class StructureController extends BaseController
{
    private const TABS = ['oferta', 'cursos', 'asignaturas', 'periodos', 'sedes'];

    public function index()
    {
        $ac     = service('academic');
        $tab    = in_array($this->request->getGet('t'), self::TABS, true) ? $this->request->getGet('t') : 'oferta';
        $period = $ac->currentPeriod();
        $level  = (int) $this->request->getGet('curso') ?: null;
        $subj   = (int) $this->request->getGet('asignatura') ?: null;

        return view('settings/structure', $this->layoutData() + [
            'title'      => 'Estructura académica',
            'section'    => 'estructura',
            'tab'        => $tab,
            'period'     => $period,
            'periods'    => $ac->periods(),
            'categories' => $ac->categories(),
            'subjects'   => $ac->subjects(),
            'levels'     => $ac->levels($period['id'] ?? null),
            'groups'     => $ac->groups($period['id'] ?? null),
            'campuses'   => $ac->campuses(),
            'offerings'  => $ac->offerings($period['id'] ?? null, $level, $subj),
            'teachers'   => $ac->teachers(),
            'filters'    => ['curso' => $level, 'asignatura' => $subj],
        ]);
    }

    public function save(string $type, ?int $id = null)
    {
        if (! in_array($type, Academic::TYPES, true)) {
            return $this->backWith('error', 'Tipo de registro desconocido.');
        }
        $in     = $this->request->getPost();
        $errors = service('academic')->save($type, $in, $id);
        if ($errors) {
            return $this->reopen($type, $id, $in, $errors);
        }

        return $this->done($type, $id === null ? 'creado' : 'guardado');
    }

    public function delete(string $type, int $id)
    {
        if (! in_array($type, Academic::TYPES, true)) {
            return $this->backWith('error', 'Tipo de registro desconocido.');
        }
        if ($errors = service('academic')->delete($type, $id)) {
            return $this->backWith('error', $errors['_'] ?? reset($errors));
        }

        return $this->done($type, 'eliminado');
    }

    /** Grupo: estudiantes matriculados y matrícula de nuevos. */
    public function group(int $groupId)
    {
        $ac    = service('academic');
        $group = $ac->group($groupId);
        if (! $group) {
            return redirect()->to('ajustes/estructura?t=cursos')->with('toast', ['type' => 'error', 'msg' => 'Ese grupo ya no existe en esta institución.']);
        }

        return view('settings/group', $this->layoutData() + [
            'title'     => 'Grupo ' . $group['name'],
            'section'   => 'estructura',
            'group'     => $group,
            'students'  => $ac->groupStudents($groupId),
            'available' => $ac->availableStudents((int) $group['period_id']),
            'offerings' => array_values(array_filter($ac->offerings((int) $group['period_id']), static fn ($o) => (int) $o['group_id'] === $groupId)),
        ]);
    }

    public function enroll(int $groupId)
    {
        $ids = (array) $this->request->getPost('user_ids');
        if ($errors = service('academic')->enroll($groupId, $ids)) {
            return $this->backWith('error', $errors['_'] ?? reset($errors));
        }

        return redirect()->to('ajustes/estructura/grupos/' . $groupId)->with('toast', ['type' => 'ok', 'msg' => plural(count($ids), 'estudiante matriculado', 'estudiantes matriculados') . '.']);
    }

    public function unenroll(int $groupId, int $userId)
    {
        if ($errors = service('academic')->unenroll($groupId, $userId)) {
            return $this->backWith('error', $errors['_'] ?? reset($errors));
        }

        return redirect()->to('ajustes/estructura/grupos/' . $groupId)->with('toast', ['type' => 'ok', 'msg' => 'Estudiante retirado del grupo.']);
    }

    public function teachers(int $offeringId)
    {
        if ($errors = service('academic')->setTeachers($offeringId, (array) $this->request->getPost('user_ids'))) {
            return $this->backWith('error', $errors['_'] ?? reset($errors));
        }

        return redirect()->to(site_url('ajustes/estructura?t=oferta'))->with('toast', ['type' => 'ok', 'msg' => 'Docentes de la oferta actualizados.']);
    }

    /* ---------- Ayudas ---------- */

    /** tipo => [nombre, pestaña, femenino] */
    private const LABELS = ['periodo' => ['Periodo', 'periodos', false], 'categoria' => ['Categoría', 'asignaturas', true], 'asignatura' => ['Asignatura', 'asignaturas', true],
        'curso' => ['Curso', 'cursos', false], 'grupo' => ['Grupo', 'cursos', false], 'oferta' => ['Oferta', 'oferta', true], 'sede' => ['Sede', 'sedes', true]];

    /** $what: creado | guardado | eliminado (concuerda en género con el tipo). */
    private function done(string $type, string $what)
    {
        [$label, $tab, $fem] = self::LABELS[$type];
        $word = $fem ? substr($what, 0, -1) . 'a' : $what;

        return redirect()->to(site_url('ajustes/estructura?t=' . $tab))->with('toast', ['type' => 'ok', 'msg' => $label . ' ' . $word . '.']);
    }

    /** Vuelve con los errores y reabre el Drawer con lo escrito. */
    private function reopen(string $type, ?int $id, array $in, array $errors)
    {
        unset($in['csrf_test_name']);
        if (isset($errors['_'])) {
            return $this->backWith('error', $errors['_']);
        }

        return redirect()->back()->with('errors', $errors)->with('reopen', ['type' => $type, 'mode' => $id ? 'edit' : 'create', 'form' => $in + ['id' => $id]]);
    }
}
