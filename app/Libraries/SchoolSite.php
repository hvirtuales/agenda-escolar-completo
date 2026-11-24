<?php

namespace App\Libraries;

/**
 * Página pública de cada colegio: agendaescolar.co/{slug}.
 *
 * Contenido en JSON (tabla institution_sites): un borrador que se edita en vivo sobre la misma página y se guarda solo,
 * y la versión publicada, que es la que ve el público. Secciones fijas que se pueden ocultar y reordenar:
 *   hero (portada), about (quiénes somos), offer (oferta académica, sale sola de la estructura académica), admissions
 *   (admisiones, con formulario «Quiero información»), gallery (galería), news, events, documents (PDF descargables), contact.
 * Los textos largos (RICH) admiten formato con la barra flotante (RichText, lista blanca); el resto es texto plano.
 * Las noticias y eventos no se escriben aquí: salen de los que el colegio marca como «públicos» en Noticias y Eventos.
 *
 * Edita quien tiene tenant.users.manage en la institución (rectoría y coordinación) o el superadministrador.
 * Todo texto se guarda como texto plano (se escapa al mostrarlo); las fotos van a writable/uploads/sites/{institución}.
 */
class SchoolSite
{
    public const SECTIONS   = ['hero', 'about', 'offer', 'admissions', 'gallery', 'news', 'events', 'documents', 'contact'];
    public const MAX_VALUES = 6;
    public const MAX_STEPS  = 6;
    public const MAX_PHOTOS = 12;
    public const MAX_DOCS   = 12;
    public const MAX_IMAGE  = 4 * 1024 * 1024;
    public const MAX_DOC    = 10 * 1024 * 1024;
    /** Textos SIN formato: se usan para armar enlaces o botones (teléfono, WhatsApp, correo, mapa) o son el texto del botón. */
    public const PLAIN = ['contact.phone', 'contact.whatsapp', 'contact.email', 'contact.address', 'hero.cta'];
    /** Textos con formato (barra flotante; HTML limpio de RichText): todos los demás. */
    public const RICH = ['hero.title', 'hero.tagline', 'about.heading', 'about.text', 'about.mission', 'about.vision', 'offer.heading', 'offer.intro',
        'admissions.heading', 'admissions.intro', 'admissions.dates', 'gallery.heading', 'news.heading', 'events.heading', 'documents.heading',
        'contact.heading', 'contact.intro', 'contact.hours'];

    /** Primeros segmentos de ruta de la plataforma: ningún colegio puede usarlos como dirección. */
    public const RESERVED = ['login', 'logout', 'idioma', 'demostracion', 'instituciones', 'buscar', 'notificaciones', 'red', 'suplantacion',
        'asistente', 'perfil', 'ayuda', 'plataforma', 'inicio', 'archivos', 'mensajes', 'noticias', 'eventos', 'agenda', 'cuadernos',
        'actividades', 'entregas', 'reportes', 'estudiantes', 'docentes', 'acudiente', 'ia', 'biblioteca', 'ajustes', 'estudiante', 'dev',
        'pagina-publica', 'assets', 'uploads', 'install', 'api', 'admin', 'colegio', 'colegios', 'precios', 'privacidad', 'terminos'];

    /** Longitud máxima de cada texto (las rutas son «sección.campo»). */
    /** Limpia un elemento de lista con formato (sin listas ni saltos: es una sola línea). */
    private static function richItem($v, int $max): string
    {
        $h = \App\Libraries\RichText::clean((string) (is_scalar($v) ? $v : ''), $max);

        return trim((string) preg_replace('#</?(ul|ol|li)>|<br>#', ' ', $h));
    }

