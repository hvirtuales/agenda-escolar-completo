<?php

namespace App\Libraries;

/**
 * Comunicaciones de una red educativa hacia sus colegios. Úsela con service('networkComms').
 *
 * - Envían los administradores de la red; los analistas solo ven el historial.
 * - Mensajes: a rectoría y coordinación (staff) y/o docentes (teacher) de los colegios elegidos.
 * - Noticias y eventos: a cualquier público (también estudiantes y familias), pero solo se publican en el feed y la
 *   agenda de cada colegio; la red nunca ve ni recibe listas de personas, solo cifras por colegio.
 * - Cada envío crea un network_posts y una copia por colegio (messages / news / events con network_post_id). Los
 *   colegios ven la marca de la red y no pueden editar ni eliminar esas copias; la red puede retirarlas.
 */
class NetworkComms
{
    public const KINDS         = ['message' => 'Mensaje', 'news' => 'Noticia', 'event' => 'Evento'];
    public const MSG_AUDIENCES = ['staff' => 'Rectoría y coordinación', 'teacher' => 'Docentes'];

    private function db()
    {
        return db_connect();
    }

    private function uid(): int
    {
        return (int) service('auth')->id();
    }

    public function canSend(array $net): bool
    {
        return ($net['role'] ?? '') === 'admin' || service('auth')->isSuperadmin();
    }

    /** Colegios de la red elegidos (ids válidos). Vacío = ninguno. */
    private function schools(int $netId, array $ids): array
    {
        $all = array_map('intval', array_column(service('network')->institutions($netId), 'id'));

        return array_values(array_intersect($all, array_map('intval', $ids)));
    }

    /** Historial de la red con cifras: colegios, destinatarios y leídos (mensajes). */
    public function list(int $netId, ?string $kind = null): array
    {
        $b = $this->db()->table('network_posts p')->select('p.*, u.name AS author')->join('users u', 'u.id = p.author_id', 'left')
            ->where('p.network_id', $netId)->where('p.deleted_at', null);
        if ($kind && isset(self::KINDS[$kind])) {
            $b->where('p.kind', $kind);
        }
        $rows = $b->orderBy('p.id', 'DESC')->limit(200)->get()->getResultArray();
        foreach ($rows as &$r) {
            $r += $this->stats($r);
        }

        return $rows;
    }

    /** Destinatarios y leídos (mensajes) o alcance (noticias y eventos) de un envío. */
    private function stats(array $p): array
    {
        $db = $this->db();
        if ($p['kind'] === 'message') {
            $row = $db->table('message_recipients r')->select('COUNT(*) AS total, SUM(r.read_at IS NOT NULL) AS read_count', false)
                ->join('messages m', 'm.id = r.message_id')->where('m.network_post_id', $p['id'])->get()->getRowArray();

            return ['total' => (int) $row['total'], 'read_count' => (int) $row['read_count']];
        }

        return ['total' => null, 'read_count' => null];
    }

    /** Un envío con su detalle por colegio. */
    public function get(int $netId, int $id): ?array
    {
        $p = $this->db()->table('network_posts p')->select('p.*, u.name AS author')->join('users u', 'u.id = p.author_id', 'left')
            ->where(['p.id' => $id, 'p.network_id' => $netId])->where('p.deleted_at', null)->get()->getRowArray();
        if (! $p) {
            return null;
        }
        $db    = $this->db();
        $table = ['message' => 'messages', 'news' => 'news', 'event' => 'events'][$p['kind']];
        $copy  = $db->table($table . ' c')->select('c.*, i.name AS school')->join('institutions i', 'i.id = c.institution_id')
            ->where('c.network_post_id', $id)->orderBy('i.name')->get()->getResultArray();
        $p['copies'] = [];
        foreach ($copy as $c) {
            if ($p['kind'] === 'message') {
                $c['read_count'] = $db->table('message_recipients')->where('message_id', $c['id'])->where('read_at IS NOT NULL', null, false)->countAllResults();
            } else {
                $c['reach'] = count(News::audienceUsers((int) $c['institution_id'], $c['audience']));
            }
            $p['copies'][] = $c;
        }
        $p['item'] = $copy[0] ?? null; // contenido (igual en todas las copias)

        return $p + $this->stats($p);
    }

    /* ---------- Envíos ---------- */

    private function post(int $netId, string $kind, string $title, string $audience, int $schools): int
    {
        $this->db()->table('network_posts')->insert(['network_id' => $netId, 'kind' => $kind, 'title' => $title, 'audience' => $audience, 'schools' => $schools,
            'author_id' => $this->uid(), 'created_at' => date('Y-m-d H:i:s')]);

        return (int) $this->db()->insertID();
    }

