<?php

namespace App\Controllers;

/**
 * Mensajes: bandeja de recibidos (todas las personas), enviados y redacción (docentes, coordinación y rectoría).
 * Las reglas de a quién se puede escribir están en service('messages').
 */
class MessagesController extends BaseController
{
    private function layout(): array
    {
        return service('tenant')->isStudent() ? $this->studentLayout() : $this->layoutData();
    }

    public function index()
    {
        $svc = service('messages');
        $box = $this->request->getGet('ver') === 'enviados' && $svc->canSend() ? 'sent' : 'inbox';
        $q   = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);

        return view('messages/index', $this->layout() + ['title' => 'Mensajes', 'box' => $box, 'q' => $q, 'list' => $svc->inbox($box, $q),
            'canSend' => $svc->canSend(), 'unread' => $svc->unread()]);
    }

    public function compose()
    {
        $svc = service('messages');
        if (! $svc->canSend()) {
            return redirect()->to('mensajes');
        }

        return view('messages/compose', $this->layout() + ['title' => 'Nuevo mensaje', 'manager' => $svc->isManager(), 'roles' => \App\Libraries\Messages::ROLES,
            'groups' => $svc->groups()]);
    }

    public function send()
    {
        $r = service('messages')->send((array) $this->request->getPost('targets'), (string) $this->request->getPost('subject'),
            (string) $this->request->getPost('body'), (bool) $this->request->getPost('important'));
        if (isset($r['error'])) {
            return redirect()->back()->withInput()->with('toast', ['type' => 'error', 'msg' => $r['error']]);
        }

        return redirect()->to('mensajes/' . $r['id'])->with('toast', ['type' => 'ok', 'msg' => 'Mensaje enviado a ' . plural($r['count'], 'persona', 'personas') . '.']);
    }

    public function show(int $id)
    {
        $m = service('messages')->open($id);
        if (! $m) {
            return redirect()->to('mensajes')->with('toast', ['type' => 'error', 'msg' => 'Ese mensaje no existe o no es para ti.']);
        }

        return view('messages/show', $this->layout() + ['title' => $m['subject'], 'm' => $m]);
    }

    /** GET mensajes/personas?q= — búsqueda para «Para». */
    public function people()
    {
        return $this->response->setJSON(service('messages')->canSend() ? service('messages')->searchPeople((string) $this->request->getGet('q')) : []);
    }

    /** POST mensajes/contar {targets[]} — cuántas personas recibirían el mensaje. */
    public function count()
    {
        $r = service('messages')->resolve((array) ($this->request->getJSON(true)['targets'] ?? []));

        return $this->response->setJSON(['count' => isset($r['ids']) ? count($r['ids']) : 0, 'audience' => $r['audience'] ?? '']);
    }
}
