<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Suplantación en solo lectura (filtro global; ver App\Libraries\Impersonation).
 *
 * - Si la suplantación venció o fue revocada, la termina y vuelve a la plataforma.
 * - Rechaza todo lo que no sea GET/HEAD, salvo terminarla o cerrar sesión.
 * - Cada GET corre dentro de una transacción que se deshace al final: ni lo que se escribe al leer (marcar un mensaje
 *   o un aviso como leído, barridos de fechas…) queda guardado. Si la petición falla, la conexión se cierra sin
 *   confirmar (pConnect = false), con el mismo efecto.
 */
class ImpersonationFilter implements FilterInterface
{
    private const ALLOWED = ['suplantacion/terminar', 'logout'];

    private static bool $inTx = false;

    public function before(RequestInterface $request, $arguments = null)
    {
        $imp = service('impersonation');
        if (! $imp->active()) {
            return null;
        }
        if (! $imp->valid()) {
            $imp->end('expired');

            return redirect()->to(service('auth')->isSuperadmin() ? 'ajustes/usuarios' : 'login')
                ->with('toast', ['type' => 'info', 'msg' => t('App.impEnded')]);
        }

        $path = trim($request->getUri()->getPath(), '/');
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            if (in_array($path, self::ALLOWED, true)) {
                return null;
            }
            $msg = t('App.impBlocked');
            if ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                return service('response')->setStatusCode(403)->setJSON(['error' => $msg, 'ok' => false]);
            }

            return redirect()->back()->with('toast', ['type' => 'error', 'msg' => $msg]);
        }

        self::$inTx = db_connect()->transBegin();

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (self::$inTx) {
            self::$inTx = false;
            db_connect()->transRollback();
        }
    }
}
