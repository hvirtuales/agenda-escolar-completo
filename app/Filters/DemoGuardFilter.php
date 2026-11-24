<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Demostración pública (filtro global): las cuentas de demostración (users.is_demo) con agendaescolar.demoMode activo no
 * pueden cambiar contraseña, verificación en dos pasos ni foto, ni crear o modificar cuentas o contraseñas de otros.
 * Así ningún visitante deja a los demás sin acceso. Todo lo demás se puede probar y se restaura cada noche (demo:reset).
 */
class DemoGuardFilter implements FilterInterface
{
    /** Rutas POST bloqueadas para las cuentas de demostración. */
    private const BLOCKED = [
        '#^perfil/contrasena$#', '#^perfil/dos-pasos(/.*)?$#', '#^perfil/foto(/quitar)?$#',
        '#^ajustes/usuarios(/\d+(/contrasena|/dos-pasos)?)?$#', '#^ajustes/usuarios/importar(/.*)?$#', '#^ajustes/roles(/.*)?$#',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        if ($request->getMethod() !== 'POST' || ! service('demo')->restricted()) {
            return null;
        }
        $path = trim($request->getUri()->getPath(), '/');
        foreach (self::BLOCKED as $re) {
            if (preg_match($re, $path)) {
                $msg = t('App.demoBlocked');
                if ($request->isAJAX()) {
                    return service('response')->setStatusCode(403)->setJSON(['ok' => false, 'error' => $msg]);
                }

                return redirect()->back()->with('toast', ['type' => 'info', 'msg' => $msg]);
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
