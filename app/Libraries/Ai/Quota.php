<?php

namespace App\Libraries\Ai;

/**
 * Cupos de IA (documento maestro 5.8). Úsela con service('aiQuota').
 *
 * Niveles: plan (tokens/mes de la institución) → rol (% del plan para la cubeta del rol, mes) → persona (tokens/día
 * según su rol) → solicitud (longitud máxima, en Gateway). Lo consumido = tokens reales de las solicitudes terminadas
 * + lo reservado por las que están en curso. Los periodos se cuentan en la zona horaria de la institución.
 * Cupo adicional: a un rol suma al mes de esa cubeta; a una persona suma a su tope diario, mientras esté vigente.
 * Un plan con 0 tokens es «a convenir»: sin tope de plan.
 */
class Quota
{
    private function db()
    {
        return db_connect();
    }

    /** Fila de ajustes de IA de la institución (la crea con los valores iniciales si no existe). */
    public function settings(int $iid): array
    {
        $row = $this->db()->table('ai_settings')->where('institution_id', $iid)->get()->getRowArray();
        if (! $row) {
            $now = date('Y-m-d H:i:s');
            $this->db()->table('ai_settings')->insert(config('Ai')->defaults + ['institution_id' => $iid, 'created_at' => $now, 'updated_at' => $now]);
            $row = $this->db()->table('ai_settings')->where('institution_id', $iid)->get()->getRowArray();
        }

        return $row;
    }

    public function plan(int $iid): array
    {
        $name = (string) ($this->db()->table('institutions')->select('plan')->where('id', $iid)->get()->getRow()->plan ?? '');
        $plan = $this->db()->table('plans')->where('name', $name)->get()->getRowArray();

        return $plan ?: ['name' => $name ?: 'Sin plan', 'monthly_tokens' => 0, 'advanced_model' => 0];
    }

    /** Red de la institución si tiene cupo común de IA (monthly_tokens > 0); si no, null. */
    public function network(int $iid): ?array
    {
        return $this->db()->table('networks n')->select('n.id, n.name, n.monthly_tokens')->join('institutions i', 'i.network_id = n.id')
            ->where('i.id', $iid)->where('n.monthly_tokens >', 0)->where('n.deleted_at', null)->get()->getRowArray();
    }

    /** Cubeta de cupo de la persona en la institución activa (teacher | coordinator | student | null). */
    public function bucketOf(int $iid, int $userId): ?string
    {
        if (service('auth')->isSuperadmin() && (int) service('auth')->id() === $userId) {
            return 'coordinator';
        }
        $key = $this->db()->table('institution_users hu')->select('r.template_key')->join('roles r', 'r.id = hu.role_id')
            ->where('hu.institution_id', $iid)->where('hu.user_id', $userId)->get()->getRow()->template_key ?? null;

        return config('Ai')->buckets[$key] ?? null;
    }

    /** Inicio y fin (en la hora de la aplicación, como se guardan las fechas) del día o del mes en curso en la zona horaria de la institución. */
    public function period(int $iid, string $unit): array
    {
        $tz  = new \DateTimeZone((string) ($this->db()->table('institutions')->select('timezone')->where('id', $iid)->get()->getRow()->timezone ?? 'America/Bogota'));
        $now = new \DateTimeImmutable('now', $tz);
        $from = $unit === 'day' ? $now->setTime(0, 0) : $now->modify('first day of this month')->setTime(0, 0);
        $to   = $unit === 'day' ? $from->modify('+1 day') : $from->modify('first day of next month');
        $utc  = new \DateTimeZone(date_default_timezone_get());

        return [$from->setTimezone($utc)->format('Y-m-d H:i:s'), $to->setTimezone($utc)->format('Y-m-d H:i:s'), $to];
    }

    /** Tokens consumidos + reservados en un periodo, con filtro opcional por cubeta o persona. */
    public function used(int $iid, string $unit, ?string $bucket = null, ?int $userId = null): int
    {
        [$from, $to] = $this->period($iid, $unit);
        // Una reserva de más de 10 minutos es de una solicitud que se cortó: deja de contar.
        $stale = date('Y-m-d H:i:s', time() - 600);
        $q = $this->db()->table('ai_usage')
            ->select("SUM(CASE WHEN status = 'reserved' AND created_at >= '" . $stale . "' THEN reserved_tokens WHEN status = 'done' THEN tokens ELSE 0 END) AS n", false)
            ->where('institution_id', $iid)->where('created_at >=', $from)->where('created_at <', $to);
        if ($bucket !== null) {
            $q->where('bucket', $bucket);
        }
        if ($userId !== null) {
            $q->where('user_id', $userId);
        }

        return (int) ($q->get()->getRow()->n ?? 0);
    }

