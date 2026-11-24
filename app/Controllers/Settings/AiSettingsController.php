<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;

/**
 * Ajustes › Inteligencia artificial (documento maestro 5.8): reparto del cupo por rol, tope diario por persona,
 * interruptores y cupos adicionales temporales. Todo cambio queda en la auditoría.
 */
class AiSettingsController extends BaseController
{
    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    public function index()
    {
        $iid     = $this->iid();
        $members = db_connect()->table('institution_users hu')->select('u.id, u.name, r.template_key, r.name AS role')
            ->join('users u', 'u.id = hu.user_id')->join('roles r', 'r.id = hu.role_id')
            ->where('hu.institution_id', $iid)->where('hu.status', 'active')->whereIn('r.template_key', ['admin', 'coordinator', 'teacher', 'student'])
            ->orderBy('u.name')->get()->getResultArray();

        return view('settings/ai', $this->layoutData() + [
            'title'   => 'Inteligencia artificial',
            'section' => 'ia',
            'r'       => service('aiQuota')->report($iid),
            'members' => $members,
            'ai'      => ['available' => service('ai')->available(), 'simulated' => service('ai')->simulated()],
        ]);
    }

    public function update()
    {
        $cfg = config('Ai');
        $in  = [];
        foreach (array_keys($cfg->bucketLabels) as $b) {
            $in['share_' . $b] = (int) $this->request->getPost('share_' . $b);
            $in['daily_' . $b] = (int) $this->request->getPost('daily_' . $b);
            if ($in['share_' . $b] < 0 || $in['share_' . $b] > 100 || $in['daily_' . $b] < 0 || $in['daily_' . $b] > 10000000) {
                return $this->backWith('error', 'Revise los valores: porcentajes de 0 a 100 y topes diarios positivos.');
            }
        }
        if (array_sum(array_map(static fn ($b) => $in['share_' . $b], array_keys($cfg->bucketLabels))) !== 100) {
            return $this->backWith('error', 'El reparto por rol debe sumar 100 %.');
        }
        foreach (['enabled', 'label_ai', 'alert_80'] as $k) {
            $in[$k] = $this->request->getPost($k) ? 1 : 0;
        }
        $iid    = $this->iid();
        $before = service('aiQuota')->settings($iid);
        db_connect()->table('ai_settings')->where('institution_id', $iid)->update($in + ['updated_by' => (int) service('auth')->id(), 'updated_at' => date('Y-m-d H:i:s')]);
        $changes = [];
        foreach ($in as $k => $v) {
            if ((string) $before[$k] !== (string) $v) {
                $changes[$k] = $before[$k] . ' → ' . $v;
            }
        }
        if ($changes) {
            service('audit')->log('ai.settings.update', $changes);
        }

        return redirect()->to('ajustes/ia')->with('toast', ['type' => 'ok', 'msg' => 'Ajustes de IA guardados. Rigen desde la próxima consulta.']);
    }

    public function grant()
    {
        $iid    = $this->iid();
        $target = (string) $this->request->getPost('target'); // u:{id} | b:{cubeta}
        $tokens = (int) $this->request->getPost('tokens');
        $until  = (string) $this->request->getPost('expires');
        $reason = trim((string) $this->request->getPost('reason'));
        $userId = null;
        $bucket = null;
        if (str_starts_with($target, 'u:')) {
            $userId = (int) substr($target, 2);
            if (! service('aiQuota')->bucketOf($iid, $userId)) {
                return $this->backWith('error', 'Esa persona no pertenece a la institución o su rol no usa la IA.');
            }
        } elseif (str_starts_with($target, 'b:') && isset(config('Ai')->bucketLabels[substr($target, 2)])) {
            $bucket = substr($target, 2);
        } else {
            return $this->backWith('error', 'Elija a quién se concede el cupo.');
        }
        $ts = strtotime($until . ' 23:59:59');
        if ($tokens < 1000 || $tokens > 5000000 || ! $ts || $ts <= time() || $ts > strtotime('+90 days') || $reason === '' || mb_strlen($reason) > 255) {
            return $this->backWith('error', 'Revise los datos: al menos 1.000 tokens, vencimiento en los próximos 90 días y un motivo.');
        }
        $now = date('Y-m-d H:i:s');
        db_connect()->table('ai_grants')->insert(['institution_id' => $iid, 'user_id' => $userId, 'bucket' => $bucket, 'tokens' => $tokens, 'reason' => $reason,
            'expires_at' => date('Y-m-d H:i:s', $ts), 'created_by' => (int) service('auth')->id(), 'created_at' => $now, 'updated_at' => $now]);
        $who = $userId ? (string) (db_connect()->table('users')->select('name')->where('id', $userId)->get()->getRow()->name ?? '') : config('Ai')->bucketLabels[$bucket];
        service('audit')->log('ai.grant.create', ['para' => $who, 'tokens' => $tokens, 'vence' => date('Y-m-d', $ts), 'motivo' => $reason]);

        return redirect()->to('ajustes/ia')->with('toast', ['type' => 'ok', 'msg' => 'Cupo adicional concedido. Queda registrado en la auditoría.']);
    }

    public function revoke(int $id)
    {
        $iid = $this->iid();
        $g   = db_connect()->table('ai_grants')->where('institution_id', $iid)->where('id', $id)->get()->getRowArray();
        if (! $g) {
            return $this->backWith('error', 'Ese cupo adicional no existe.');
        }
        db_connect()->table('ai_grants')->where('institution_id', $iid)->where('id', $id)->update(['expires_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        service('audit')->log('ai.grant.revoke', ['tokens' => (int) $g['tokens'], 'motivo' => $g['reason']]);

        return redirect()->to('ajustes/ia')->with('toast', ['type' => 'ok', 'msg' => 'Cupo adicional retirado.']);
    }
}
