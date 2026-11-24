<?php

namespace App\Controllers;

/**
 * Cuaderno del docente (documento maestro 3.3, 4.3 y 6): asignaturas, secciones y páginas reales, editor por bloques
 * con guardado automático (endpoint JSON) y publicación. El panel de IA es el asistente real del cuaderno (Fase 3).
 *
 * El cuaderno se identifica por la oferta: /cuadernos/o{id}/{página}.
 */
class NotebookController extends BaseController
{
    /** Catálogo del panel de asignaturas: coordinación ve todas las ofertas; el docente, las que dicta. */
    private function catalog(): array
    {
        return service('academic')->myCatalog();
    }

    private static function offeringId(?string $slug): ?int
    {
        return ($slug && preg_match('/^o(\d+)$/', $slug, $m)) ? (int) $m[1] : null;
    }

    /** Resuelve la oferta y su cuaderno, comprobando que la persona puede editarlo. */
    private function resolve(?string $slug): array
    {
        $catalog = $this->catalog();
        $items   = \App\Libraries\Academic::flatten($catalog);
        $id      = self::offeringId($slug);
        $item    = null;
        foreach ($items as $i) {
            if ($id === null || $i['id'] === $id) {
                $item = $i;
                break;
            }
        }
        if (! $item || ! service('content')->canEditOffering($item['id'])) {
            return [$catalog, null, null];
        }
        $ws = service('content')->workspaceFor($item['id'], $item['subject'] . ' · ' . $item['group']);

        return [$catalog, $item, $ws];
    }

    public function show(?string $slug = null, ?int $pageId = null)
    {
        [$catalog, $item, $ws] = $this->resolve($slug);
        if (! $item) {
            return $catalog === []
                ? redirect()->to('inicio')->with('toast', ['type' => 'info', 'msg' => 'Aún no tiene asignaturas asignadas en el periodo en curso. Pídale a coordinación que lo asigne a una oferta.'])
                : redirect()->to('cuadernos')->with('toast', ['type' => 'error', 'msg' => 'Ese cuaderno no existe o no es suyo.']);
        }
        $content = service('content');
        $tree    = $content->tree((int) $ws['id']);
        if ($pageId === null) {
            foreach ($tree as $s) {
                if ($s['pages']) {
                    $pageId = (int) $s['pages'][0]['id'];
                    break;
                }
            }
        }
        $page = $pageId ? $content->page($pageId, (int) $ws['id']) : null;
        if ($pageId && ! $page) {
            return redirect()->to('cuadernos/' . $item['slug'])->with('toast', ['type' => 'error', 'msg' => 'Esa página ya no existe en este cuaderno.']);
        }

        return view('notebook/show', $this->layoutData() + [
            'title'    => $item['subject'] . ' · ' . $item['group'],
            'catalog'  => $catalog,
            'notebook' => ['slug' => $item['slug'], 'title' => $item['subject'] . ' · ' . $item['group'], 'group' => $item['group'], 'group_id' => $item['group_id'], 'category' => $item['category'], 'color' => $item['color'], 'sections' => $tree],
            'page'     => $page,
            'thread'   => $page ? service('assistant')->copilotThread((int) $page['id'], (int) service('auth')->id()) : [],
            'labelAi'  => (bool) service('aiQuota')->settings((int) service('tenant')->institutionId())['label_ai'],
            'quota'    => service('aiQuota')->balance(),
            'openAi'   => $this->request->getGet('ia') === '1',
            // Bloques «Reto» y «Evaluación»: las actividades de esta asignatura que se pueden poner en la página.
            'acts'     => service('activities')->forOffering((int) $item['id']),
            // Bloques «Imagen» y «Archivo»: sus archivos y dónde subir nuevos.
            'files'     => $page ? service('notebookFiles')->forWorkspace((int) $ws['id'], array_map(static fn ($b) => $b['data']['file_id'] ?? 0, $page['blocks'])) : [],
            'uploadUrl' => site_url('cuadernos/' . $item['slug'] . '/archivos'),
        ]);
    }

    /* ---------- Acciones ---------- */