    /** Texto visible de un campo con formato (para comprobar si está vacío, atributos alt, metadatos…). */
    public static function plain(?string $html): string
    {
        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private const LIMITS = [
        'hero.title' => 600, 'hero.tagline' => 1200, 'hero.cta' => 40,
        'about.heading' => 400, 'about.text' => 6000, 'about.mission' => 2500, 'about.vision' => 2500, 'about.value' => 60,
        'news.heading' => 400, 'events.heading' => 400,
        'offer.heading' => 400, 'offer.intro' => 1500, 'admissions.heading' => 400, 'admissions.intro' => 3000, 'admissions.dates' => 800, 'admissions.step' => 400,
        'gallery.heading' => 400, 'gallery.caption' => 500, 'documents.heading' => 400, 'documents.title' => 500,
        'contact.heading' => 400, 'contact.intro' => 1000, 'contact.address' => 200, 'contact.phone' => 40, 'contact.whatsapp' => 40,
        'contact.email' => 160, 'contact.hours' => 600,
    ];

    private function db()
    {
        return db_connect();
    }

    public static function dirOf(int $iid): string
    {
        return WRITEPATH . 'uploads/sites/' . $iid . '/';
    }

    /** Institución activa (por dirección pública). Solo instituciones activas tienen página. */
    public function institutionBySlug(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9-]{1,160}$/', $slug) || in_array($slug, self::RESERVED, true)) {
            return null;
        }

