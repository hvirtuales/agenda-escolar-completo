<?php

namespace App\Libraries\Ai;

/**
 * Clave de API del proveedor de IA.
 *
 * - El superadministrador puede guardarla desde Consola › Configuración. Se guarda cifrada (encryption.key del .env,
 *   AES-256 autenticado) en platform_settings ('ai.api_key'); nunca en claro, nunca se vuelve a mostrar completa
 *   (solo los últimos 4 caracteres) y nunca se escribe en la auditoría ni en los registros.
 * - Si no hay clave guardada en la consola, se usa ANTHROPIC_API_KEY del .env (como antes).
 * - Si cambia encryption.key, la clave guardada deja de poder leerse: se registra el error y se cae al .env.
 */
class KeyStore
{
    private const SETTING = 'ai.api_key';

    private ?array $cache = null;

    /** ['key' => string, 'source' => 'console'|'env'|null] */
    public function resolve(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        if (($k = $this->stored()) !== '') {
            return $this->cache = ['key' => $k, 'source' => 'console'];
        }
        $env = trim((string) env('ANTHROPIC_API_KEY', ''));

        return $this->cache = ['key' => $env, 'source' => $env !== '' ? 'env' : null];
    }

    public function key(): string
    {
        return $this->resolve()['key'];
    }

    /** Para la pantalla: de dónde viene y los últimos 4 caracteres. Nunca la clave. */
    public function describe(): array
    {
        $r = $this->resolve();

        return [
            'source'      => $r['source'],
            'hint'        => $r['key'] !== '' ? '…' . substr($r['key'], -4) : null,
            'envPresent'  => trim((string) env('ANTHROPIC_API_KEY', '')) !== '',
            'unreadable'  => $this->row() !== null && $this->stored() === '',
            'updatedAt'   => $this->row()['updated_at'] ?? null,
        ];
    }

    /** Formato básico de una clave de Anthropic (sin espacios, prefijo sk-ant-). */
    public static function looksValid(string $key): bool
    {
        return (bool) preg_match('/^sk-ant-[A-Za-z0-9_\-]{20,250}$/', $key);
    }

    /**
     * Comprueba la clave contra la API (lista de modelos, no consume tokens).
     * Devuelve null si funciona, 'invalid' si el proveedor la rechaza o 'unreachable' si no hubo respuesta clara.
     */
    public function test(string $key): ?string
    {
        $cfg = config('Ai');
        try {
            $res = \Config\Services::curlrequest(['timeout' => 15, 'http_errors' => false], null, null, false)
                ->get(preg_replace('#/messages$#', '/models', $cfg->endpoint) . '?limit=1', [
                    'headers' => ['x-api-key' => $key, 'anthropic-version' => $cfg->apiVersion],
                ]);
        } catch (\Throwable $e) {
            log_message('error', 'IA: no se pudo probar la clave: ' . $e->getMessage());

            return 'unreachable';
        }
        $code = $res->getStatusCode();

        return match (true) {
            $code === 200                 => null,
            $code === 401, $code === 403  => 'invalid',
            default                       => 'unreachable',
        };
    }

    public function save(string $key): void
    {
        $value = base64_encode(service('encrypter')->encrypt($key));
        $this->write($value);
    }

    public function clear(): void
    {
        db_connect()->table('platform_settings')->where('setting', self::SETTING)->delete();
        $this->cache = null;
    }

    /* ---------- Interno ---------- */

    private function row(): ?array
    {
        return db_connect()->table('platform_settings')->where('setting', self::SETTING)->get()->getRowArray() ?: null;
    }

    private function stored(): string
    {
        $row = $this->row();
        if (! $row || (string) $row['value'] === '') {
            return '';
        }
        try {
            return (string) service('encrypter')->decrypt(base64_decode((string) $row['value'], true) ?: '');
        } catch (\Throwable $e) {
            log_message('error', 'IA: no se pudo descifrar la clave guardada en la consola (¿cambió encryption.key?)');

            return '';
        }
    }

    private function write(string $value): void
    {
        $db  = db_connect();
        $row = ['value' => $value, 'updated_by' => service('auth')->id(), 'updated_at' => date('Y-m-d H:i:s')];
        $q   = $db->table('platform_settings')->where('setting', self::SETTING);
        $q->countAllResults(false) ? $q->update($row) : $db->table('platform_settings')->insert($row + ['setting' => self::SETTING]);
        $this->cache = null;
    }
}
