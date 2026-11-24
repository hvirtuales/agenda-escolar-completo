<?php

namespace App\Libraries;

/**
 * Recursos de la biblioteca: archivos (PDF, documentos de Office, imágenes, texto) o enlaces. Úsela con
 * service('resources'). Los archivos se guardan en writable/uploads/resources/{institución}/ y solo se descargan por
 * controlador, con sesión en la institución. Subir exige content.edit; eliminar, ser autor o coordinación.
 */
class Resources
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    /** Extensión => tipo MIME aceptado (se comprueba el contenido real del archivo). */
    public const TYPES = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint'], 'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'], 'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'jpg'  => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'], 'gif' => ['image/gif'],
        'txt'  => ['text/plain'], 'csv' => ['text/plain', 'text/csv'],
    ];

    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    private function uid(): int
    {
        return (int) service('auth')->id();
    }

    private function dir(): string
    {
        return WRITEPATH . 'uploads/resources/' . $this->iid() . '/';
    }

    public function canManage(array $r): bool
    {
        return (int) $r['created_by'] === $this->uid() || service('tenant')->can('academic.structure.manage') || service('auth')->isSuperadmin();
    }

    public function list(array $f = []): array
    {
        $q = $this->db()->table('library_resources r')->select('r.id, r.kind, r.title, r.description, r.subject_id, r.url, r.file_name, r.mime, r.size, r.created_by, r.updated_at, s.name AS subject, u.name AS author')
            ->join('subjects s', 's.id = r.subject_id', 'left')->join('users u', 'u.id = r.created_by', 'left')
            ->where('r.institution_id', $this->iid())->where('r.deleted_at', null);
        if (($f['subject'] ?? '') === '0') {
            $q->where('r.subject_id', null);
        } elseif (ctype_digit((string) ($f['subject'] ?? ''))) {
            $q->where('r.subject_id', (int) $f['subject']);
        }
        if (($f['q'] ?? '') !== '') {
            $q->groupStart()->like('r.title', $f['q'])->orLike('r.description', $f['q'])->orLike('r.file_name', $f['q'])->groupEnd();
        }

        return array_map(fn ($r) => $r + ['can_manage' => $this->canManage($r)], $q->orderBy('r.updated_at', 'DESC')->get()->getResultArray());
    }

    public function get(int $id): ?array
    {
        return $this->db()->table('library_resources')->where(['id' => $id, 'institution_id' => $this->iid()])->where('deleted_at', null)->get()->getRowArray();
    }

    /** Crea un recurso (archivo o enlace). Devuelve [id] o [errors]. */
    public function create(array $in, ?\CodeIgniter\HTTP\Files\UploadedFile $file): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        $kind  = ($in['kind'] ?? '') === 'link' ? 'link' : 'file';
        $sid   = (string) ($in['subject_id'] ?? '');
        $err   = [];
        $row   = ['institution_id' => $this->iid(), 'kind' => $kind, 'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 500) ?: null,
            'subject_id' => Templates::validSubject($sid), 'created_by' => $this->uid()];

        if ($kind === 'link') {
            $url = trim((string) ($in['url'] ?? ''));
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url) || mb_strlen($url) > 500) {
                $err['url'] = 'Escriba un enlace completo que empiece por http:// o https://.';
            }
            $row['url'] = $url;
        } else {
            if (! $file || ! $file->isValid() || $file->hasMoved()) {
                $err['file'] = 'Elija el archivo que quiere compartir.';
            } else {
                $ext  = strtolower($file->getClientExtension());
                $mime = $file->getMimeType();
                if (! isset(self::TYPES[$ext]) || ! in_array($mime, self::TYPES[$ext], true)) {
                    $err['file'] = 'Ese tipo de archivo no se puede compartir. Use PDF, Word, Excel, PowerPoint, OpenDocument, imagen o texto.';
                } elseif ($file->getSize() > self::MAX_BYTES) {
                    $err['file'] = 'El archivo pesa más de 20 MB.';
                } elseif (service('demo')->restricted() && $file->getSize() > Demo::MAX_UPLOAD) {
                    $err['file'] = t('App.demoUpload', [2]);
                }
            }
        }
        if ($title === '' && $kind === 'file' && $file && $file->isValid()) {
            $title = pathinfo($file->getClientName(), PATHINFO_FILENAME);
        }
        if ($title === '' || mb_strlen($title) > 160) {
            $err['title'] = 'Escriba un título de hasta 160 caracteres.';
        }
        if ($err) {
            return ['errors' => $err];
        }

        if ($kind === 'file') {
            if (! is_dir($this->dir())) {
                mkdir($this->dir(), 0775, true);
            }
            $stored = bin2hex(random_bytes(12)) . '.' . strtolower($file->getClientExtension());
            $meta   = ['file_path' => $stored, 'file_name' => mb_substr(basename($file->getClientName()), 0, 255), 'mime' => $file->getMimeType(), 'size' => $file->getSize()];
            $file->move($this->dir(), $stored); // después de mover ya no existe el temporal: los datos se leen antes
            $row += $meta;
        }
        $now = date('Y-m-d H:i:s');
        $this->db()->table('library_resources')->insert($row + ['title' => $title, 'created_at' => $now, 'updated_at' => $now]);
        service('audit')->log('library.resource.create', ['recurso' => $title]);

        return ['id' => (int) $this->db()->insertID()];
    }

    public function delete(int $id): ?string
    {
        $r = $this->get($id);
        if (! $r || ! $this->canManage($r)) {
            return 'Ese recurso no existe o no puede eliminarlo.';
        }
        $this->db()->table('library_resources')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        if ($r['file_path'] && basename($r['file_path']) === $r['file_path'] && is_file($this->dir() . $r['file_path'])) {
            @unlink($this->dir() . $r['file_path']);
        }
        service('audit')->log('library.resource.delete', ['recurso' => $r['title']]);

        return null;
    }

    /** Ruta del archivo de un recurso, o null. */
    public function filePath(array $r): ?string
    {
        $p = $this->dir() . (string) $r['file_path'];

        return $r['kind'] === 'file' && $r['file_path'] && basename($r['file_path']) === $r['file_path'] && is_file($p) ? $p : null;
    }
}
