<?php

namespace App\Controllers;

use App\Models\UserModel;

/**
 * Ayudas SOLO de desarrollo (capturas automáticas y pruebas). La ruta solo se registra con CI_ENVIRONMENT=development
 * y además solo responde a peticiones desde este mismo equipo. En producción no existe.
 */
class DevController extends BaseController
{
    public function loginAs()
    {
        if (ENVIRONMENT !== 'development' || ! in_array($this->request->getIPAddress(), ['127.0.0.1', '::1'], true)) {
            return $this->response->setStatusCode(404);
        }
        $user = model(UserModel::class)->findByEmail((string) $this->request->getGet('email'));
        if (! $user || $user['status'] !== 'active') {
            return $this->response->setStatusCode(404);
        }
        service('auth')->login($user);
        $list = service('tenant')->availableInstitutions();
        $inst = (int) $this->request->getGet('inst') ?: (count($list) === 1 ? (int) $list[0]['id'] : 0);
        if ($inst) {
            service('tenant')->switchTo($inst);
        }
        $to = (string) ($this->request->getGet('to') ?: 'inicio');

        return redirect()->to(preg_match('#^[a-z0-9/_?=&.-]+$#i', $to) ? $to : 'inicio');
    }
}
