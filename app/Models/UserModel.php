<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Personas (identidad global). El correo se guarda en minúsculas para que la unicidad no dependa del cotejamiento (9.5).
 * El número de documento (opcional, único) se guarda normalizado: sin puntos, espacios ni guiones, en mayúsculas.
 * Se puede entrar con el correo o con el número de documento.
 */
class UserModel extends Model
{
    /** Tipos de documento de identidad (Colombia). */
    public const DOC_TYPES = [
        'TI' => 'Tarjeta de identidad', 'CC' => 'Cédula de ciudadanía', 'RC' => 'Registro civil', 'NUIP' => 'NUIP',
        'CE' => 'Cédula de extranjería', 'PA' => 'Pasaporte', 'PPT' => 'Permiso por protección temporal',
    ];

    protected $table         = 'users';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'email', 'doc_type', 'doc_number', 'password_hash', 'is_superadmin', 'status', 'last_login_at',
        'avatar_path', 'text_size', 'reduce_motion', 'tutor_tts', 'password_changed_at', 'must_change_password',
        'totp_secret', 'totp_enabled_at', 'totp_last_step', 'locale'];

    protected $beforeInsert = ['normalize'];
    protected $beforeUpdate = ['normalize'];

    public function findByEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));

        return $email === '' ? null : $this->where('email', $email)->first();
    }

    public function findByDocument(string $number): ?array
    {
        $n = self::normalizeDocument($number);

        return $n === '' ? null : $this->where('doc_number', $n)->first();
    }

    /** «1.012.345-678 » → «1012345678». Solo letras y números, en mayúsculas. */
    public static function normalizeDocument(?string $number): string
    {
        return mb_substr(strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) $number)), 0, 20);
    }

    /** «TI 1.012.345.678» para mostrar. */
    public static function docLabel(?string $type, ?string $number): string
    {
        if (! $number) {
            return '';
        }
        $num = ctype_digit($number) ? number_format((float) $number, 0, ',', '.') : $number;

        return trim(($type ?: '') . ' ' . $num);
    }

    protected function normalize(array $data): array
    {
        if (array_key_exists('email', $data['data'] ?? [])) {
            $e = mb_strtolower(trim((string) $data['data']['email']));
            $data['data']['email'] = $e === '' ? null : $e;
        }
        if (array_key_exists('doc_number', $data['data'] ?? [])) {
            $n = self::normalizeDocument($data['data']['doc_number']);
            $data['data']['doc_number'] = $n === '' ? null : $n;
        }
        if (array_key_exists('doc_type', $data['data'] ?? [])) {
            $t = strtoupper(trim((string) $data['data']['doc_type']));
            $data['data']['doc_type'] = isset(self::DOC_TYPES[$t]) ? $t : null;
        }

        return $data;
    }
}
