<?php

namespace App\Libraries;

/**
 * Archivos de los bloques «Imagen» y «Archivo» del cuaderno. Úsela con service('notebookFiles').
 *
 * Seguridad:
 * - Tipos permitidos por extensión Y por el contenido real del archivo (mismas reglas que la Biblioteca, Resources::TYPES).
 *   Imágenes: JPG, PNG, WebP o GIF que getimagesize() reconozca (nunca SVG: puede llevar código).
 * - Se guardan con nombre aleatorio en writable/uploads/notebooks/{institución}/, fuera de la carpeta web.
 * - Solo se entregan por controlador (FilesController) a quien puede leer la asignatura del cuaderno: su docente o
 *   coordinación, los estudiantes del grupo, o la vista de estudiante del docente. Con nosniff y una CSP que no ejecuta nada.
 * - Cuentan para el almacenamiento del plan (plans.storage_gb; 0 = sin límite), junto con los recursos de la Biblioteca.
 */
class NotebookFiles
{
    public const MAX_IMAGE = 8 * 1024 * 1024;
    public const MAX_FILE  = Resources::MAX_BYTES; // 20 MB
    public const IMAGE_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    private function dir(): string
    {
        return WRITEPATH . 'uploads/notebooks/' . $this->iid() . '/';
    }

    /** Bytes usados por la institución (archivos de cuadernos + recursos de la biblioteca). */
    public function usedBytes(): int
    {
        $db = db_connect();
        $a  = (int) ($db->table('page_files')->selectSum('size', 's')->where('institution_id', $this->iid())->where('deleted_at', null)->get()->getRow()->s ?? 0);
        $b  = (int) ($db->table('library_resources')->selectSum('size', 's')->where('institution_id', $this->iid())->where('deleted_at', null)->get()->getRow()->s ?? 0);

        return $a + $b;
    }

    /**
     * Sube un archivo al cuaderno ($workspaceId; quien llama ya comprobó que puede editarlo).
     * Devuelve ['id', 'kind', 'name', 'size', 'mime', 'url'] o ['error' => mensaje].
     */
    public function store(int $workspaceId, string $kind, ?\CodeIgniter\HTTP\Files\UploadedFile $f): array
    {
        if (! $f || ! $f->isValid() || $f->hasMoved()) {
            return ['error' => t('Notebook.fileChoose')];
        }
        $ext  = strtolower($f->getClientExtension());
        $mime = (string) $f->getMimeType();
        $size = (int) $f->getSize();
        if ($kind === 'image') {
            if (($ext === 'jpeg' ? 'image/jpeg' : (self::IMAGE_TYPES[$ext] ?? null)) !== $mime || @getimagesize($f->getTempName()) === false) {
                return ['error' => t('Notebook.imageType')];
            }
            if ($size > self::MAX_IMAGE) {
                return ['error' => t('Notebook.imageTooBig', [8])];
            }
        } else {
            if (! isset(Resources::TYPES[$ext]) || ! in_array($mime, Resources::TYPES[$ext], true)) {
                return ['error' => t('Notebook.fileType')];
            }
            if ($size > self::MAX_FILE) {
                return ['error' => t('Notebook.fileTooBig', [20])];
            }
        }
        if (service('demo')->restricted() && $size > Demo::MAX_UPLOAD) {
            return ['error' => t('App.demoUpload', [2])];
        }
        $gb = (int) (service('aiQuota')->plan($this->iid())['storage_gb'] ?? 0);
        if ($gb > 0 && $this->usedBytes() + $size > $gb * 1024 ** 3) {
            return ['error' => t('Notebook.storageFull', [$gb])];
        }
        if (! is_dir($this->dir())) {
            mkdir($this->dir(), 0775, true);
        }
        $stored = bin2hex(random_bytes(12)) . '.' . $ext;
        $name   = mb_substr(basename($f->getClientName()), 0, 255);
        $f->move($this->dir(), $stored);
        db_connect()->table('page_files')->insert([
            'institution_id' => $this->iid(), 'workspace_id' => $workspaceId, 'kind' => $kind === 'image' ? 'image' : 'file', 'file_path' => $stored,
            'file_name' => $name, 'mime' => $mime, 'size' => $size, 'uploaded_by' => service('auth')->id(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) db_connect()->insertID();

        return ['id' => $id, 'kind' => $kind === 'image' ? 'image' : 'file', 'name' => $name, 'size' => $size, 'mime' => $mime, 'url' => site_url('archivos/' . $id)];
    }

    /** Archivo de la institución activa (sin comprobar permisos de lectura). */
    public function get(int $id): ?array
    {
        return db_connect()->table('page_files')->where(['id' => $id, 'institution_id' => $this->iid()])->where('deleted_at', null)->get()->getRowArray() ?: null;
    }

    /** Archivos de un cuaderno por id (para validar y mostrar los bloques de una página). */
    public function forWorkspace(int $workspaceId, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (! $ids) {
            return [];
        }

        return array_column(db_connect()->table('page_files')->where('institution_id', $this->iid())->where('workspace_id', $workspaceId)
            ->whereIn('id', $ids)->where('deleted_at', null)->get()->getResultArray(), null, 'id');
    }

    /** ¿Puede la persona ver este archivo? Docente o coordinación de la asignatura, estudiante del grupo o vista de estudiante. */
    public function canRead(array $file): bool
    {
        $ws = db_connect()->table('workspaces')->select('offering_id')->where('id', (int) $file['workspace_id'])->get()->getRowArray();
        if (! $ws) {
            return false;
        }
        $off = (int) $ws['offering_id'];
        $c   = service('content');
        if ($c->canEditOffering($off) || $c->canReadOffering($off)) {
            return true;
        }
        // Vista de estudiante del docente: el grupo previsualizado debe ser el de la asignatura.
        $pv = service('studentPreview');
        if (! $pv->isStudent() && ($g = $pv->groupId())) {
            return (bool) db_connect()->table('subject_offerings')->where(['id' => $off, 'group_id' => $g])->countAllResults();
        }

        return false;
    }

    public function path(array $file): ?string
    {
        $p = $this->dir() . (string) $file['file_path'];

        return $file['file_path'] && basename($file['file_path']) === $file['file_path'] && is_file($p) ? $p : null;
    }

    /** «2,4 MB» */
    public static function human(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
