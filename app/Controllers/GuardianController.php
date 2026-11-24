<?php

namespace App\Controllers;

/**
 * Portal del acudiente (permiso progress.view_linked): sus estudiantes vinculados y, de cada uno, progreso, actividades
 * y retroalimentación en solo lectura. La comprobación del vínculo está en Guardians.
 */
class GuardianController extends BaseController
{
    public function index()
    {
        $uid  = (int) service('auth')->id();
        $kids = service('guardians')->studentsOf($uid);
        if (count($kids) === 1) {
            return redirect()->to('acudiente/' . $kids[0]['id']);
        }
        foreach ($kids as &$k) {
            $p           = service('myProgress')->progress((int) $k['id']);
            $k['avg']    = $p['avg'];
            $k['counts'] = $p['counts'];
            $pending      = \App\Libraries\StudentProgress::GROUPS['pending'][1];
            $k['pending'] = count(array_filter(service('myProgress')->activities((int) $k['id']), static fn ($a) => in_array($a['state'], $pending, true)));
        }
        unset($k);

        return view('guardian/index', $this->layoutData() + ['title' => 'Mis estudiantes', 'kids' => $kids]);
    }

    public function show(int $studentId)
    {
        $uid = (int) service('auth')->id();
        if (! service('guardians')->canView($uid, $studentId)) {
            return redirect()->to('acudiente')->with('toast', ['type' => 'error', 'msg' => 'Ese estudiante no está vinculado a su cuenta.']);
        }
        $kids = service('guardians')->studentsOf($uid);
        $kid  = current(array_filter($kids, static fn ($k) => (int) $k['id'] === $studentId));

        return view('guardian/show', $this->layoutData() + [
            'title' => $kid['name'], 'kid' => $kid, 'kids' => $kids,
            'p'     => service('myProgress')->progress($studentId),
            'acts'  => service('myProgress')->activities($studentId),
        ]);
    }
}
