<?php

namespace App\Controllers;

/** Eventos de la institución: se gestionan desde «Mi agenda» (reglas en service('events')). */
class EventsController extends BaseController
{
    /** La pantalla «Eventos» se unió a «Mi agenda»: los eventos se crean, editan y eliminan desde allí. */
    public function index()
    {
        $nuevo = (string) $this->request->getGet('nuevo');
        if (service('events')->canManage() && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevo)) {
            return redirect()->to('agenda?dia=' . $nuevo . '&evento=nuevo');
        }

        return redirect()->to('agenda');
    }

    private function backTo(?string $date): string
    {
        return 'agenda' . ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? '?dia=' . $date : '');
    }

    public function save(?int $id = null)
    {
        $r = service('events')->save($id, $this->request->getPost());
        if (isset($r['error'])) {
            return redirect()->back()->withInput()->with('toast', ['type' => 'error', 'msg' => $r['error']]);
        }

        return redirect()->to($this->backTo((string) $this->request->getPost('date')))->with('toast', ['type' => 'ok', 'msg' => $id ? 'Evento actualizado.' : 'Evento creado. Se avisó a su público.']);
    }

    public function delete(int $id)
    {
        $e = service('events')->delete($id);

        return redirect()->to($this->backTo((string) $this->request->getPost('date')))->with('toast', $e ? ['type' => 'error', 'msg' => $e] : ['type' => 'ok', 'msg' => 'Evento eliminado.']);
    }
}