    private static function audience(array $in, array $allowed): string
    {
        $aud = array_values(array_intersect(array_keys($allowed), (array) ($in['audience'] ?? [])));

        return ! $aud || count($aud) === count($allowed) ? 'all' : implode(',', $aud);
    }

    /** Mensaje a directivos y/o docentes de los colegios elegidos. Devuelve [id, count] o [error]. */
    public function sendMessage(array $net, array $in): array
    {
        $netId   = (int) $net['id'];
        $schools = $this->schools($netId, (array) ($in['schools'] ?? []));
        $subject = trim(preg_replace('/\s+/u', ' ', (string) ($in['subject'] ?? '')));
        $body    = trim(str_replace("\r", '', (string) ($in['body'] ?? '')));
        $aud     = self::audience($in, self::MSG_AUDIENCES);
        $keys    = $aud === 'all' ? array_keys(self::MSG_AUDIENCES) : explode(',', $aud);
        if (! $schools) {
            return ['error' => 'Elige al menos un colegio.'];
        }
        if ($subject === '' || mb_strlen($subject) > 160) {
            return ['error' => 'Escribe un asunto de hasta 160 caracteres.'];
        }
        if ($body === '' || mb_strlen($body) > 5000) {
            return ['error' => 'Escribe el mensaje (hasta 5.000 caracteres).'];
        }
        $important = ! empty($in['important']);
        $label     = implode(' y ', array_map(static fn ($k) => self::MSG_AUDIENCES[$k], $keys));
        $db        = $this->db();
        $now       = date('Y-m-d H:i:s');
        $from      = (string) (service('auth')->user()['name'] ?? '') . ' · ' . $net['name'];
        $sent      = [];
        $db->transStart();
        $pid = $this->post($netId, 'message', $subject, implode(',', $keys), count($schools));
        foreach ($schools as $iid) {
            $ids = array_values(array_diff(News::audienceUsers($iid, implode(',', $keys)), [$this->uid()]));
            if (! $ids) {
                continue;
            }
            $db->table('messages')->insert(['institution_id' => $iid, 'sender_id' => $this->uid(), 'subject' => $subject, 'body' => $body, 'audience' => $net['name'] . ' · ' . $label,
                'priority' => $important ? 'important' : 'normal', 'recipients' => count($ids), 'created_at' => $now, 'network_post_id' => $pid]);
            $mid = (int) $db->insertID();
            foreach (array_chunk($ids, 500) as $chunk) {
                $db->table('message_recipients')->insertBatch(array_map(static fn ($u) => ['institution_id' => $iid, 'message_id' => $mid, 'user_id' => $u], $chunk));
            }
            $sent[] = [$iid, $mid, $ids];
        }
        $db->transComplete();
        if (! $db->transStatus() || ! $sent) {
            return ['error' => $sent ? 'No se pudo enviar. Intenta de nuevo.' : 'No hay personas con ese rol en los colegios elegidos.'];
        }
        $count = 0;
        foreach ($sent as [$iid, $mid, $ids]) {
            service('notify')->send($ids, $important ? 'message_important' : 'message', ($important ? 'Importante: ' : 'Mensaje: ') . $subject,
                $from . ' · ' . mb_substr(preg_replace('/\s+/u', ' ', $body), 0, 140), 'mensajes/' . $mid, 'msg:' . $mid, $iid);
            $count += count($ids);
        }
        service('audit')->log('network.message', ['red' => $net['name'], 'asunto' => $subject, 'colegios' => count($sent), 'destinatarios' => $count], null);

        return ['id' => $pid, 'count' => $count];
    }

