<?php

namespace App\Libraries;

/**
 * Noticias y boletines de la institución. Úsela con service('news').
 *
 * - Publican rectoría y coordinación (tenant.users.manage); el resto lee las publicadas para su público.
 * - Públicos: teacher (docentes), student (estudiantes), guardian (familias), staff (rectoría y coordinación) o all.
 * - Al publicar por primera vez se avisa al público elegido. Las destacadas salen primero.
 * - Portada: JPG, PNG o WebP de hasta 3 MB, reducida a 1200 px de ancho, en writable/uploads/news/{institución}.
 */
class News
{
    public const AUDIENCES = ['teacher' => 'Docentes', 'student' => 'Estudiantes', 'guardian' => 'Familias', 'staff' => 'Rectoría y coordinación'];

    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    private function dir(): string
    {
        return self::dirOf($this->iid());
    }

    public static function dirOf(int $iid): string
    {
        return WRITEPATH . 'uploads/news/' . $iid . '/';
    }

    public function canManage(): bool
    {
        return service('tenant')->can('tenant.users.manage') || service('auth')->isSuperadmin();
    }

    /** Público de quien lee (null = todo, para quien publica). */
    private function viewerKey(): ?string
    {
        $t = service('tenant');

        return match (true) {
            $this->canManage() => null,
            $t->isStudent()    => 'student',
            $t->isGuardian()   => 'guardian',
            $t->can('content.edit') => 'teacher',
            default            => 'none',
        };
    }

    /** Noticias visibles: publicadas para su público (quien publica ve también los borradores). Destacadas primero. */
    public function list(int $limit = 50): array
    {
        $key = $this->viewerKey();
        $b   = $this->base()->where('n.institution_id', $this->iid())->where('n.deleted_at', null);
        if ($key !== null) {
            $b->where('n.status', 'published')->where('n.published_at <=', date('Y-m-d H:i:s'))
                ->groupStart()->where('n.audience', 'all')->orLike('n.audience', $key)->groupEnd();
        }

        return $b->orderBy("CASE WHEN n.status = 'draft' THEN 0 ELSE 1 END", '', false)->orderBy('n.pinned', 'DESC')
            ->orderBy('n.published_at', 'DESC')->orderBy('n.id', 'DESC')->limit($limit)->get()->getResultArray();
    }

    /** Consulta base: con autor y, si viene de la red, su nombre (network_name). */
    private function base()
    {
        return $this->db()->table('news n')->select('n.*, u.name AS author, nw.name AS network_name')->join('users u', 'u.id = n.author_id', 'left')
            ->join('network_posts np', 'np.id = n.network_post_id', 'left')->join('networks nw', 'nw.id = np.network_id', 'left');
    }

    public function get(int $id): ?array
    {
        foreach ($this->base()
            ->where('n.institution_id', $this->iid())->where('n.id', $id)->where('n.deleted_at', null)->get()->getResultArray() as $n) {
            $key = $this->viewerKey();
            if ($key === null || ($n['status'] === 'published' && ($n['audience'] === 'all' || in_array($key, explode(',', $n['audience']), true)))) {
                return $n;
            }
        }

        return null;
    }

