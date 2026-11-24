<?php

namespace App\Controllers;

/**
 * Consola de una red educativa (grupo de colegios): resumen consolidado, biblioteca compartida y administradores.
 * Acceso: administradores y analistas de la red, y el superadministrador. Solo cifras agregadas: nunca datos
 * personales de estudiantes de un colegio.
 */
class NetworkController extends BaseController
{
    /** Contexto del layout para la consola de la red (sin institución activa). */
    private function ctx(array $net, array $data = []): array
    {
        $auth = service('auth');

        return $data + [
            'user'         => $auth->user(),
            'isSuperadmin' => $auth->isSuperadmin(),
            'network'      => $net,
            'networks'     => service('network')->forUser(),
            'institution'  => null,
            'institutions' => service('tenant')->availableInstitutions(),
            'perms'        => [],
            'railItems'    => array_values(array_filter(config('AgendaEscolar')->networkRail,
                static fn ($i) => $i['route'] !== 'red/administradores' || $net['role'] === 'admin')),
        ];
    }

    /** Red activa; si no hay y la persona tiene una sola, entra a esa. */
    private function active(): ?array
    {
        $svc = service('network');
        if ($net = $svc->active()) {
            return $net;
        }
        $list = $svc->forUser();
        if (count($list) === 1 && $svc->enter((int) $list[0]['id'])) {
            return $svc->active();
        }

        return null;
    }

    public function index()
    {
        $net = $this->active();
        if (! $net) {
            $list = service('network')->forUser();
            if (! $list) {
                return redirect()->to('/')->with('toast', ['type' => 'error', 'msg' => 'No administra ninguna red educativa.']);
            }

            return view('network/choose', ['title' => 'Elija una red', 'networks' => $list, 'user' => service('auth')->user(), 'isSuperadmin' => service('auth')->isSuperadmin()]);
        }

        return view('network/home', $this->ctx($net, ['title' => 'Resumen de la red', 'r' => service('network')->report((int) $net['id'])]));
    }

    public function enter()
    {
        if (! service('network')->enter((int) $this->request->getPost('network_id'))) {
            return redirect()->to('red')->with('toast', ['type' => 'error', 'msg' => 'No tiene acceso a esa red.']);
        }
        session()->remove('institution_id'); // en la consola de la red no hay colegio activo

        return redirect()->to('red');
    }

    public function library()
    {
        if (! $net = $this->active()) {
            return redirect()->to('red');
        }

        return view('network/library', $this->ctx($net, ['title' => 'Biblioteca compartida', 'banks' => service('network')->sharedBanks((int) $net['id'])]));
    }

    public function members()
    {
        $net = $this->active();
        if (! $net || $net['role'] !== 'admin') {
            return redirect()->to('red')->with('toast', ['type' => 'error', 'msg' => 'Solo los administradores de la red gestionan su equipo.']);
        }

        return view('network/members', $this->ctx($net, ['title' => 'Administradores de la red', 'members' => service('network')->members((int) $net['id'])]));
    }

    public function addMember()
    {
        $net = $this->active();
        if (! $net || $net['role'] !== 'admin') {
            return redirect()->to('red');
        }
        $e = service('network')->addMember((int) $net['id'], (string) $this->request->getPost('email'), (string) $this->request->getPost('role'));

        return $e ? $this->backWith('error', $e) : redirect()->to('red/administradores')->with('toast', ['type' => 'ok', 'msg' => 'Persona agregada a la red. Queda en la auditoría.']);
    }

    public function removeMember(int $id)
    {
        $net = $this->active();
        if (! $net || $net['role'] !== 'admin') {
            return redirect()->to('red');
        }
        $e = service('network')->removeMember((int) $net['id'], $id);

        return $e ? $this->backWith('error', $e) : redirect()->to('red/administradores')->with('toast', ['type' => 'ok', 'msg' => 'Persona retirada de la red.']);
    }

    /** Entrar a un colegio de la red desde la consola (solo administradores; queda en la auditoría). */
    public function school(int $institutionId)
    {
        $net = $this->active();
        $ok  = $net && $net['role'] === 'admin'
            && in_array($institutionId, array_map('intval', array_column(service('network')->institutions((int) $net['id']), 'id')), true)
            && service('tenant')->switchTo($institutionId);
        if (! $ok) {
            return redirect()->to('red')->with('toast', ['type' => 'error', 'msg' => 'No puede entrar a ese colegio o está suspendido.']);
        }
        $name = service('tenant')->institution()['name'];

        return redirect()->to('inicio')->with('toast', ['type' => 'info', 'msg' => 'Entró a ' . $name . (service('auth')->isSuperadmin() ? ' como superadministrador' : ' como administrador de la red') . '. Queda en la auditoría.']);
    }
}
