<?php

namespace App\Controllers;

/**
 * Asistente flotante de la interfaz (service('helpAssistant')): preguntas abiertas sobre cómo usar la plataforma y la
 * imagen del asistente. Solo requiere sesión. La configuración (nombre, imagen, activo) está en la consola.
 */
class AssistantController extends BaseController
{
    /** POST asistente/preguntar  {message, history[], page, title} */
    public function ask()
    {
        if (service('throttler')->check('assistant-' . service('auth')->id(), 20, MINUTE) === false) {
            return $this->response->setStatusCode(429)->setJSON(['ok' => false, 'error' => 'Vas muy rápido. Espera un momento y vuelve a preguntar.']);
        }
        $in = $this->request->getJSON(true) ?: [];
        $r  = service('helpAssistant')->answer((string) ($in['message'] ?? ''), (array) ($in['history'] ?? []), (string) ($in['page'] ?? ''), (string) ($in['title'] ?? ''));
        if (isset($r['error'])) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $r['error']]);
        }
        foreach (['link', 'article'] as $k) { // [texto, ruta] → {text, url}
            $r[$k] = ! empty($r[$k]) ? ['text' => $r[$k][0], 'url' => site_url($r[$k][1])] : null;
        }

        return $this->response->setJSON(['ok' => true] + $r);
    }

    /** GET asistente/imagen — la imagen personalizada (o el logo de la plataforma). */
    public function image()
    {
        $f = service('helpAssistant')->avatarFile();
        if (! $f) {
            return redirect()->to(base_url('assets/img/logo-mark.png'));
        }

        return $this->response->setHeader('Content-Type', $f['mime'])->setHeader('Cache-Control', 'private, max-age=86400')
            ->setHeader('X-Content-Type-Options', 'nosniff')->setBody((string) file_get_contents($f['path']));
    }

    /* ---------- Consola (superadministrador) ---------- */

    public function saveSettings()
    {
        $svc = service('helpAssistant');
        $svc->save((bool) $this->request->getPost('enabled'), (string) $this->request->getPost('name'));
        $file = $this->request->getFile('avatar');
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            if ($e = $svc->setAvatar($file)) {
                return redirect()->to('plataforma/configuracion#asistente')->with('toast', ['type' => 'error', 'msg' => $e]);
            }
        }
        service('audit')->log('platform.assistant.update', [], null);

        return redirect()->to('plataforma/configuracion#asistente')->with('toast', ['type' => 'ok', 'msg' => 'Asistente actualizado.']);
    }

    public function clearImage()
    {
        service('helpAssistant')->clearAvatar();

        return redirect()->to('plataforma/configuracion#asistente')->with('toast', ['type' => 'ok', 'msg' => 'Se restauró la imagen por defecto.']);
    }
}
