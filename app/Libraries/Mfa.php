<?php

namespace App\Libraries;

use App\Models\UserModel;

/**
 * Verificación en dos pasos con aplicación autenticadora (TOTP, RFC 6238: HMAC-SHA1, 6 dígitos, pasos de 30 s).
 * Úsela con service('mfa'). Sin dependencias externas.
 *
 * - El secreto se guarda cifrado (service('encrypter'), clave encryption.key de .env).
 * - Se acepta el código del paso actual y el de ±1 paso (relojes desfasados) y nunca dos veces el mismo paso.
 * - Obligatoria (Config\AgendaEscolar::$mfaForAdmins) para superadministradores, administradores de red y quien gestiona
 *   personas en alguna institución (permiso tenant.users.manage).
 */
class Mfa
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD   = 30;
    private const DIGITS   = 6;
    private const CODES    = 10;

    /* ---------- TOTP ---------- */

    public static function newSecret(): string
    {
        $bytes = random_bytes(20); // 160 bits, como recomienda el RFC 4226
        $out   = '';
        $bits  = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function base32Decode(string $s): string
    {
        $s    = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $s));
        $bits = '';
        foreach (str_split($s) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    /** Código de 6 dígitos de un paso de tiempo. */
    public static function code(string $secret, int $step): string
    {
        $hash   = hash_hmac('sha1', pack('N*', 0, $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value  = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function currentStep(): int
    {
        return intdiv(time(), self::PERIOD);
    }

    /** Paso que coincide con el código (actual o ±1), o null. */
    public static function matchStep(string $secret, string $code): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return null;
        }
        $now = self::currentStep();
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** Dirección otpauth:// para la aplicación (la lee el código QR). */
    public static function uri(string $email, string $secret): string
    {
        $issuer = config('AgendaEscolar')->appName;

        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /* ---------- Estado de la persona ---------- */

    public function enabled(?array $user): bool
    {
        return $user && ! empty($user['totp_enabled_at']) && ! empty($user['totp_secret']);
    }

    /** ¿Debe tenerla obligatoriamente? */
    public function required(?array $user): bool
    {
        if (! $user || ! config('AgendaEscolar')->mfaForAdmins || ! empty($user['is_demo'])) {
            return false;
        }
        if ((int) $user['is_superadmin']) {
            return true;
        }
        $db = db_connect();
        if ($db->table('network_users')->where('user_id', (int) $user['id'])->countAllResults()) {
            return true;
        }

        return $db->table('institution_users m')->join('role_permissions rp', 'rp.role_id = m.role_id')
            ->where('m.user_id', (int) $user['id'])->where('m.status', 'active')->where('rp.permission', 'tenant.users.manage')->countAllResults() > 0;
    }

    private function secretOf(array $user): ?string
    {
        try {
            return $user['totp_secret'] ? service('encrypter')->decrypt(base64_decode($user['totp_secret'], true) ?: '') : null;
        } catch (\Throwable $e) {
            log_message('error', 'MFA: no se pudo descifrar el secreto del usuario ' . $user['id'] . ' (¿cambió encryption.key?)');

            return null;
        }
    }

    /**
     * Valida un código de la aplicación para la persona (y lo marca como usado) o, si no, un código de respaldo.
     * Devuelve 'totp', 'recovery' o null.
     */
    public function verify(array $user, string $input): ?string
    {
        $secret = $this->secretOf($user);
        if ($secret && ($step = self::matchStep($secret, $input)) !== null) {
            if ($user['totp_last_step'] !== null && $step <= (int) $user['totp_last_step']) {
                return null; // ese código ya se usó
            }
            model(UserModel::class)->update((int) $user['id'], ['totp_last_step' => $step]);

            return 'totp';
        }
        $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $input));
        if (strlen($clean) === 10) {
            foreach (db_connect()->table('user_recovery_codes')->where('user_id', (int) $user['id'])->where('used_at', null)->get()->getResultArray() as $c) {
                if (password_verify($clean, $c['code_hash'])) {
                    db_connect()->table('user_recovery_codes')->where('id', $c['id'])->update(['used_at' => date('Y-m-d H:i:s')]);

                    return 'recovery';
                }
            }
        }

        return null;
    }

    /* ---------- Activar, desactivar, códigos de respaldo ---------- */

    /** Activa con el secreto pendiente si el código es correcto. Devuelve los códigos de respaldo o null. */
    public function enable(int $userId, string $secret, string $code): ?array
    {
        $step = self::matchStep($secret, $code);
        if ($step === null) {
            return null;
        }
        model(UserModel::class)->update($userId, [
            'totp_secret' => base64_encode(service('encrypter')->encrypt($secret)), 'totp_enabled_at' => date('Y-m-d H:i:s'), 'totp_last_step' => $step,
        ]);

        return $this->newRecoveryCodes($userId);
    }

    public function disable(int $userId): void
    {
        model(UserModel::class)->update($userId, ['totp_secret' => null, 'totp_enabled_at' => null, 'totp_last_step' => null]);
        db_connect()->table('user_recovery_codes')->where('user_id', $userId)->delete();
    }

    /** Reemplaza los códigos de respaldo y los devuelve en claro (se muestran una sola vez). */
    public function newRecoveryCodes(int $userId): array
    {
        $db = db_connect();
        $db->table('user_recovery_codes')->where('user_id', $userId)->delete();
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $codes    = [];
        $now      = date('Y-m-d H:i:s');
        for ($i = 0; $i < self::CODES; $i++) {
            $c = '';
            for ($j = 0; $j < 10; $j++) {
                $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($c, 0, 5) . '-' . substr($c, 5);
            $db->table('user_recovery_codes')->insert(['user_id' => $userId, 'code_hash' => password_hash($c, PASSWORD_DEFAULT), 'created_at' => $now]);
        }

        return $codes;
    }

    public function remainingCodes(int $userId): int
    {
        return db_connect()->table('user_recovery_codes')->where('user_id', $userId)->where('used_at', null)->countAllResults();
    }
}
