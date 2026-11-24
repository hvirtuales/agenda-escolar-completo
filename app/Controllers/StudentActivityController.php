<?php

namespace App\Controllers;

/** Reto del estudiante (documento maestro 13.3): instrucciones, pasos, pistas, borrador y entrega. */
class StudentActivityController extends BaseController
{
    /** Reto del estudiante; para docentes y coordinación, vista previa del reto asignado al grupo previsualizado (solo lectura). */
    public function show(int $assignmentId)
    {
        $pv      = service('studentPreview');
        $preview = ! $pv->isStudent();
        $v       = service('activities')->studentView($assignmentId, $preview ? 0 : (int) service('auth')->id(), $preview ? ($pv->groupId() ?? 0) : null);
        if (! $v) {
            return redirect()->to('estudiante/actividades')->with('toast', ['type' => 'error', 'msg' => $preview ? 'Ese reto no está asignado al grupo de la vista previa.' : 'Ese reto no está disponible para usted.']);
        }

        return view('student/challenge_real', $this->studentLayout() + [
            'title'  => $v['activity']['title'],
            'v'      => $v,
            'thread'  => $preview ? [] : service('assistant')->thread($assignmentId, (int) service('auth')->id()),
            'preview' => $preview,
            'labelAi' => (bool) service('aiQuota')->settings((int) service('tenant')->institutionId())['label_ai'],
        ]);
    }

    public function save(int $assignmentId)
    {
        $submit = $this->request->getPost('action') === 'submit';
        $e      = service('activities')->studentSave($assignmentId, (int) service('auth')->id(), (string) $this->request->getPost('answer'),
            (array) $this->request->getPost('steps'), $submit);
        if ($e) {
            return redirect()->back()->withInput()->with('toast', ['type' => 'error', 'msg' => $e]);
        }

        return redirect()->to('estudiante/retos/' . $assignmentId)->with('toast', ['type' => 'ok', 'msg' => $submit ? 'Reto entregado. Tu docente lo revisará.' : 'Borrador guardado.']);
    }
}
