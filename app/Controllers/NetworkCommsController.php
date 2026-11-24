<?php

namespace App\Controllers;

use App\Libraries\Events;
use App\Libraries\News;
use App\Libraries\NetworkComms;

/**
 * Consola de la red › Comunicaciones: mensajes a directivos y docentes, noticias y eventos para los colegios de la red.
 * Envían los administradores de la red; los analistas ven el historial y sus cifras.
 */
class NetworkCommsController extends BaseController
{
    /** Red activa; si no hay y la persona tiene una sola, entra a esa (como la consola). */
    private function net(): ?array
    {
        $svc = service('network');
        if ($net = $svc->active()) {
            return $net;
        }
        $list = $svc->forUser();

        return count($list) === 1 && $svc->enter((int) $list[0]['id']) ? $svc->active() : null;
    }

    private function ctx(array $net, array $data): array
    {
        $auth = service('auth');

        return $data + [
            'user' => $auth->user(), 'isSuperadmin' => $auth->isSuperadmin(), 'network' => $net, 'networks' => service('network')->forUser(),
            'institution' => null, 'institutions' => service('tenant')->availableInstitutions(), 'perms' => [],
            'railItems' => array_values(array_filter(config('AgendaEscolar')->networkRail,
                static fn ($i) => $i['route'] !== 'red/administradores' || $net['role'] === 'admin')),
        ];
    }

    public function index()
    {
        if (! $net = $this->net()) {
            return redirect()->to('red');
        }
        $kind = (string) $this->request->getGet('tipo');
        $svc  = service('networkComms');

        return view('network/comms', $this->ctx($net, [
            'title' => 'Comunicaciones de la red', 'kind' => isset(NetworkComms::KINDS[$kind]) ? $kind : null,
            'posts' => $svc->list((int) $net['id'], isset(NetworkComms::KINDS[$kind]) ? $kind : null), 'canSend' => $svc->canSend($net),
            'schools' => service('network')->institutions((int) $net['id']), 'categories' => Events::CATEGORIES,
            'msgAudiences' => NetworkComms::MSG_AUDIENCES, 'audiences' => News::AUDIENCES,
        ]));
    }

    public function show(int $id)
    {
        if (! $net = $this->net()) {
            return redirect()->to('red');
        }
        $p = service('networkComms')->get((int) $net['id'], $id);
        if (! $p) {
            return redirect()->to('red/comunicaciones')->with('toast', ['type' => 'error', 'msg' => 'Ese envío no existe.']);
        }

        return view('network/comms_show', $this->ctx($net, ['title' => $p['title'], 'p' => $p, 'canSend' => service('networkComms')->canSend($net),
            'categories' => Events::CATEGORIES, 'audiences' => News::AUDIENCES + NetworkComms::MSG_AUDIENCES]));
    }

    public function send()
    {
        $net = $this->net();
        $svc = service('networkComms');
        if (! $net || ! $svc->canSend($net)) {
            return redirect()->to('red/comunicaciones')->with('toast', ['type' => 'error', 'msg' => 'Solo los administradores de la red envían comunicaciones.']);
        }
        $in   = $this->request->getPost();
        $kind = (string) ($in['kind'] ?? '');
        $r    = match ($kind) {
            'message' => $svc->sendMessage($net, $in),
            'news'    => $svc->publishNews($net, $in, $this->request->getFile('cover')),
            'event'   => $svc->createEvent($net, $in),
            default   => ['error' => 'Elige qué quieres enviar.'],
        };
        if (isset($r['error'])) {
            return redirect()->back()->withInput()->with('toast', ['type' => 'error', 'msg' => $r['error']]);
        }
        $msg = match ($kind) {
            'message' => 'Mensaje enviado a ' . $r['count'] . ' personas.',
            'news'    => 'Noticia publicada en los colegios elegidos.',
            default   => 'Evento agregado al calendario de los colegios elegidos.',
        };

        return redirect()->to('red/comunicaciones/' . $r['id'])->with('toast', ['type' => 'ok', 'msg' => $msg]);
    }

    public function withdraw(int $id)
    {
        if (! $net = $this->net()) {
            return redirect()->to('red');
        }
        $e = service('networkComms')->withdraw($net, $id);

        return redirect()->to('red/comunicaciones')->with('toast', $e ? ['type' => 'error', 'msg' => $e] : ['type' => 'ok', 'msg' => 'Envío retirado de los colegios.']);
    }
}
