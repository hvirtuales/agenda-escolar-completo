<?php

namespace App\Controllers;

/**
 * Docentes (coordinación y rectoría; filtro can:academic.structure.manage en Routes): listado del periodo con
 * indicadores de carga y retroalimentación pendiente, y la ficha de cada docente.
 */
class TeachersController extends BaseController
{
    public function index()
    {
        $all    = service('teachers')->list();
        $q      = trim((string) $this->request->getGet('q'));
        $filter = (string) $this->request->getGet('ver');
        $list   = array_values(array_filter($all, static fn ($t) => ($q === '' || mb_stripos($t['name'], $q) !== false
                || array_filter($t['offerings'], static fn ($o) => mb_stripos($o['subject'] . ' ' . $o['group_name'], $q) !== false))
            && ($filter !== 'alertas' || $t['alerts'])));

        return view('teachers/index', $this->layoutData() + [
            'title' => 'Docentes', 'list' => $list, 'q' => $q, 'filter' => $filter,
            'kpi'   => [
                'teachers'  => count($all),
                'to_review' => array_sum(array_column($all, 'to_review')),
                'late'      => count(array_filter($all, static fn ($t) => ($d = \App\Libraries\Teachers::daysSince($t['oldest'])) !== null && $d >= \App\Libraries\Teachers::LATE_REVIEW_DAYS)),
                'alerts'    => count(array_filter($all, static fn ($t) => $t['alerts'])),
                'published' => array_sum(array_column($all, 'published')),
            ],
        ]);
    }

    public function show(int $id)
    {
        $t = service('teachers')->profile($id);
        if (! $t) {
            return redirect()->to('docentes')->with('toast', ['type' => 'error', 'msg' => 'Esa persona no es docente de esta institución.']);
        }

        return view('teachers/show', $this->layoutData() + ['title' => $t['name'], 't' => $t]);
    }
}
