<?php

namespace App\Controllers;

/** Selector de idioma (ES / EN): guarda la elección y vuelve a la misma pantalla. */
class LocaleController extends BaseController
{
    public function choose()
    {
        $l = (string) $this->request->getPost('locale');
        service('locale')->choose($l);

        // Volver a la pantalla anterior solo si es de esta misma plataforma.
        $back = (string) $this->request->getPost('back');
        $base = rtrim(base_url(), '/');
        $to   = $back !== '' && str_starts_with($back, $base . '/') ? $back : site_url('/');

        return redirect()->to($to);
    }
}
