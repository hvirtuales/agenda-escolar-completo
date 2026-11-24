<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Página pública de cada colegio (agendaescolar.co/{slug}) y su editor en vivo (pagina-publica).
 * Lo público no pide sesión; el editor exige institución activa y tenant.users.manage (rutas en Config/Routes.php).
 */
class SchoolSiteController extends BaseController
{
    private function site(): \App\Libraries\SchoolSite
    {
        return service('schoolSite');
    }

    private function bySlug(string $slug): array
    {
        $inst = $this->site()->institutionBySlug($slug);
        if (! $inst) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $inst;
    }

    /** ¿Quien navega puede editar ESTA institución? (para mostrarle el botón «Editar esta página»). */
    private function editorOf(array $inst): bool
    {
        $auth = service('auth');
        if (! $auth->check()) {
            return false;
        }
        if ($auth->isSuperadmin()) {
            return true;
        }

        return (int) (service('tenant')->institution()['id'] ?? 0) === (int) $inst['id'] && $this->site()->canEdit();
    }

    private function render(array $inst, array $site, bool $edit)
    {
        $iid = (int) $inst['id'];

        return view('site/page', [
            'inst'     => $inst,
            'site'     => $site,
            'edit'     => $edit,
            'news'     => $this->site()->publicNews($iid),
            'offer'    => $this->site()->publicOffer($iid),
            'newRequests' => $edit ? (int) db_connect()->table('site_requests')->where('institution_id', $iid)->where('status', 'new')->countAllResults() : 0,
            'events'   => $this->site()->publicEvents($iid),
            'status'   => $edit ? $this->site()->status($inst) : null,
            'canEdit'  => ! $edit && $this->editorOf($inst),
            'title'    => $inst['name'],
        ]);
    }

    /* ---------- Público ---------- */

    public function show(string $slug)
    {
        $inst = $this->bySlug($slug);

        return $this->render($inst, $this->site()->published($inst), false);
    }

    public function news(string $slug, int $id)
    {
        $inst = $this->bySlug($slug);
        $n    = $this->site()->publicNewsItem((int) $inst['id'], $id);
        if (! $n) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('site/news', ['inst' => $inst, 'site' => $this->site()->published($inst), 'n' => $n, 'title' => $n['title']]);
    }

    public function newsCover(string $slug, int $id)
    {
        $inst = $this->bySlug($slug);
        $n    = $this->site()->publicNewsItem((int) $inst['id'], $id);
        $path = $n && $n['cover_path'] && basename($n['cover_path']) === $n['cover_path'] ? \App\Libraries\News::dirOf((int) $inst['id']) . $n['cover_path'] : null;
        if (! $path || ! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->image200($path);
    }

    public function image(string $slug, string $name)
    {
        $inst = $this->bySlug($slug);
        $path = $this->site()->imagePath($inst, $name);
        if (! $path) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->image200($path);
    }

    /** PDF de la sección «Documentos» (se abre en el navegador). */
    public function document(string $slug, string $name)
    {
        $inst = $this->bySlug($slug);
        $path = $this->site()->documentPath($inst, $name);
        if (! $path) {
            throw PageNotFoundException::forPageNotFound();
        }
        $title = 'documento';
        foreach ($this->site()->published($inst)['documents']['items'] as $d) {
            if ($d['file'] === $name) {
                $title = \App\Libraries\SchoolSite::plain($d['title']);
            }
        }
        $safe = trim((string) preg_replace('/[^A-Za-z0-9 _-]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $title) ?: 'documento')) ?: 'documento';

        return $this->response->setHeader('Content-Type', 'application/pdf')->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Disposition', 'inline; filename="' . $safe . '.pdf"')
            ->setHeader('Cache-Control', 'public, max-age=86400')->setBody((string) file_get_contents($path));
    }

    /** Formulario «Quiero información» de admisiones (público). Máximo 3 por hora por conexión; campo trampa contra bots. */
    public function admission(string $slug)
    {
        $inst = $this->bySlug($slug);
        $back = redirect()->to(site_url($slug) . '#admisiones');
        if ((string) $this->request->getPost('website') !== '') {
            return $back->with('admissionSent', true);
        }
        if (! ($this->site()->published($inst)['admissions']['form'] ?? false)) {
            return $back;
        }
        if (service('throttler')->check('admission-' . $inst['id'] . '-' . md5($this->request->getIPAddress()), 3, HOUR) === false) {
            return $back->withInput()->with('admissionError', t('Site.reqTooMany', [], 'Recibimos varias solicitudes desde tu conexión. Intenta de nuevo en una hora.'));
        }
        $err = $this->site()->admissionRequest($inst, (array) $this->request->getPost(['name', 'phone', 'email', 'grade', 'message']), $this->request->getIPAddress());
        if ($err) {
            return $back->withInput()->with('admissionError', $err);
        }

        return $back->with('admissionSent', true);
    }

    private function image200(string $path)
    {
        return $this->response->setHeader('Content-Type', 'image/jpeg')->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'public, max-age=604800')->setBody((string) file_get_contents($path));
    }

    /* ---------- Editor en vivo ---------- */

    private function current(): array
    {
        $inst = service('tenant')->institution();
        if (! $inst || ! $this->site()->canEdit()) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $inst;
    }

    public function edit()
    {
        $inst = $this->current();

        return $this->render($inst, $this->site()->draft($inst), true);
    }

    public function save()
    {
        $inst = $this->current();
        $in   = $this->request->getJSON(true);
        if (! is_array($in)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => t('Site.saveFailed', [], 'No se pudo guardar. Intenta de nuevo.')]);
        }
        $this->site()->saveDraft($inst, $in);

        return $this->response->setJSON(['ok' => true, 'status' => $this->site()->status($inst), 'csrf' => csrf_hash()]);
    }

