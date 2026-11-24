<?php

namespace App\Controllers;

/** «Ver como» (suplantación de soporte en solo lectura). Las reglas están en App\Libraries\Impersonation. */
class ImpersonationController extends BaseController
{
    public function start(int $userId)
    {
        $back = redirect()->to('ajustes/usuarios');
        $auth = service('auth');
        if (! $auth->isSuperadmin()) {
            return $back->with('toast', ['type' => 'error', 'msg' => t('App.impOnlySuperadmin')]);
        }
        $reason = trim((string) $this->request->getPost('reason'));
        if (mb_strlen($reason) < 10) {
            return $back->with('toast', ['type' => 'error', 'msg' => t('App.impReasonRequired')]);
        }
        if (! password_verify((string) $this->request->getPost('password'), (string) $auth->user()['password_hash'])) {
            service('audit')->log('impersonation.denied', ['motivo' => 'contraseña incorrecta']);

            return $back->with('toast', ['type' => 'error', 'msg' => t('App.wrongPassword')]);
        }
        if ($e = service('impersonation')->start($userId, $reason)) {
            return $back->with('toast', ['type' => 'error', 'msg' => $e]);
        }

        return redirect()->to('inicio')->with('toast', ['type' => 'info', 'msg' => t('App.impStarted')]);
    }

    public function stop()
    {
        $imp = service('impersonation');
        if (! $imp->active()) {
            return redirect()->to('/');
        }
        $imp->end('manual');

        return service('auth')->isSuperadmin()
            ? redirect()->to('ajustes/usuarios')->with('toast', ['type' => 'ok', 'msg' => t('App.impBackToAccount')])
            : redirect()->to('login');
    }
}
