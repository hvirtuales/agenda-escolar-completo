<?php

namespace App\Controllers;

/** Elegir o cambiar la institución activa (documento maestro 7.4). */
class InstitutionSelectController extends BaseController
{
    public function index()
    {
        $auth = service('auth');
        $list = service('tenant')->availableInstitutions();

        $networks = $auth->isSuperadmin() ? [] : service('network')->forUser();
        if (! $list && $networks) {
            return redirect()->to('red');
        }
        if (count($list) === 1 && ! $auth->isSuperadmin() && ! $networks) {
            service('tenant')->switchTo((int) $list[0]['id']);
            session()->keepFlashdata('toast'); // solo redirige: el aviso debe llegar a la pantalla final

            return redirect()->to('inicio');
        }

        return view('institutions/select', [
            'title'        => 'Elija una institución',
            'institutions' => $list,
            'user'         => $auth->user(),
            'isSuperadmin' => $auth->isSuperadmin(),
            'networks'     => $networks,
        ]);
    }

    public function switch()
    {
        $tenant = service('tenant');
        if (! $tenant->switchTo((int) $this->request->getPost('institution_id'))) {
            return redirect()->to('instituciones')->with('toast', [
                'type' => 'error',
                'msg'  => 'No tiene acceso a esa institución o está suspendida.',
            ]);
        }
        $msg = service('auth')->isSuperadmin()
            ? 'Entró a ' . $tenant->institution()['name'] . ' como superadministrador. Queda en la auditoría.'
            : 'Trabajando en ' . $tenant->institution()['name'] . '.';

        return redirect()->to('inicio')->with('toast', ['type' => service('auth')->isSuperadmin() ? 'info' : 'ok', 'msg' => $msg]);
    }
}