    public function createSection(string $slug)
    {
        [, $item, $ws] = $this->resolve($slug);
        if (! $item) {
            return $this->denied();
        }
        if ($e = service('content')->createSection((int) $ws['id'], (string) $this->request->getPost('name'))) {
            return $this->backWith('error', $e);
        }

        return redirect()->back()->with('toast', ['type' => 'ok', 'msg' => 'Sección creada.']);
    }

    public function renameSection(string $slug, int $sectionId)
    {
        [, $item, $ws] = $this->resolve($slug);
        if (! $item) {
            return $this->denied();
        }
        if ($e = service('content')->renameSection($sectionId, (int) $ws['id'], (string) $this->request->getPost('name'))) {
            return $this->backWith('error', $e);
        }

        return redirect()->back()->with('toast', ['type' => 'ok', 'msg' => 'Sección renombrada.']);
    }

    public function deleteSection(string $slug, int $sectionId)
    {
        [, $item, $ws] = $this->resolve($slug);
        if (! $item) {
            return $this->denied();
        }
        if ($e = service('content')->deleteSection($sectionId, (int) $ws['id'])) {
            return $this->backWith('error', $e);
        }

        return redirect()->to('cuadernos/' . $item['slug'])->with('toast', ['type' => 'ok', 'msg' => 'Sección eliminada.']);
    }

    public function createPage(string $slug)
    {
        [, $item, $ws] = $this->resolve($slug);
        if (! $item) {
            return $this->denied();
        }
        $r = service('content')->createPage((int) $ws['id'], (int) $this->request->getPost('section_id'), (string) $this->request->getPost('title'));
        if (isset($r['error'])) {
            return $this->backWith('error', $r['error']);
        }

        return redirect()->to('cuadernos/' . $item['slug'] . '/' . $r['id'])->with('toast', ['type' => 'ok', 'msg' => 'Página creada como borrador.']);
    }

    /** Sube la imagen o el archivo de un bloque (JSON). Solo quien edita este cuaderno. */
    public function upload(string $slug)
    {
        [, $item, $ws] = $this->resolve($slug);
        if (! $item) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => t('Notebook.cantEdit')]);
        }
        $kind = $this->request->getPost('kind') === 'image' ? 'image' : 'file';
        $r    = service('notebookFiles')->store((int) $ws['id'], $kind, $this->request->getFile('file'));

        return isset($r['error'])
            ? $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $r['error']])
            : $this->response->setJSON(['ok' => true] + $r + ['sizeLabel' => \App\Libraries\NotebookFiles::human($r['size'])]);
    }

    /** Guardado automático (JSON): { title, objective, blocks: [{type, data}] }. */
    public function save(string $slug, int $pageId)
    {
        [, $item, $ws] = $this->resolve($slug);
        if (! $item) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'No puede editar este cuaderno.']);
        }
        $in = $this->request->getJSON(true) ?? [];
        $e  = service('content')->savePage($pageId, (int) $ws['id'], (string) ($in['title'] ?? ''), $in['objective'] ?? null, (array) ($in['blocks'] ?? []));
        if ($e) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $e]);
        }

        return $this->response->setJSON(['ok' => true, 'saved_at' => date('c')]);
    }

    public function publish(string $slug, int $pageId)
    {
        [, $item, $ws] = $this->resolve($slug);
        if (! $item || ! service('tenant')->can('content.publish')) {
            return $this->denied();
        }
        if ($e = service('content')->publish($pageId, (int) $ws['id'])) {
            return $this->backWith('error', $e);
        }

        return redirect()->to('cuadernos/' . $item['slug'] . '/' . $pageId)->with('toast', ['type' => 'ok', 'msg' => 'Página publicada para ' . $item['group'] . '.']);
    }

    public function deletePage(string $slug, int $pageId)
    {
        [, $item, $ws] = $this->resolve($slug);
        if (! $item) {
            return $this->denied();
        }
        if ($e = service('content')->deletePage($pageId, (int) $ws['id'])) {
            return $this->backWith('error', $e);
        }

        return redirect()->to('cuadernos/' . $item['slug'])->with('toast', ['type' => 'ok', 'msg' => 'Página eliminada.']);
    }

    private function denied()
    {
        return redirect()->to('cuadernos')->with('toast', ['type' => 'error', 'msg' => 'No puede editar ese cuaderno.']);
    }
}
