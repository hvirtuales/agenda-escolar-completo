<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Exige una sesión iniciada. Si la contraseña la puso otra persona, solo deja entrar a Mi perfil hasta cambiarla. */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! service('auth')->check()) {
            if ($request->getMethod() === 'GET') {
                session()->set('redirect_after_login', current_url());
            }

            return redirect()->to('/login')->with('toast', ['type' => 'info', 'msg' => t('App.loginToContinue')]);
        }
        // Viendo como otra persona (solo lectura): no se le obliga a cambiar su contraseña ni a activar dos pasos.
        if (service('impersonation')->active()) {
            return null;
        }
        if ((int) (service('auth')->user()['must_change_password'] ?? 0)) {
            $path = trim($request->getUri()->getPath(), '/');
            if (! in_array($path, ['perfil', 'perfil/contrasena', 'perfil/foto', 'logout'], true)) {
                return redirect()->to('perfil#seguridad')->with('toast', ['type' => 'info', 'msg' => t('App.changeAssignedPassword')]);
            }
        }
        // Verificación en dos pasos obligatoria para administradores: solo Mi perfil hasta activarla.
        $user = service('auth')->user();
        if (! service('mfa')->enabled($user) && service('mfa')->required($user)) {
            $path = trim($request->getUri()->getPath(), '/');
            if (! in_array($path, ['perfil', 'perfil/contrasena', 'perfil/foto', 'logout'], true) && ! str_starts_with($path, 'perfil/dos-pasos')) {
                return redirect()->to('perfil#dos-pasos')->with('toast', ['type' => 'info', 'msg' => t('App.mfaRequiredForRole')]);
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
