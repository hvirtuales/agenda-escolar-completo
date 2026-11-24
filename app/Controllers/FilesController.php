<?php

namespace App\Controllers;

/**
 * Entrega los archivos de los cuadernos (bloques «Imagen» y «Archivo»), solo a quien puede leer la asignatura
 * (App\Libraries\NotebookFiles::canRead). Imágenes en línea; los demás, como descarga.
 */
class FilesController extends BaseController
{
    public function show(int $id)
    {
        $svc  = service('notebookFiles');
        $file = $svc->get($id);
        $path = $file && $svc->canRead($file) ? $svc->path($file) : null;
        if (! $path) {
            return $this->response->setStatusCode(404)->setBody(t('Notebook.fileGone'));
        }
        // Nada de lo que se entrega aquí se ejecuta ni se interpreta como otra cosa.
        $this->response->setHeader('X-Content-Type-Options', 'nosniff')->setHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        if ($file['kind'] === 'image') {
            return $this->response->setHeader('Content-Type', (string) $file['mime'])->setHeader('Cache-Control', 'private, max-age=86400')
                ->setHeader('Content-Disposition', 'inline; filename="' . rawurlencode((string) $file['file_name']) . '"')->setBody((string) file_get_contents($path));
        }

        return $this->response->download($path, null)->setFileName((string) $file['file_name'])->setContentType((string) $file['mime'])
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }
}
