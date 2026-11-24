<?php

namespace App\Controllers;

/**
 * Noticias y boletines: lectura para todos según su público; rectoría y coordinación crean, editan, publican y
 * eliminan (reglas en service('news')).
 */
class NewsController extends BaseController
{
    private function layout(): array
    {
        return service('tenant')->isStudent() ? $this->studentLayout() : $this->layoutData();
    }

    public function index()
    {
        $svc = service('news');

        return view('news/index', $this->layout() + ['title' => 'Noticias', 'list' => $svc->list(), 'canManage' => $svc->canManage(), 'audiences' => \App\Libraries\News::AUDIENCES]);
    }

    public function show(int $id)
    {
        $svc = service('news');
        $n   = $svc->get($id);
        if (! $n) {
            return redirect()->to('noticias')->with('toast', ['type' => 'error', 'msg' => 'Esa noticia no existe o no está disponible.']);
        }

        return view('news/show', $this->layout() + ['title' => $n['title'], 'n' => $n, 'canManage' => $svc->canManage(), 'audiences' => \App\Libraries\News::AUDIENCES]);
    }

    public function save(?int $id = null)
    {
        $r = service('news')->save($id, $this->request->getPost(), $this->request->getFile('cover'), $this->request->getPost('action') === 'publish');
        if (isset($r['error'])) {
            return redirect()->back()->withInput()->with('toast', ['type' => 'error', 'msg' => $r['error']]);
        }

        return redirect()->to('noticias/' . $r['id'])->with('toast', ['type' => 'ok', 'msg' => $r['published'] ? 'Noticia publicada. Se avisó a su público.' : 'Noticia guardada.']);
    }

    public function delete(int $id)
    {
        $e = service('news')->delete($id);

        return redirect()->to('noticias')->with('toast', $e ? ['type' => 'error', 'msg' => $e] : ['type' => 'ok', 'msg' => 'Noticia eliminada.']);
    }

    /** Portada (solo con acceso a la noticia). */
    public function cover(int $id)
    {
        $svc  = service('news');
        $n    = $svc->get($id);
        $path = $n ? $svc->coverPath($n) : null;
        if (! $path) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=86400')->setBody((string) file_get_contents($path));
    }
}