    /** Guarda (crea o edita). $publish: publicar ahora. Devuelve [id] o [error]. */
    public function save(?int $id, array $in, ?\CodeIgniter\HTTP\Files\UploadedFile $cover, bool $publish): array
    {
        if (! $this->canManage()) {
            return ['error' => 'Solo rectoría y coordinación publican noticias.'];
        }
        $old = $id ? $this->get($id) : null;
        if ($id && ! $old) {
            return ['error' => 'Esa noticia no existe.'];
        }
        if ($old && $old['network_post_id']) {
            return ['error' => 'Es una noticia de la red: solo la red la modifica.'];
        }
        $title = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')));
        $body  = trim(str_replace("\r", '', (string) ($in['body'] ?? '')));
        if ($title === '' || mb_strlen($title) > 160) {
            return ['error' => 'Escribe un título de hasta 160 caracteres.'];
        }
        if ($body === '' || mb_strlen($body) > 20000) {
            return ['error' => 'Escribe el contenido de la noticia.'];
        }
        $aud = array_values(array_intersect(array_keys(self::AUDIENCES), (array) ($in['audience'] ?? [])));
        $row = [
            'title' => $title, 'summary' => mb_substr(trim((string) ($in['summary'] ?? '')), 0, 300) ?: null, 'body' => $body,
            'audience' => ! $aud || count($aud) === count(self::AUDIENCES) ? 'all' : implode(',', $aud), 'pinned' => empty($in['pinned']) ? 0 : 1, 'is_public' => empty($in['is_public']) ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($cover && $cover->getError() !== UPLOAD_ERR_NO_FILE) {
            $c = $this->storeCover($cover);
            if (isset($c['error'])) {
                return $c;
            }
            $row['cover_path'] = $c['name'];
        }
        $firstPublish = $publish && (! $old || $old['status'] !== 'published');
        if ($publish) {
            $row += ['status' => 'published'] + ($firstPublish ? ['published_at' => date('Y-m-d H:i:s')] : []);
        } elseif (! $old) {
            $row['status'] = 'draft';
        }
        $db = $this->db();
        if ($old) {
            $db->table('news')->where('id', $id)->update($row);
            if (isset($row['cover_path']) && $old['cover_path']) {
                $this->removeCover($old['cover_path']);
            }
        } else {
            $db->table('news')->insert($row + ['institution_id' => $this->iid(), 'author_id' => service('auth')->id(), 'created_at' => date('Y-m-d H:i:s')]);
            $id = (int) $db->insertID();
        }
        if ($firstPublish) {
            $this->notify($id, $title, $row['summary'], $row['audience']);
            service('audit')->log('news.publish', ['noticia' => $title]);
        }

        return ['id' => $id, 'published' => $firstPublish];
    }

    public function delete(int $id): ?string
    {
        $n = $this->get($id);
        if (! $n || ! $this->canManage() || $n['network_post_id']) {
            return 'Esa noticia no existe o no puedes eliminarla.';
        }
        $this->db()->table('news')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->removeCover($n['cover_path']);
        service('audit')->log('news.delete', ['noticia' => $n['title']]);

        return null;
    }

    /** Destinatarios del aviso según el público. */
    private function notify(int $id, string $title, ?string $summary, string $audience): void
    {
        self::notifyAudience($this->iid(), $id, $title, $summary ?: 'Publicada por ' . (service('tenant')->institution()['name'] ?? ''), $audience);
    }

    /** Avisa al público de una noticia en un colegio (también lo usa la red). */
    public static function notifyAudience(int $iid, int $id, string $title, string $body, string $audience): void
    {
        $ids = array_values(array_diff(self::audienceUsers($iid, $audience), [(int) service('auth')->id()]));
        service('notify')->send($ids, 'news', 'Noticia: ' . $title, $body, 'noticias/' . $id, 'news:' . $id, $iid);
    }

    /** Miembros activos de un colegio para un público («all» o claves separadas por coma; staff = rectoría y coordinación). */
    public static function audienceUsers(int $iid, string $audience): array
    {
        $keys = $audience === 'all' ? array_keys(self::AUDIENCES) : explode(',', $audience);
        $tpl  = [];
        foreach ($keys as $k) {
            $tpl = array_merge($tpl, $k === 'staff' ? ['admin', 'coordinator'] : [$k]);
        }

        return array_map('intval', array_column(db_connect()->table('institution_users m')->select('m.user_id')->join('roles r', 'r.id = m.role_id')
            ->where('m.institution_id', $iid)->where('m.status', 'active')->whereIn('r.template_key', $tpl ?: ['none'])->get()->getResultArray(), 'user_id'));
    }

    /* ---------- Portada ---------- */

    private function storeCover(\CodeIgniter\HTTP\Files\UploadedFile $f): array
    {
        if (! $f->isValid() || ! in_array($f->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) || $f->getSize() > 3 * 1024 * 1024 || @getimagesize($f->getTempName()) === false) {
            return ['error' => 'La portada debe ser JPG, PNG o WebP de hasta 3 MB.'];
        }
        if (! is_dir($this->dir())) {
            mkdir($this->dir(), 0775, true);
        }
        $name = bin2hex(random_bytes(8)) . '.jpg';
        try {
            $img = \Config\Services::image(null, null, false)->withFile($f->getTempName());
            if ($img->getWidth() > 1200) {
                $img->resize(1200, (int) round($img->getHeight() * 1200 / $img->getWidth()));
            }
            $img->convert(IMAGETYPE_JPEG)->save($this->dir() . $name, 82);
        } catch (\Throwable $e) {
            log_message('error', 'Portada de noticia: ' . $e->getMessage());

            return ['error' => 'No se pudo procesar la imagen. Prueba con otra.'];
        }

        return ['name' => $name];
    }

    private function removeCover(?string $name): void
    {
        if ($name && basename($name) === $name && is_file($this->dir() . $name)) {
            @unlink($this->dir() . $name);
        }
    }

    public function coverPath(array $n): ?string
    {
        $p = $this->dir() . (string) $n['cover_path'];

        return $n['cover_path'] && basename($n['cover_path']) === $n['cover_path'] && is_file($p) ? $p : null;
    }
}