    /** Noticia publicada en los colegios elegidos (con portada opcional). Devuelve [id] o [error]. */
    public function publishNews(array $net, array $in, ?\CodeIgniter\HTTP\Files\UploadedFile $cover): array
    {
        $netId   = (int) $net['id'];
        $schools = $this->schools($netId, (array) ($in['schools'] ?? []));
        $title   = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')));
        $body    = trim(str_replace("\r", '', (string) ($in['body'] ?? '')));
        if (! $schools) {
            return ['error' => 'Elige al menos un colegio.'];
        }
        if ($title === '' || mb_strlen($title) > 160) {
            return ['error' => 'Escribe un título de hasta 160 caracteres.'];
        }
        if ($body === '' || mb_strlen($body) > 20000) {
            return ['error' => 'Escribe el contenido de la noticia.'];
        }
        $tmp = null;
        if ($cover && $cover->getError() !== UPLOAD_ERR_NO_FILE) {
            if (! $cover->isValid() || ! in_array($cover->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) || $cover->getSize() > 3 * 1024 * 1024 || @getimagesize($cover->getTempName()) === false) {
                return ['error' => 'La portada debe ser JPG, PNG o WebP de hasta 3 MB.'];
            }
            $tmp = WRITEPATH . 'uploads/news/tmp-' . bin2hex(random_bytes(6)) . '.jpg';
            try {
                if (! is_dir(dirname($tmp))) {
                    mkdir(dirname($tmp), 0775, true);
                }
                $img = \Config\Services::image(null, null, false)->withFile($cover->getTempName());
                if ($img->getWidth() > 1200) {
                    $img->resize(1200, (int) round($img->getHeight() * 1200 / $img->getWidth()));
                }
                $img->convert(IMAGETYPE_JPEG)->save($tmp, 82);
            } catch (\Throwable $e) {
                log_message('error', 'Portada de noticia de red: ' . $e->getMessage());

                return ['error' => 'No se pudo procesar la imagen. Prueba con otra.'];
            }
        }
        $aud     = self::audience($in, News::AUDIENCES);
        $summary = mb_substr(trim((string) ($in['summary'] ?? '')), 0, 300) ?: null;
        $now     = date('Y-m-d H:i:s');
        $db      = $this->db();
        $pid     = $this->post($netId, 'news', $title, $aud, count($schools));
        foreach ($schools as $iid) {
            $file = null;
            if ($tmp) {
                $file = bin2hex(random_bytes(8)) . '.jpg';
                if (! is_dir(News::dirOf($iid))) {
                    mkdir(News::dirOf($iid), 0775, true);
                }
                copy($tmp, News::dirOf($iid) . $file);
            }
            $db->table('news')->insert(['institution_id' => $iid, 'author_id' => $this->uid(), 'title' => $title, 'summary' => $summary, 'body' => $body, 'cover_path' => $file,
                'audience' => $aud, 'pinned' => empty($in['pinned']) ? 0 : 1, 'status' => 'published', 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now, 'network_post_id' => $pid]);
            News::notifyAudience($iid, (int) $db->insertID(), $title, $summary ?: 'Publicada por ' . $net['name'], $aud);
        }
        if ($tmp) {
            @unlink($tmp);
        }
        service('audit')->log('network.news', ['red' => $net['name'], 'noticia' => $title, 'colegios' => count($schools)], null);

        return ['id' => $pid];
    }

    /** Evento en el calendario de los colegios elegidos. Devuelve [id] o [error]. */
    public function createEvent(array $net, array $in): array
    {
        $netId   = (int) $net['id'];
        $schools = $this->schools($netId, (array) ($in['schools'] ?? []));
        if (! $schools) {
            return ['error' => 'Elige al menos un colegio.'];
        }
        $row = Events::normalize($in);
        if (isset($row['error'])) {
            return $row;
        }
        $db  = $this->db();
        $pid = $this->post($netId, 'event', $row['title'], $row['audience'], count($schools));
        foreach ($schools as $iid) {
            $db->table('events')->insert($row + ['institution_id' => $iid, 'created_by' => $this->uid(), 'created_at' => date('Y-m-d H:i:s'), 'network_post_id' => $pid]);
            Events::notifyAudience($iid, (int) $db->insertID(), $row);
        }
        service('audit')->log('network.event', ['red' => $net['name'], 'evento' => $row['title'], 'colegios' => count($schools)], null);

        return ['id' => $pid];
    }

    /** Retira un envío de todos los colegios (noticias y eventos se ocultan; los mensajes ya entregados se conservan). */
    public function withdraw(array $net, int $id): ?string
    {
        $p = $this->get((int) $net['id'], $id);
        if (! $p || ! $this->canSend($net)) {
            return 'Ese envío no existe o no puedes retirarlo.';
        }
        $now = date('Y-m-d H:i:s');
        if ($p['kind'] !== 'message') {
            $this->db()->table($p['kind'] === 'news' ? 'news' : 'events')->where('network_post_id', $id)->update(['deleted_at' => $now]);
        }
        $this->db()->table('network_posts')->where('id', $id)->update(['deleted_at' => $now]);
        service('audit')->log('network.withdraw', ['red' => $net['name'], 'envio' => $p['title']], null);

        return null;
    }
}
