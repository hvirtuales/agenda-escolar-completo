<?php

namespace App\Libraries\Ai;

use App\Libraries\Ai\Providers\AnthropicProvider;
use App\Libraries\Ai\Providers\Provider;
use App\Libraries\Ai\Providers\SimulatedProvider;

/**
 * AI Gateway (documento maestro 5.4 y 5.8): punto único de toda llamada a un modelo. Úsela con service('ai').
 *
 * 1. Valida la función, la longitud de la entrada (nivel «solicitud») y que la IA esté activa en la institución.
 * 2. Reserva un estimado de tokens en una transacción que bloquea la fila de ajustes de la institución, comprobando
 *    plan, rol y persona (así dos solicitudes simultáneas no pasan el cupo).
 * 3. Llama al proveedor; al terminar ajusta la reserva con el consumo real (o la libera si falló).
 * Nunca se confía en la pantalla: toda comprobación de cupo y permiso está aquí y en quien la llama.
 */
class Gateway
{
    private ?Provider $provider = null;

    public function provider(): ?Provider
    {
        if ($this->provider) {
            return $this->provider;
        }
        $key = (new KeyStore())->key(); // consola (cifrada) o, si no hay, ANTHROPIC_API_KEY del .env
        if ($key !== '') {
            return $this->provider = new AnthropicProvider($key);
        }

        return $this->provider = ENVIRONMENT === 'development' ? new SimulatedProvider() : null;
    }

    /** Para pruebas: inyectar un proveedor. */
    public function setProvider(?Provider $p): void
    {
        $this->provider = $p;
    }

    /** ¿Hay proveedor? (en producción sin clave: no) */
    public function available(): bool
    {
        return $this->provider() !== null;
    }

    public function simulated(): bool
    {
        return $this->available() && ! $this->provider()->isReal();
    }

    /**
     * Ejecuta una función de IA.
     *
     * @param string $feature  clave de Config\Ai::$features
     * @param string $system   instrucciones del sistema (Prompts)
     * @param array  $messages [['role' => 'user'|'assistant', 'content' => '…'], …] — la última es la de la persona
     * @param array  $opts     schema (JSON Schema), prompt_version, meta (datos para el simulador)
     *
     * @return array ['text', 'simulated', 'tokens'] o ['error' => mensaje, 'level' => plan|role|user|request|off]
     */
    public function run(string $feature, string $system, array $messages, array $opts = []): array
    {
        $cfg = config('Ai');
        $f   = $cfg->features[$feature] ?? null;
        $iid = (int) service('tenant')->institutionId();
        $uid = (int) service('auth')->id();
        if (! $f || ! $iid) {
            return ['error' => 'Función de IA no disponible.', 'level' => 'off'];
        }
        if (service('impersonation')->active()) { // «Ver como»: no se gasta el cupo de la persona
            return ['error' => 'Viendo como otra persona: la IA no está disponible.', 'level' => 'off'];
        }
        if (! $this->available()) {
            return ['error' => 'La IA no está configurada en esta plataforma.', 'level' => 'off'];
        }
        $q = service('aiQuota');
        if (! (int) $q->settings($iid)['enabled']) {
            return ['error' => 'La institución desactivó la IA.', 'level' => 'off'];
        }
        $bucket = $q->bucketOf($iid, $uid);
        if (! $bucket) {
            return ['error' => 'Su rol no usa la IA.', 'level' => 'off'];
        }
        // Idioma de la respuesta: el de la interfaz de la persona (las instrucciones están en español; la respuesta no).
        if (service('locale')->current() === 'en') {
            $system .= "\n\nIMPORTANT: this person uses the platform in English. Write your entire answer in English (including any JSON text"
                . " fields), even though these instructions are in Spanish. Keep names of subjects, pages and activities as written.";
        }
        $userText = (string) (end($messages)['content'] ?? '');
        if (mb_strlen($userText) > $f['max_in']) {
            return ['error' => 'El texto es muy largo: máximo ' . $f['max_in'] . ' caracteres.', 'level' => 'request'];
        }
        $tier  = $cfg->models[$f['tier']]; // todos los planes incluyen ambos niveles (mismo modelo, distinto esfuerzo)

        // Reserva atómica.
        $db = db_connect();
        $db->transStart();
        if ($net = $q->network($iid)) { // cupo común de la red: también se bloquea la fila de la red
            $db->query('SELECT id FROM networks WHERE id = ? FOR UPDATE', [(int) $net['id']]);
        }
        $db->query('SELECT id FROM ai_settings WHERE institution_id = ? FOR UPDATE', [$iid]);
        if ($deny = $q->check($iid, $bucket, $uid, $f['estimate'])) {
            $db->transComplete();

            return ['error' => $deny[1], 'level' => $deny[0]];
        }
        $now = date('Y-m-d H:i:s');
        $db->table('ai_usage')->insert(['institution_id' => $iid, 'user_id' => $uid, 'bucket' => $bucket, 'feature' => $feature, 'model' => $tier['id'],
            'status' => 'reserved', 'reserved_tokens' => $f['estimate'], 'prompt_version' => $opts['prompt_version'] ?? null, 'created_at' => $now, 'updated_at' => $now]);
        $usageId = (int) $db->insertID();
        $db->transComplete();

        $t0 = microtime(true);
        try {
            $r = $this->provider()->complete([
                'model' => $tier['id'], 'effort' => $tier['effort'], 'max_tokens' => $f['max_out'], 'system' => $system,
                'messages' => $messages, 'schema' => $opts['schema'] ?? null, 'feature' => $feature, 'meta' => $opts['meta'] ?? [],
            ]);
        } catch (AiException $e) {
            $db->table('ai_usage')->where('id', $usageId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 255),
                'latency_ms' => (int) ((microtime(true) - $t0) * 1000), 'updated_at' => date('Y-m-d H:i:s')]);

            return ['error' => $e->getMessage(), 'level' => 'provider'];
        }

        $tokens = $r['input_tokens'] + $r['output_tokens'];
        // Precio por millón; con un prompt por encima de long_from tokens rige el tramo largo para toda la llamada.
        $long   = isset($tier['long_from']) && $r['input_tokens'] > $tier['long_from'];
        $cost   = $r['simulated'] ? 0 : ($r['input_tokens'] * ($long ? $tier['in_long'] : $tier['in']) + $r['output_tokens'] * ($long ? $tier['out_long'] : $tier['out'])) / 1e6;
        $db->table('ai_usage')->where('id', $usageId)->update([
            'status' => 'done', 'input_tokens' => $r['input_tokens'], 'output_tokens' => $r['output_tokens'], 'tokens' => $tokens,
            'cost_usd' => round($cost, 6), 'model' => $r['model'], 'latency_ms' => (int) ((microtime(true) - $t0) * 1000), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        service('notify')->aiThreshold($iid, $uid, $bucket);

        return ['text' => $r['text'], 'simulated' => $r['simulated'], 'tokens' => $tokens];
    }
}
