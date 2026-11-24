<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;

/**
 * Consola › Solicitudes de demostración (las que llegan desde la página pública). Solo superadministrador (filtro en
 * Routes). Seguimiento por estado y notas internas; «Crear institución» abre el alta con los datos ya llenos.
 */
class DemoRequestsController extends BaseController
{
    public const STATUSES = ['new' => ['Nueva', 'accent'], 'contacted' => ['Contactada', 'warn'], 'won' => ['Convertida', 'ok'], 'closed' => ['Descartada', 'neutral']];

    private function filters(): array
    {
        $estado = (string) $this->request->getGet('estado');

        return ['estado' => isset(self::STATUSES[$estado]) ? $estado : '', 'q' => mb_substr(trim((string) $this->request->getGet('q')), 0, 100)];
    }

    private function query(array $f)
    {
        $q = db_connect()->table('demo_requests d')->select('d.*, u.name AS handler, i.name AS institution_name')
            ->join('users u', 'u.id = d.handled_by', 'left')->join('institutions i', 'i.id = d.institution_id', 'left');
        if ($f['estado'] !== '') {
            $q->where('d.status', $f['estado']);
        }
        if ($f['q'] !== '') {
            $q->groupStart()->like('d.institution', $f['q'])->orLike('d.name', $f['q'])->orLike('d.email', $f['q'])->orLike('d.city', $f['q'])->groupEnd();
        }

        return $q;
    }

    public function index()
    {
        $f      = $this->filters();
        $counts = array_column(db_connect()->table('demo_requests')->select('status, COUNT(*) AS n')->groupBy('status')->get()->getResultArray(), 'n', 'status');
        // Primero las nuevas, luego las contactadas; dentro de cada estado, la más reciente primero.
        $list = $this->query($f)->orderBy("CASE d.status WHEN 'new' THEN 0 WHEN 'contacted' THEN 1 ELSE 2 END", '', false)->orderBy('d.id', 'DESC')
            ->limit(300)->get()->getResultArray();

        return view('platform/demo_requests', $this->platformLayout() + [
            'title' => 'Solicitudes de demostración', 'list' => $list, 'filters' => $f, 'statuses' => self::STATUSES,
            'counts' => array_map('intval', $counts), 'total' => array_sum(array_map('intval', $counts)),
        ]);
    }

    /** Cambia el estado y las notas internas. */
    public function update(int $id)
    {
        $db  = db_connect();
        $row = $db->table('demo_requests')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return $this->backWith('error', 'Esa solicitud ya no existe.');
        }
        $status = (string) $this->request->getPost('status');
        if (! isset(self::STATUSES[$status])) {
            return $this->backWith('error', 'Elija un estado válido.');
        }
        $db->table('demo_requests')->where('id', $id)->update([
            'status' => $status, 'notes' => mb_substr(trim((string) $this->request->getPost('notes')), 0, 1000) ?: null,
            'handled_by' => service('auth')->id(), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($status !== $row['status']) {
            service('audit')->log('platform.demo.update', ['solicitud' => $row['institution'], 'estado' => self::STATUSES[$status][0]], null);
        }

        return redirect()->to('plataforma/solicitudes' . ($this->request->getPost('back') ? '?' . http_build_query(['estado' => (string) $this->request->getPost('back')]) : ''))
            ->with('toast', ['type' => 'ok', 'msg' => 'Solicitud actualizada.']);
    }

    public function csv()
    {
        $rows = array_map(static fn ($r) => [$r['created_at'], $r['institution'], $r['city'], $r['students'], $r['name'], $r['role'], $r['email'], $r['phone'],
            self::STATUSES[$r['status']][0] ?? $r['status'], $r['message'], $r['notes']], $this->query($this->filters())->orderBy('d.id', 'DESC')->get()->getResultArray());

        return $this->csvResponse('solicitudes-demostracion-' . date('Y-m-d') . '.csv',
            ['Fecha', 'Institución', 'Ciudad', 'Estudiantes', 'Contacto', 'Cargo', 'Correo', 'Teléfono', 'Estado', 'Mensaje', 'Notas internas'], $rows);
    }
}
