<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Libraries\Import\SheetReader;
use App\Libraries\Import\StudentImport;

/**
 * Ajustes › Personas › Importar: estudiantes, acudientes y matrícula desde Excel o CSV.
 * Pasos: plantilla → subir (análisis, nada se guarda) → vista previa → confirmar → descargar contraseñas (una vez).
 * Exige gestionar personas (ruta) y la estructura académica (matrícula), que tienen rectoría y coordinación.
 */
class ImportController extends BaseController
{
    private function denied()
    {
        return service('tenant')->can('academic.structure.manage') ? null
            : redirect()->to('ajustes/usuarios')->with('toast', ['type' => 'error', 'msg' => 'Para importar necesita también el permiso de estructura académica (matrícula).']);
    }

    public function index()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $token   = (string) $this->request->getGet('vista');
        $preview = $token !== '' ? (new StudentImport())->load($token) : null;
        if ($token !== '' && ! $preview) {
            return redirect()->to('ajustes/usuarios/importar')->with('toast', ['type' => 'info', 'msg' => 'La vista previa venció. Suba el archivo de nuevo.']);
        }

        return view('settings/import', $this->layoutData() + [
            'title'   => 'Importar personas',
            'preview' => $preview,
            'result'  => session('importResult'),
            'columns' => StudentImport::COLUMNS,
            'period'  => service('academic')->currentPeriod()['name'] ?? '',
            'groups'  => array_column(service('academic')->groups((int) (service('academic')->currentPeriod()['id'] ?? 0)), 'name'),
        ]);
    }

    /** Plantilla con encabezados y dos filas de ejemplo (.xlsx o .csv). */
    public function template(string $format)
    {
        [$head, $rows] = StudentImport::template();
        if ($format === 'xlsx') {
            return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment; filename="plantilla-estudiantes.xlsx"')
                ->setBody(SheetReader::writeXlsx($head, $rows, 'Estudiantes'));
        }
        helper('csv');

        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="plantilla-estudiantes.csv"')
            ->setBody(csv_build($head, $rows));
    }

    /** Sube y analiza el archivo (no guarda nada todavía). */
    public function analyze()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $back = redirect()->to('ajustes/usuarios/importar');
        $f    = $this->request->getFile('archivo');
        if (! $f || ! $f->isValid() || $f->hasMoved()) {
            return $back->with('toast', ['type' => 'error', 'msg' => 'Elija un archivo .xlsx o .csv.']);
        }
        $ext = strtolower($f->getClientExtension());
        if (! in_array($ext, ['xlsx', 'csv'], true)) {
            return $back->with('toast', ['type' => 'error', 'msg' => 'El archivo debe ser .xlsx (Excel) o .csv.']);
        }
        $read = SheetReader::read($f->getTempName(), $ext);
        if (isset($read['error'])) {
            return $back->with('toast', ['type' => 'error', 'msg' => $read['error']]);
        }
        $a = (new StudentImport())->analyze($read['rows'], $f->getClientName());
        if (isset($a['error'])) {
            return $back->with('toast', ['type' => 'error', 'msg' => $a['error']]);
        }

        return redirect()->to('ajustes/usuarios/importar?vista=' . $a['token']);
    }

    /** Aplica la importación analizada. */
    public function confirm()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $token = (string) $this->request->getPost('token');
        $res   = (new StudentImport())->apply($token);
        if (isset($res['error'])) {
            return redirect()->to('ajustes/usuarios/importar' . ($token ? '?vista=' . $token : ''))->with('toast', ['type' => 'error', 'msg' => $res['error']]);
        }

        return redirect()->to('ajustes/usuarios/importar')->with('importResult', $res)
            ->with('toast', ['type' => 'ok', 'msg' => 'Importación completa.']);
    }

    /** Contraseñas temporales de las cuentas nuevas: se descargan una sola vez. */
    public function credentials(string $token)
    {
        $rows = (new StudentImport())->takeCredentials($token);
        if ($rows === null) {
            return redirect()->to('ajustes/usuarios/importar')->with('toast', ['type' => 'error', 'msg' => 'La lista de contraseñas ya se descargó o venció. Si alguien la perdió, asígnele una contraseña temporal en Ajustes › Personas.']);
        }
        helper('csv');
        service('audit')->log('users.import_credentials', ['cuentas' => count($rows)]);

        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="contrasenas-temporales-' . date('Ymd-Hi') . '.csv"')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody(csv_build(['Nombre', 'Documento', 'Rol', 'Correo', 'Contraseña temporal'], $rows));
    }
}
