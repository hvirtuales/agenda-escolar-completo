<?php

namespace App\Controllers;

/** GET buscar?scope=…&q=… → resultados JSON de la búsqueda global (el alcance real lo decide Search). */
class SearchController extends BaseController
{
    public function index()
    {
        return $this->response->setJSON(['results' => service('search')->run((string) $this->request->getGet('scope'), (string) $this->request->getGet('q'))]);
    }
}
