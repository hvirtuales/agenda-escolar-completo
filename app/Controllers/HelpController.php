<?php

namespace App\Controllers;

/**
 * Ayuda integrada: artículos de Config\HelpCenter (service('help')) filtrados por rol, con búsqueda. Es también la base
 * de conocimiento del asistente flotante. Conserva el menú de quien la abre: estudiante (o vista de estudiante),
 * consola de plataforma, red o institución.
 */
class HelpController extends BaseController
{
    private function ctx(): array
    {
        $tenant  = service('tenant');
        $student = $tenant->institution() && ($tenant->isStudent() || str_starts_with((string) $this->request->getGet('desde'), 'estudiante'));

        return match (true) {
            $student                                                    => $this->studentLayout(),
            service('auth')->isSuperadmin() && ! $tenant->institution() => $this->platformLayout(),
            default                                                     => $this->layoutData() + ['quota' => service('aiQuota')->balance()],
        };
    }

    /** Portada de la Ayuda: búsqueda (?q=) o el primer artículo del índice. */
    public function index()
    {
        $help = service('help');
        $q    = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
        if ($q === '') {
            $first = array_key_first($help->articles());

            return $this->render($first);
        }

        return view('help/index', $this->ctx() + ['title' => 'Ayuda', 'index' => $help->index(), 'q' => $q, 'results' => $help->search($q, 10), 'article' => null]);
    }

    public function show(string $slug)
    {
        if (! service('help')->find($slug)) {
            return redirect()->to('ayuda')->with('toast', ['type' => 'info', 'msg' => 'Ese artículo no existe o no está disponible para tu rol.']);
        }

        return $this->render($slug);
    }

    private function render(?string $slug)
    {
        $help = service('help');
        $art  = $slug ? $help->find($slug) : null;

        return view('help/index', $this->ctx() + [
            'title' => $art ? $art['title'] . ' · Ayuda' : 'Ayuda', 'index' => $help->index(), 'q' => '', 'results' => null,
            'article' => $art, 'neighbors' => $art ? $help->neighbors($slug) : [null, null],
        ]);
    }
}
