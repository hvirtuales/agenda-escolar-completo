<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use App\Models\NetworkModel;

/**
 * Consola de plataforma › Redes educativas (solo superadministrador): crear redes, asignar sus colegios, fijar el
 * cupo común de IA y nombrar administradores. Todo queda en la auditoría.
 */
class NetworksController extends BaseController
{
    private function ctx(array $data = []): array
    {
        return $data + $this->platformLayout();
    }

    public function index()
    {
        $db       = db_connect();
        $networks = $db->table('networks n')->select('n.*, (SELECT COUNT(*) FROM network_users nu WHERE nu.network_id = n.id) AS members', false)
            ->where('n.deleted_at', null)->orderBy('n.name')->get()->getResultArray();
        $schools  = $db->table('institutions')->select('id, name, city, network_id, status')->where('deleted_at', null)->orderBy('name')->get()->getResultArray();
        foreach ($networks as &$n) {
            $n['schools'] = array_values(array_filter($schools, static fn ($s) => (int) $s['network_id'] === (int) $n['id']));
            $n['members_list'] = service('network')->members((int) $n['id']);
            $n['ai_month'] = service('network')->aiUsedThisMonth((int) $n['id']);
        }

        return view('platform/networks', $this->ctx(['title' => 'Redes educativas', 'rows' => $networks, 'schools' => $schools]));
    }

    /** Crea (sin id) o actualiza una red con sus colegios. */
    public function save(?int $id = null)
    {
        $in   = $this->postFields(['name', 'city', 'accent_color', 'monthly_tokens']);
        $name = $in['name'];
        if ($name === '' || mb_strlen($name) > 160) {
            return $this->backWith('error', 'Escriba el nombre de la red (hasta 160 caracteres).');
        }
        $data = [
            'name' => $name, 'city' => $in['city'] ?: null, 'accent_color' => preg_match('/^#[0-9a-fA-F]{6}$/', $in['accent_color']) ? strtoupper($in['accent_color']) : '#1D4ED8',
            'monthly_tokens' => max(0, (int) $in['monthly_tokens']),
        ];
        $m = model(NetworkModel::class);
        if ($id) {
            if (! $m->find($id)) {
                return $this->backWith('error', 'Esa red no existe.');
            }
            $m->update($id, $data);
        } else {
            helper('url');
            $data['slug'] = url_title(convert_accented_characters($name), '-', true) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            $id           = (int) $m->insert($data);
        }

        // Colegios de la red: los marcados quedan en ella; los que estaban y se desmarcan quedan independientes.
        $ids = array_map('intval', (array) $this->request->getPost('schools'));
        $db  = db_connect();
        $db->table('institutions')->where('network_id', $id)->update(['network_id' => null]);
        if ($ids) {
            $db->table('institutions')->whereIn('id', $ids)->update(['network_id' => $id]);
        }
        service('audit')->log('network.save', ['red' => $name, 'colegios' => count($ids), 'cupo común' => $data['monthly_tokens']]);

        return redirect()->to('plataforma/redes')->with('toast', ['type' => 'ok', 'msg' => 'Red guardada.']);
    }

    public function addMember(int $id)
    {
        $e = service('network')->addMember($id, (string) $this->request->getPost('email'), (string) $this->request->getPost('role'));

        return $e ? $this->backWith('error', $e) : redirect()->to('plataforma/redes')->with('toast', ['type' => 'ok', 'msg' => 'Persona agregada a la red.']);
    }

    public function removeMember(int $id, int $memberId)
    {
        $e = service('network')->removeMember($id, $memberId);

        return $e ? $this->backWith('error', $e) : redirect()->to('plataforma/redes')->with('toast', ['type' => 'ok', 'msg' => 'Persona retirada de la red.']);
    }

    public function delete(int $id)
    {
        $n = model(NetworkModel::class)->find($id);
        if (! $n) {
            return $this->backWith('error', 'Esa red no existe.');
        }
        db_connect()->table('institutions')->where('network_id', $id)->update(['network_id' => null]);
        db_connect()->table('network_users')->where('network_id', $id)->delete();
        model(NetworkModel::class)->delete($id);
        service('audit')->log('network.delete', ['red' => $n['name']]);

        return redirect()->to('plataforma/redes')->with('toast', ['type' => 'ok', 'msg' => 'Red eliminada. Sus colegios quedan independientes, con todos sus datos.']);
    }
}