    public function publish()
    {
        $inst = $this->current();
        $this->site()->publish($inst);

        return $this->response->setJSON(['ok' => true, 'status' => $this->site()->status($inst), 'csrf' => csrf_hash()]);
    }

    public function discard()
    {
        $inst = $this->current();
        $this->site()->discard($inst);

        return redirect()->to('pagina-publica')->with('toast', ['type' => 'ok', 'msg' => t('Site.discarded', [], 'Se descartaron los cambios: la página volvió a la versión publicada.')]);
    }

    /** Sube un PDF para la sección «Documentos». */
    public function uploadDocument()
    {
        $inst = $this->current();
        $f    = $this->request->getFile('file');
        $r    = $f ? $this->site()->storeDocument($inst, $f) : ['error' => t('Site.docInvalid', [], 'El documento debe ser un PDF de hasta 10 MB.')];
        if (isset($r['error'])) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $r['error'], 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON($r + ['url' => site_url($inst['slug'] . '/documento/' . $r['name']), 'csrf' => csrf_hash()]);
    }

    /** Solicitudes de admisión que llegaron desde la página pública. */
    public function requests()
    {
        $inst   = $this->current();
        $status = (string) $this->request->getGet('estado');

        return view('site/requests', $this->layoutData() + [
            'title'    => 'Solicitudes de admisión',
            'list'     => $this->site()->requests((int) $inst['id'], $status ?: null),
            'status'   => $status,
            'statuses' => \App\Libraries\SchoolSite::REQUEST_STATUSES,
            'counts'   => array_column(db_connect()->table('site_requests')->select('status, COUNT(*) AS n')->where('institution_id', $inst['id'])->groupBy('status')->get()->getResultArray(), 'n', 'status'),
        ]);
    }

    public function requestStatus(int $id)
    {
        $inst = $this->current();
        $ok   = $this->site()->setRequestStatus((int) $inst['id'], $id, (string) $this->request->getPost('status'));

        return redirect()->back()->with('toast', $ok ? ['type' => 'ok', 'msg' => 'Solicitud actualizada.'] : ['type' => 'error', 'msg' => 'No se pudo actualizar la solicitud.']);
    }

    public function upload()
    {
        $inst = $this->current();
        $f    = $this->request->getFile('image');
        $r    = $f ? $this->site()->storeImage($inst, $f) : ['error' => t('Site.imageInvalid', [], 'La foto debe ser JPG, PNG o WebP de hasta 4 MB.')];
        if (isset($r['error'])) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $r['error'], 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON(['name' => $r['name'], 'url' => site_url($inst['slug'] . '/foto/' . $r['name']), 'csrf' => csrf_hash()]);
    }
}
