<?php

namespace App\Controllers;

/** Bandeja de notificaciones de la persona (campana de la barra superior). */
class NotificationsController extends BaseController
{
    public function index()
    {
        $uid  = (int) service('auth')->id();
        $only = $this->request->getGet('ver') === 'sin-leer';
        $base = service('tenant')->institution() ? $this->layoutData() : [
            'user' => service('auth')->user(), 'isSuperadmin' => service('auth')->isSuperadmin(), 'institution' => null,
            'institutions' => service('tenant')->availableInstitutions(), 'networks' => service('network')->forUser(), 'perms' => [],
            'platform' => service('auth')->isSuperadmin(), 'railItems' => service('auth')->isSuperadmin() ? config('AgendaEscolar')->platformRail : [],
        ];

        return view('notifications/index', $base + ['title' => 'Notificaciones', 'items' => service('notify')->latest($uid, 100, $only), 'only' => $only]);
    }

    /** Abre una notificación: la marca como leída, entra a su institución si hace falta y va a su enlace. */
    public function open(int $id)
    {
        $n = service('notify')->open((int) service('auth')->id(), $id);
        if (! $n) {
            return redirect()->to('notificaciones');
        }
        $tenant = service('tenant');
        if ($n['institution_id'] && (int) $tenant->institutionId() !== (int) $n['institution_id'] && ! $tenant->switchTo((int) $n['institution_id'])) {
            return redirect()->to('notificaciones')->with('toast', ['type' => 'error', 'msg' => 'Ya no tiene acceso a la institución de ese aviso.']);
        }

        return redirect()->to($n['url'] ? site_url($n['url']) : 'notificaciones');
    }

    public function readAll()
    {
        service('notify')->readAll((int) service('auth')->id());

        return redirect()->back()->with('toast', ['type' => 'ok', 'msg' => 'Todas las notificaciones quedaron como leídas.']);
    }
}
