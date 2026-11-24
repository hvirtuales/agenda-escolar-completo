<?php

namespace App\Controllers;

/** Inicio y cierre de sesión (documento maestro 7.4 y 10). */
class AuthController extends BaseController
{
    public function login()
    {
        if (service('auth')->check()) {
            return redirect()->to(service('auth')->isSuperadmin() ? 'plataforma' : 'inicio');
        }

        return view('auth/login', ['title' => 'Iniciar sesión']);
    }

    public function attempt()
    {
        // Máximo 5 intentos por minuto por IP.
        $key = 'login-' . md5($this->request->getIPAddress());
        if (service('throttler')->check($key, 5, MINUTE) === false) {
            return redirect()->back()->withInput()->with('toast', [
                'type' => 'error',
                'msg'  => t('Auth.tooMany'),
            ]);
        }

        if (! $this->validate([
            // Correo o número de documento (Auth::attempt decide cuál es).
            'email'    => ['label' => t('Auth.identity'), 'rules' => 'required|max_length[160]'],
            'password' => ['label' => 'Contraseña', 'rules' => 'required'],
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $auth = service('auth');
        $user = $auth->attempt((string) $this->request->getPost('email'), (string) $this->request->getPost('password'));
        if (! $user) {
            return redirect()->back()->withInput()->with('toast', [
                'type' => 'error',
                'msg'  => t('Auth.badCredentials'),
            ]);
        }

        // Con verificación en dos pasos: la sesión no se abre hasta validar el código (máximo 5 minutos).
        if (service('mfa')->enabled($user)) {
            session()->regenerate(true);
            session()->set(['mfa_user' => (int) $user['id'], 'mfa_until' => time() + 300]);

            return redirect()->to('login/verificacion');
        }

        return $this->finishLogin($user);
    }

    /** Segundo paso: formulario del código. */
    public function mfaForm()
    {
        $user = $this->pendingMfaUser();
        if (! $user) {
            return redirect()->to('login')->with('toast', ['type' => 'info', 'msg' => t('Auth.loginAgain')]);
        }

        return view('auth/mfa', ['title' => 'Verificación en dos pasos', 'email' => $user['email'] ?: \App\Models\UserModel::docLabel($user['doc_type'], $user['doc_number'])]);
    }

    public function mfaVerify()
    {
        $user = $this->pendingMfaUser();
        if (! $user) {
            return redirect()->to('login')->with('toast', ['type' => 'info', 'msg' => t('Auth.mfaExpired')]);
        }
        if (service('throttler')->check('mfa-' . $user['id'], 5, MINUTE) === false) {
            return redirect()->to('login/verificacion')->with('toast', ['type' => 'error', 'msg' => t('Auth.mfaTooMany')]);
        }
        $how = service('mfa')->verify($user, (string) $this->request->getPost('code'));
        if (! $how) {
            return redirect()->to('login/verificacion')->with('toast', ['type' => 'error', 'msg' => t('Auth.mfaWrong')]);
        }
        session()->remove(['mfa_user', 'mfa_until']);

        return $this->finishLogin($user, $how === 'recovery');
    }

    public function mfaCancel()
    {
        session()->remove(['mfa_user', 'mfa_until']);

        return redirect()->to('login');
    }

    /** Persona con la contraseña ya validada que debe escribir su código (o null si venció). */
    private function pendingMfaUser(): ?array
    {
        $id = (int) session('mfa_user');
        if (! $id || (int) session('mfa_until') < time()) {
            return null;
        }
        $user = model(\App\Models\UserModel::class)->find($id);

        return $user && $user['status'] === 'active' && service('mfa')->enabled($user) ? $user : null;
    }

    /** Abre la sesión y lleva a cada persona a su inicio. */
    private function finishLogin(array $user, bool $usedRecovery = false)
    {
        $auth = service('auth');
        $auth->login($user);
        service('audit')->log('auth.login', $usedRecovery ? ['con' => 'código de respaldo'] : [], null);
        $hello = ['type' => 'ok', 'msg' => t('Auth.hello', [explode(' ', $user['name'])[0]])];

        if ($user['is_superadmin']) {
            $to = site_url('plataforma');
        } else {
            // Si solo tiene una institución, entra directo.
            $list     = service('tenant')->availableInstitutions();
            $networks = service('network')->forUser();
            if (count($list) === 1 && ! $networks) {
                service('tenant')->switchTo((int) $list[0]['id']);
            }
            // Quien solo administra redes (sin colegios propios) entra a la consola de su red.
            $to = session('redirect_after_login') ?: site_url(! $list && $networks ? 'red' : (count($list) === 1 && ! $networks ? 'inicio' : 'instituciones'));
        }
        session()->remove('redirect_after_login');

        // Con un código de respaldo: a Mi perfil, para que vea cuántos le quedan (la institución ya quedó elegida).
        if ($usedRecovery) {
            $left = service('mfa')->remainingCodes((int) $user['id']);

            return redirect()->to('perfil#dos-pasos')->with('toast', ['type' => 'info', 'msg' => t('Auth.mfaBackupUsed', [$left])]);
        }

        return redirect()->to($to)->with('toast', $hello);
    }

    public function logout()
    {
        // Durante «Ver como», cerrar sesión termina la suplantación y devuelve al superadministrador.
        if (service('impersonation')->active()) {
            service('impersonation')->end('logout');
            if (service('auth')->isSuperadmin()) {
                return redirect()->to('ajustes/usuarios')->with('toast', ['type' => 'ok', 'msg' => t('App.impBackToAccount')]);
            }

            return redirect()->to('login');
        }
        service('auth')->logout();

        return redirect()->to('login');
    }
}
