<?php

namespace App\Controllers;

/** Estudiantes (docente y coordinación): lista por grupo y ficha del estudiante con observaciones. */
class StudentsController extends BaseController
{
    public function index()
    {
        $svc    = service('students');
        $groups = $svc->groups();
        if (! $groups) {
            return view('students/empty', $this->layoutData() + ['title' => 'Estudiantes']);
        }
        $gid    = (int) $this->request->getGet('g') ?: (int) $groups[0]['id'];
        $roster = $svc->roster($gid);
        if (! $roster) {
            return redirect()->to('estudiantes')->with('toast', ['type' => 'error', 'msg' => 'Ese grupo no existe o no es suyo.']);
        }

        return view('students/index', $this->layoutData() + ['title' => 'Estudiantes', 'groups' => $groups, 'r' => $roster, 'q' => trim((string) $this->request->getGet('q'))]);
    }

    public function show(int $id)
    {
        $p = service('students')->profile($id);
        if (! $p) {
            return redirect()->to('estudiantes')->with('toast', ['type' => 'error', 'msg' => 'Ese estudiante no está en sus grupos.']);
        }

        return view('students/show', $this->layoutData() + ['title' => $p['student']['name'], 'p' => $p,
            'guardians' => service('tenant')->can('tenant.users.manage') ? service('guardians')->linksOf($id) : null]);
    }

    public function addNote(int $id)
    {
        $e = service('students')->addNote($id, (string) $this->request->getPost('body'), (int) $this->request->getPost('offering_id') ?: null);

        return $e ? redirect()->back()->withInput()->with('toast', ['type' => 'error', 'msg' => $e])
            : redirect()->to('estudiantes/' . $id . '#observaciones')->with('toast', ['type' => 'ok', 'msg' => 'Observación guardada. La ven los docentes del estudiante y la coordinación.']);
    }

    public function deleteNote(int $id, int $noteId)
    {
        $e = service('students')->deleteNote($id, $noteId);

        return $e ? $this->backWith('error', $e) : redirect()->to('estudiantes/' . $id . '#observaciones')->with('toast', ['type' => 'ok', 'msg' => 'Observación eliminada.']);
    }

    public function linkGuardian(int $id)
    {
        $e = service('guardians')->link($id, (string) $this->request->getPost('email'), (string) $this->request->getPost('relationship'));

        return $e ? redirect()->back()->withInput()->with('toast', ['type' => 'error', 'msg' => $e])
            : redirect()->to('estudiantes/' . $id . '#acudientes')->with('toast', ['type' => 'ok', 'msg' => 'Acudiente vinculado. Ya puede ver el progreso del estudiante.']);
    }

    public function unlinkGuardian(int $id, int $linkId)
    {
        $e = service('guardians')->unlink($id, $linkId);

        return $e ? $this->backWith('error', $e) : redirect()->to('estudiantes/' . $id . '#acudientes')->with('toast', ['type' => 'ok', 'msg' => 'Acudiente desvinculado.']);
    }
}