        return $this->db()->table('institutions')->where('slug', $slug)->where('status', 'active')->where('deleted_at', null)->get()->getRowArray() ?: null;
    }

    public function canEdit(): bool
    {
        return service('tenant')->can('tenant.users.manage') || service('auth')->isSuperadmin();
    }

    /** Contenido de partida, armado con los datos que ya tiene la institución. */
    public static function defaults(array $inst): array
    {
        return [
            'sections' => array_map(static fn ($k) => ['key' => $k, 'visible' => true], self::SECTIONS),
            'hero'     => ['title' => $inst['name'], 'tagline' => 'Una comunidad que aprende, crea y crece unida.', 'cta' => 'Conócenos', 'image' => null],
            'about'    => [
                'heading' => 'Quiénes somos',
                'text'    => 'Somos una institución educativa comprometida con la formación integral de nuestros estudiantes, en un ambiente de respeto, curiosidad y acompañamiento cercano a las familias.',
                'mission' => '', 'vision' => '', 'values' => ['Respeto', 'Responsabilidad', 'Creatividad'], 'image' => null,
            ],
            'offer'      => ['heading' => 'Oferta académica', 'intro' => 'Estos son los grados y las asignaturas que ofrecemos este año.'],
            'admissions' => ['heading' => 'Admisiones', 'intro' => 'Te acompañamos en todo el proceso de matrícula. Déjanos tus datos y te contactamos para resolver tus preguntas y agendar una visita.',
                'dates' => '', 'steps' => ['Solicita información', 'Visita el colegio', 'Entrega los documentos', 'Formaliza la matrícula'], 'form' => true],
            'gallery'    => ['heading' => 'Nuestro colegio', 'items' => []],
            'documents'  => ['heading' => 'Documentos institucionales', 'items' => []],
            'news'    => ['heading' => 'Noticias'],
            'events'  => ['heading' => 'Próximos eventos'],
            'contact' => [
                'heading' => 'Contáctanos', 'intro' => 'Con gusto resolvemos tus preguntas sobre admisiones, horarios y vida escolar.',
                'address' => '', 'phone' => (string) ($inst['phone'] ?? ''), 'whatsapp' => '', 'email' => (string) ($inst['email'] ?? ''),
                'hours' => 'Lunes a viernes, 7:00 a. m. a 3:00 p. m.',
            ],
        ];
    }

    /** Fila de la tabla (o null si el colegio aún no ha editado su página). */
    private function row(int $iid): ?array
    {
        return $this->db()->table('institution_sites')->where('institution_id', $iid)->get()->getRowArray() ?: null;
    }

    /** Borrador para editar: el guardado o el de partida. */
    public function draft(array $inst): array
    {
        $row = $this->row((int) $inst['id']);

        return self::clean($row ? (json_decode($row['draft'], true) ?: []) : [], $inst);
    }

    /** Lo que ve el público: lo publicado o, si nunca se ha publicado, el contenido de partida. */
    public function published(array $inst): array
    {
        $row = $this->row((int) $inst['id']);

        return self::clean($row && $row['published'] ? (json_decode($row['published'], true) ?: []) : [], $inst);
    }

    /** Estado para la barra del editor: hay cambios sin publicar, fecha de publicación. */
    public function status(array $inst): array
    {
        $row = $this->row((int) $inst['id']);

        return [
            'publishedAt' => $row['published_at'] ?? null,
            'pending'     => $row !== null && ($row['published'] === null || json_decode($row['draft'], true) != json_decode($row['published'], true)),
        ];
    }

    public function saveDraft(array $inst, array $in): array
    {
        $data = self::clean($in, $inst);
        $db   = $this->db();
        $now  = date('Y-m-d H:i:s');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($this->row((int) $inst['id'])) {
            $db->table('institution_sites')->where('institution_id', $inst['id'])->update(['draft' => $json, 'updated_at' => $now, 'updated_by' => service('auth')->id()]);
        } else {
            $db->table('institution_sites')->insert(['institution_id' => $inst['id'], 'draft' => $json, 'updated_at' => $now, 'updated_by' => service('auth')->id()]);
        }
        $this->removeUnusedImages($inst);

        return $data;
    }

    public function publish(array $inst): void
    {
        $data = $this->draft($inst);
        $this->saveDraft($inst, $data); // asegura la fila
        $this->db()->table('institution_sites')->where('institution_id', $inst['id'])->update([
            'published' => json_encode($data, JSON_UNESCAPED_UNICODE), 'published_at' => date('Y-m-d H:i:s'), 'published_by' => service('auth')->id(),
        ]);
        $this->removeUnusedImages($inst);
        service('audit')->log('site.publish', ['página' => $inst['slug']]);
    }

    /** Vuelve el borrador a lo publicado (descarta los cambios sin publicar). */
    public function discard(array $inst): void
    {
        $row = $this->row((int) $inst['id']);
        if ($row) {
            $this->db()->table('institution_sites')->where('institution_id', $inst['id'])->update(['draft' => $row['published'] ?? json_encode(self::defaults($inst), JSON_UNESCAPED_UNICODE)]);
            $this->removeUnusedImages($inst);
        }
    }

    /**
     * Normaliza cualquier entrada a la estructura completa: solo claves conocidas, textos planos recortados a su
     * límite, secciones en un orden válido sin repetir, fotos solo si son archivos de esta institución.
     */
    public static function clean(array $in, array $inst): array
    {
        $def = self::defaults($inst);
        $out = $def;
        $str = static function ($v, string $limitKey, bool $multiline = false): string {
            $v = str_replace("\r", '', (string) (is_scalar($v) ? $v : ''));
            $v = $multiline ? preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/u', ' ', $v)) : preg_replace('/\s+/u', ' ', $v);

            return mb_substr(trim(strip_tags((string) $v)), 0, self::LIMITS[$limitKey]); // texto plano: sin etiquetas
        };

        // Secciones: orden recibido (solo claves válidas, sin repetir) y las que falten al final.
        $seen = [];
        $secs = [];
        foreach ((array) ($in['sections'] ?? []) as $s) {
            $k = is_array($s) ? (string) ($s['key'] ?? '') : '';
            if (in_array($k, self::SECTIONS, true) && ! isset($seen[$k])) {
                $seen[$k] = true;
                $secs[]   = ['key' => $k, 'visible' => ! empty($s['visible'])];
            }
        }
        foreach (self::SECTIONS as $k) {
            if (! isset($seen[$k])) {
                $at = array_search('contact', array_column($secs, 'key'), true);
                $at === false || $k === 'contact' ? $secs[] = ['key' => $k, 'visible' => true] : array_splice($secs, $at, 0, [['key' => $k, 'visible' => true]]);
            }
        }
        $out['sections'] = $secs;

        $multi = ['about.text', 'about.mission', 'about.vision', 'contact.intro', 'hero.tagline'];
        foreach (self::LIMITS as $path => $_) {
            [$sec, $field] = explode('.', $path);
            if (in_array($path, ['about.value', 'admissions.step', 'gallery.caption', 'documents.title'], true) || ! isset($in[$sec]) || ! is_array($in[$sec]) || ! array_key_exists($field, $in[$sec])) {
                continue;
            }
            $out[$sec][$field] = in_array($path, self::RICH, true)
                ? \App\Libraries\RichText::clean((string) (is_scalar($in[$sec][$field]) ? $in[$sec][$field] : ''), self::LIMITS[$path])
                : $str($in[$sec][$field], $path, in_array($path, $multi, true));
        }
        // Admisiones: pasos (lista corta) y si se muestra el formulario.
        if (isset($in['admissions']['steps']) && is_array($in['admissions']['steps'])) {
            $out['admissions']['steps'] = array_slice(array_values(array_filter(array_map(static fn ($v) => self::richItem($v, self::LIMITS['admissions.step']), $in['admissions']['steps']), static fn ($v) => self::plain($v) !== '')), 0, self::MAX_STEPS);
        }
        if (isset($in['admissions']['form'])) {
            $out['admissions']['form'] = (bool) $in['admissions']['form'];
        }
        // Galería y documentos: solo archivos de esta institución.
        $dir = self::dirOf((int) $inst['id']);
        if (isset($in['gallery']['items']) && is_array($in['gallery']['items'])) {
            $items = [];
            foreach ($in['gallery']['items'] as $it) {
                $img = is_array($it) ? (string) ($it['image'] ?? '') : '';
                if (preg_match('/^[a-f0-9]{16}\.(jpg|webp)$/', $img) && is_file($dir . $img) && count($items) < self::MAX_PHOTOS) {
                    $items[] = ['image' => $img, 'caption' => self::richItem($it['caption'] ?? '', self::LIMITS['gallery.caption'])];
                }
            }
            $out['gallery']['items'] = $items;
        }
        if (isset($in['documents']['items']) && is_array($in['documents']['items'])) {
            $items = [];
            foreach ($in['documents']['items'] as $it) {
                $file = is_array($it) ? (string) ($it['file'] ?? '') : '';
                if (preg_match('/^[a-f0-9]{16}\.pdf$/', $file) && is_file($dir . $file) && count($items) < self::MAX_DOCS) {
                    $t       = self::richItem($it['title'] ?? '', self::LIMITS['documents.title']);
                    $items[] = ['file' => $file, 'title' => self::plain($t) !== '' ? $t : 'Documento', 'size' => (int) filesize($dir . $file)];
                }
            }
            $out['documents']['items'] = $items;
        }
        if (isset($in['about']['values']) && is_array($in['about']['values'])) {
            $vals = [];
            foreach ($in['about']['values'] as $v) {
                $v = self::richItem($v, 400);
                if (self::plain($v) !== '' && count($vals) < self::MAX_VALUES) {
                    $vals[] = $v;
                }
            }
            $out['about']['values'] = $vals;
        }
        // Título de portada vacío: el nombre del colegio.
        if (self::plain($out['hero']['title']) === '') {
            $out['hero']['title'] = esc($inst['name']);
        }
        // Correo de contacto: solo si es válido.
        if ($out['contact']['email'] !== '' && ! filter_var($out['contact']['email'], FILTER_VALIDATE_EMAIL)) {
            $out['contact']['email'] = '';
        }
        foreach (['phone', 'whatsapp'] as $f) {
            $out['contact'][$f] = preg_replace('/[^0-9 +()-]/', '', $out['contact'][$f]);
        }
        foreach (['hero', 'about'] as $sec) {
            $img                  = $in[$sec]['image'] ?? null;
            $out[$sec]['image'] = is_string($img) && preg_match('/^[a-f0-9]{16}\.(jpg|webp)$/', $img) && is_file(self::dirOf((int) $inst['id']) . $img) ? $img : null;
        }

        return $out;
    }

    /* ---------- Fotos ---------- */

    /** Guarda una foto (JPG/PNG/WebP de hasta 4 MB), reducida a 1920 px de ancho. Devuelve [name] o [error]. */
    public function storeImage(array $inst, \CodeIgniter\HTTP\Files\UploadedFile $f): array
    {
        if (! $f->isValid() || ! in_array($f->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) || $f->getSize() > self::MAX_IMAGE || @getimagesize($f->getTempName()) === false) {
            return ['error' => t('Site.imageInvalid', [], 'La foto debe ser JPG, PNG o WebP de hasta 4 MB.')];
        }
        $dir = self::dirOf((int) $inst['id']);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = bin2hex(random_bytes(8)) . '.jpg';
        try {
            $img = \Config\Services::image(null, null, false)->withFile($f->getTempName());
            if ($img->getWidth() > 1920) {
                $img->resize(1920, (int) round($img->getHeight() * 1920 / $img->getWidth()));
            }
            $img->convert(IMAGETYPE_JPEG)->save($dir . $name, 82);
        } catch (\Throwable $e) {
            log_message('error', 'Foto de la página pública: ' . $e->getMessage());

            return ['error' => t('Site.imageFailed', [], 'No se pudo procesar la foto. Prueba con otra.')];
        }

        return ['name' => $name];
    }

    public function imagePath(array $inst, string $name): ?string
    {
        $p = self::dirOf((int) $inst['id']) . $name;

        return preg_match('/^[a-f0-9]{16}\.(jpg|webp)$/', $name) && is_file($p) ? $p : null;
    }

    /** Borra las fotos que ya no usan ni el borrador ni lo publicado (las subidas hace menos de un día se conservan). */
    private function removeUnusedImages(array $inst): void
    {
        $row  = $this->row((int) $inst['id']);
        $used = [];
        foreach ([$row['draft'] ?? null, $row['published'] ?? null] as $j) {
            $d = $j ? (json_decode($j, true) ?: []) : [];
            foreach (['hero', 'about'] as $s) {
                if (! empty($d[$s]['image'])) {
                    $used[$d[$s]['image']] = true;
                }
            }
            foreach ($d['gallery']['items'] ?? [] as $it) {
                $used[$it['image'] ?? ''] = true;
            }
            foreach ($d['documents']['items'] ?? [] as $it) {
                $used[$it['file'] ?? ''] = true;
            }
        }
        foreach (glob(self::dirOf((int) $inst['id']) . '*.{jpg,webp,pdf}', GLOB_BRACE) ?: [] as $f) {
            if (! isset($used[basename($f)]) && filemtime($f) < time() - DAY) {
                @unlink($f);
            }
        }
    }

    /* ---------- Documentos (PDF) ---------- */

    /** Guarda un PDF (hasta 10 MB). Devuelve [name, title, size] o [error]. */
    public function storeDocument(array $inst, \CodeIgniter\HTTP\Files\UploadedFile $f): array
    {
        $head = $f->isValid() ? (string) file_get_contents($f->getTempName(), false, null, 0, 5) : '';
        if (! $f->isValid() || strtolower($f->getClientExtension()) !== 'pdf' || $head !== '%PDF-' || $f->getSize() > self::MAX_DOC) {
            return ['error' => t('Site.docInvalid', [], 'El documento debe ser un PDF de hasta 10 MB.')];
        }
        $dir = self::dirOf((int) $inst['id']);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = bin2hex(random_bytes(8)) . '.pdf';
        $f->move($dir, $name);
        $title = trim((string) preg_replace('/[_-]+/', ' ', pathinfo($f->getClientName(), PATHINFO_FILENAME)));

        return ['name' => $name, 'title' => mb_substr($title !== '' ? mb_convert_case($title, MB_CASE_TITLE) : 'Documento', 0, 120), 'size' => (int) filesize($dir . $name)];
    }

    public function documentPath(array $inst, string $name): ?string
    {
        $p = self::dirOf((int) $inst['id']) . $name;

        return preg_match('/^[a-f0-9]{16}\.pdf$/', $name) && is_file($p) ? $p : null;
    }

    /* ---------- Oferta académica (sale de la estructura del periodo actual) ---------- */

    /** ['levels' => [[nivel, [grupos]]], 'subjects' => [nombres]] del periodo actual de la institución. */
    public function publicOffer(int $iid): array
    {
        $db     = $this->db();
        $period = $db->table('academic_periods')->where('institution_id', $iid)->orderBy('is_current', 'DESC')->orderBy('starts_on', 'DESC')->get()->getRowArray();
        if (! $period) {
            return ['levels' => [], 'subjects' => []];
        }
        $levels = [];
        foreach ($db->table('class_groups g')->select('l.name AS level, g.name')->join('levels l', 'l.id = g.level_id')
            ->where('g.institution_id', $iid)->where('g.period_id', $period['id'])->orderBy('l.sort_order')->orderBy('g.name')->get()->getResultArray() as $g) {
            $levels[$g['level']][] = $g['name'];
        }
        $subjects = array_column($db->table('subject_offerings o')->select('s.name')->distinct()->join('subjects s', 's.id = o.subject_id')
            ->where('o.institution_id', $iid)->where('o.period_id', $period['id'])->orderBy('s.name')->get()->getResultArray(), 'name');

        return ['levels' => array_map(static fn ($k, $v) => [$k, $v], array_keys($levels), $levels), 'subjects' => $subjects];
    }

    /* ---------- Solicitudes de admisión ---------- */

    /** Guarda una solicitud «Quiero información» y avisa a rectoría y coordinación. Devuelve un error o null. */
    public function admissionRequest(array $inst, array $in, string $ip): ?string
    {
        $name  = trim((string) preg_replace('/\s+/u', ' ', (string) ($in['name'] ?? '')));
        $phone = trim((string) preg_replace('/[^0-9 +()-]/', '', (string) ($in['phone'] ?? '')));
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        if ($name === '' || mb_strlen($name) > 120) {
            return t('Site.reqName', [], 'Escribe tu nombre.');
        }
        if ($phone === '' && $email === '') {
            return t('Site.reqContact', [], 'Escribe un teléfono o un correo para contactarte.');
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return t('Site.reqEmail', [], 'Revisa el correo: no parece válido.');
        }
        $now = date('Y-m-d H:i:s');
        $this->db()->table('site_requests')->insert(['institution_id' => $inst['id'], 'name' => $name, 'phone' => $phone ?: null, 'email' => $email ?: null,
            'grade' => mb_substr(trim((string) ($in['grade'] ?? '')), 0, 60) ?: null, 'message' => mb_substr(trim((string) ($in['message'] ?? '')), 0, 1000) ?: null,
            'status' => 'new', 'ip_address' => $ip, 'created_at' => $now, 'updated_at' => $now]);
        $managers = array_column($this->db()->table('institution_users hu')->select('hu.user_id')->join('role_permissions rp', 'rp.role_id = hu.role_id')
            ->where('hu.institution_id', $inst['id'])->where('hu.status', 'active')->where('rp.permission', 'tenant.users.manage')->get()->getResultArray(), 'user_id');
        if ($managers) {
            service('notify')->send(array_map('intval', $managers), 'admission', 'Nueva solicitud de admisión: ' . $name,
                trim(($in['grade'] ?? '') !== '' ? 'Grado de interés: ' . $in['grade'] : 'Desde la página pública'), 'pagina-publica/solicitudes', null, (int) $inst['id']);
        }

        return null;
    }

    public const REQUEST_STATUSES = ['new' => 'Nueva', 'contacted' => 'Contactada', 'enrolled' => 'Matriculada', 'closed' => 'Cerrada'];

    public function requests(int $iid, ?string $status = null): array
    {
        $b = $this->db()->table('site_requests')->where('institution_id', $iid);
        if ($status && isset(self::REQUEST_STATUSES[$status])) {
            $b->where('status', $status);
        }

        return $b->orderBy('created_at', 'DESC')->limit(300)->get()->getResultArray();
    }

    public function setRequestStatus(int $iid, int $id, string $status): bool
    {
        if (! isset(self::REQUEST_STATUSES[$status])) {
            return false;
        }
        $this->db()->table('site_requests')->where('institution_id', $iid)->where('id', $id)
            ->update(['status' => $status, 'handled_by' => service('auth')->id(), 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->db()->affectedRows() > 0;
    }

    /* ---------- Noticias y eventos públicos ---------- */

    public function publicNews(int $iid, int $limit = 3): array
    {
        return $this->db()->table('news')->select('id, title, summary, body, cover_path, published_at, pinned')
            ->where('institution_id', $iid)->where('is_public', 1)->where('status', 'published')->where('deleted_at', null)
            ->orderBy('pinned', 'DESC')->orderBy('published_at', 'DESC')->limit($limit)->get()->getResultArray();
    }

    public function publicNewsItem(int $iid, int $id): ?array
    {
        return $this->db()->table('news')->where('id', $id)->where('institution_id', $iid)->where('is_public', 1)
            ->where('status', 'published')->where('deleted_at', null)->get()->getRowArray() ?: null;
    }

    public function publicEvents(int $iid, int $limit = 4): array
    {
        return $this->db()->table('events')->select('id, title, description, location, category, starts_at, ends_at, all_day')
            ->where('institution_id', $iid)->where('is_public', 1)->where('deleted_at', null)
            ->groupStart()->where('starts_at >=', date('Y-m-d 00:00:00'))->orWhere('ends_at >=', date('Y-m-d 00:00:00'))->groupEnd()
            ->orderBy('starts_at')->limit($limit)->get()->getResultArray();
    }
}