    private function grants(int $iid, string $field, $value): int
    {
        return (int) ($this->db()->table('ai_grants')->select('SUM(tokens) AS n', false)->where('institution_id', $iid)->where($field, $value)
            ->where('expires_at >', date('Y-m-d H:i:s'))->get()->getRow()->n ?? 0);
    }

    /** Límites vigentes de la persona: [plan, role, user] en tokens (null = sin tope). */
    public function limits(int $iid, string $bucket, int $userId): array
    {
        $s    = $this->settings($iid);
        $plan = (int) $this->plan($iid)['monthly_tokens'];

        return [
            'plan' => $plan ?: null,
            'role' => $plan ? (int) floor($plan * (int) $s['share_' . $bucket] / 100) + $this->grants($iid, 'bucket', $bucket) : null,
            'user' => (int) $s['daily_' . $bucket] + $this->grants($iid, 'user_id', $userId),
        ];
    }

    /**
     * ¿Cabe una solicitud de $tokens? Devuelve null si cabe, o [level, message] del primer nivel que no alcanza.
     * Llamarla dentro de la transacción que bloquea ai_settings (Gateway) para que la reserva sea atómica.
     */
    public function check(int $iid, string $bucket, int $userId, int $tokens): ?array
    {
        $lim = $this->limits($iid, $bucket, $userId);
        $mes = static fn (\DateTimeImmutable $d) => $d->format('j') . ' de ' . ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][(int) $d->format('n')];
        $month = $this->period($iid, 'month')[2];

        if ($net = $this->network($iid)) {
            if (service('network')->aiUsedThisMonth((int) $net['id']) + $tokens > (int) $net['monthly_tokens']) {
                return ['network', 'La red ' . $net['name'] . ' usó todo su cupo común de IA de este mes. Se renueva el ' . $mes($month) . '.'];
            }
        }
        if ($lim['plan'] !== null && $this->used($iid, 'month') + $tokens > $lim['plan']) {
            return ['plan', 'La institución usó todo su cupo de IA de este mes. Se renueva el ' . $mes($month) . '.'];
        }
        if ($lim['role'] !== null && $this->used($iid, 'month', $bucket) + $tokens > $lim['role']) {
            return ['role', 'Se agotó el cupo de IA de este mes para ' . mb_strtolower(config('Ai')->bucketLabels[$bucket]) . '. Se renueva el ' . $mes($month) . '.'];
        }
        if ($this->used($iid, 'day', null, $userId) + $tokens > $lim['user']) {
            return ['user', 'Se alcanzó el límite diario de IA de esta cuenta. Se renueva mañana a las 00:00.'];
        }

