<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Exige al menos uno de los permisos indicados en la institución activa. Uso: 'can:activities.create' o 'can:a,b'.
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $perms = (array) $arguments;
        if ($perms !== [] && ! service('tenant')->can(...$perms)) {
            $msg = 'Su rol en esta institución no tiene permiso para eso. Pídale acceso a quien administra la institución.';
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(403)->setJSON(['ok' => false, 'error' => $msg]);
            }

            return redirect()->to('/')->with('toast', ['type' => 'error', 'msg' => $msg]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