        return null;
    }

    /**
     * Informe de Ajustes › IA: plan, consumo y costo del mes, proyección, uso por cubeta, por función, personas con
     * mayor consumo, cupos adicionales vigentes y avisos al 80 % / 100 %.
     */
    public function report(int $iid): array
    {
        $cfg  = config('Ai');
        $s    = $this->settings($iid);
        $plan = $this->plan($iid);
        [$from, $to, $renew] = $this->period($iid, 'month');
        [$dFrom] = $this->period($iid, 'day');
        $done = fn () => $this->db()->table('ai_usage u')->where('u.institution_id', $iid)->where('u.status', 'done')->where('u.created_at >=', $from)->where('u.created_at <', $to);

        $used  = $this->used($iid, 'month');
        $cost  = (float) ($done()->select('SUM(cost_usd) AS c', false)->get()->getRow()->c ?? 0);
        $tz    = $renew->getTimezone();
        $today = new \DateTimeImmutable('now', $tz);
        $days  = (int) $today->format('t');
        $day   = (int) $today->format('j');
        $limit = (int) $plan['monthly_tokens'];

        $people = [];
        foreach ($this->db()->table('institution_users hu')->select('r.template_key, COUNT(*) AS n', false)->join('roles r', 'r.id = hu.role_id')
            ->where('hu.institution_id', $iid)->where('hu.status', 'active')->groupBy('r.template_key')->get()->getResultArray() as $r) {
            if ($b = $cfg->buckets[$r['template_key']] ?? null) {
                $people[$b] = ($people[$b] ?? 0) + (int) $r['n'];
            }
        }
        $buckets = [];
        foreach ($cfg->bucketLabels as $b => $label) {
            $share     = (int) $s['share_' . $b];
            $bLimit    = $limit ? (int) floor($limit * $share / 100) + $this->grants($iid, 'bucket', $b) : null;
            $buckets[] = ['key' => $b, 'name' => $label, 'share' => $share, 'daily' => (int) $s['daily_' . $b], 'people' => $people[$b] ?? 0,
                'used' => $this->used($iid, 'month', $b), 'limit' => $bLimit];
        }

        $functions = [];
        foreach ($done()->select('feature, COUNT(*) AS calls, SUM(tokens) AS tokens', false)->groupBy('feature')->orderBy('tokens', 'DESC')->get()->getResultArray() as $f) {
            $functions[] = ['name' => $cfg->features[$f['feature']]['label'] ?? $f['feature'], 'calls' => (int) $f['calls'], 'tokens' => (int) $f['tokens']];
        }

        $top = [];
        foreach ($done()->select("u.user_id, us.name, u.bucket, SUM(u.tokens) AS month, SUM(CASE WHEN u.created_at >= '" . $dFrom . "' THEN u.tokens ELSE 0 END) AS today", false)
            ->join('users us', 'us.id = u.user_id')->groupBy(['u.user_id', 'us.name', 'u.bucket'])->orderBy('month', 'DESC')->limit(8)->get()->getResultArray() as $t) {
            $top[] = ['id' => (int) $t['user_id'], 'name' => $t['name'], 'role' => $cfg->bucketLabels[$t['bucket']] ?? '', 'month' => (int) $t['month'],
                'today' => (int) $t['today'], 'daily' => (int) $s['daily_' . $t['bucket']] + $this->grants($iid, 'user_id', (int) $t['user_id'])];
        }

        $grants = $this->db()->table('ai_grants g')->select('g.*, us.name AS person, cb.name AS by_name')
            ->join('users us', 'us.id = g.user_id', 'left')->join('users cb', 'cb.id = g.created_by', 'left')
            ->where('g.institution_id', $iid)->where('g.expires_at >', date('Y-m-d H:i:s'))->orderBy('g.expires_at')->get()->getResultArray();

        $alerts = [];
        if ((int) $s['alert_80']) {
            if ($limit && $used >= 0.8 * $limit) {
                $alerts[] = 'La institución lleva el ' . (int) round($used * 100 / $limit) . ' % del cupo mensual del plan.';
            }
            foreach ($buckets as $b) {
                if ($b['limit'] && $b['used'] >= 0.8 * $b['limit']) {
                    $alerts[] = $b['name'] . ' llevan el ' . (int) round($b['used'] * 100 / $b['limit']) . ' % de su parte del cupo mensual.';
                }
            }
            foreach ($top as $t) {
                if ($t['daily'] && $t['today'] >= 0.8 * $t['daily']) {
                    $alerts[] = $t['name'] . ' lleva hoy el ' . (int) round($t['today'] * 100 / $t['daily']) . ' % de su tope diario.';
                }
            }
        }

        return [
            'settings' => $s, 'plan' => $plan, 'used' => $used, 'limit' => $limit, 'cost' => $cost,
            'projection' => $limit ? (int) round($used / max(1, $day) * $days * 100 / $limit) : null,
            'day' => $day, 'days' => $days, 'renews' => $renew, 'buckets' => $buckets, 'functions' => $functions, 'top' => $top,
            'grants' => $grants, 'alerts' => $alerts,
        ];
    }

    /**
     * Saldo para mostrar en el panel de IA: used/limit del día de la persona (limitado por lo que quede del rol y del
     * plan), per_query para el estudiante y hora de renovación. Formato de partials/ai_quota.
     */
    public function balance(?int $iid = null, ?int $userId = null): array
    {
        $iid ??= (int) service('tenant')->institutionId();
        $userId ??= (int) service('auth')->id();
        $bucket = $iid ? $this->bucketOf($iid, $userId) : null;
        if (! $bucket) {
            return ['used' => 0, 'limit' => 0, 'per_query' => config('Ai')->studentQueryTokens, 'renews' => '00:00', 'blocked' => 'Su rol no usa la IA.'];
        }
        $lim  = $this->limits($iid, $bucket, $userId);
        $used = $this->used($iid, 'day', null, $userId);
        $left = $lim['user'] - $used;
        if ($lim['role'] !== null) {
            $left = min($left, $lim['role'] - $this->used($iid, 'month', $bucket));
        }
        if ($lim['plan'] !== null) {
            $left = min($left, $lim['plan'] - $this->used($iid, 'month'));
        }
        if ($net = $this->network($iid)) {
            $left = min($left, (int) $net['monthly_tokens'] - service('network')->aiUsedThisMonth((int) $net['id']));
        }
        $left    = max(0, $left);
        $enabled = (bool) $this->settings($iid)['enabled'];

        return [
            'used'      => max(0, $lim['user'] - $left),
            'limit'     => $lim['user'],
            'per_query' => $bucket === 'student' ? config('Ai')->studentQueryTokens : config('Ai')->features['questions']['estimate'],
            'renews'    => '00:00',
            'blocked'   => $enabled ? null : 'La institución desactivó la IA.',
        ];
    }
}
