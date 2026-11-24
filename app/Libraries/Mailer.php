<?php

namespace App\Libraries;

/**
 * Correo saliente de la plataforma (un solo servidor SMTP para todos los colegios).
 *
 * El superadministrador lo configura en Consola › Configuración › Correo. Se guarda en platform_settings
 * (mail.host, mail.port, mail.crypto, mail.user, mail.from, mail.from_name) y la contraseña CIFRADA (mail.pass, con
 * service('encrypter')); nunca se vuelve a mostrar. Si en la consola no hay nada, se usa lo del .env (email.*).
 *
 * Uso: (new Mailer())->send($para, $asunto, $texto, $responderA = null, $nombreRemitente = null).
 * Cada colegio puede salir con su propio nombre de remitente y «responder a» (su correo), sin servidor propio.
 */
class Mailer
{
    private const KEYS   = ['host', 'port', 'crypto', 'user', 'from', 'from_name'];
    public const CRYPTOS = ['tls' => 'STARTTLS (puerto 587)', 'ssl' => 'SSL/TLS (puerto 465)', '' => 'Sin cifrado (no recomendado)'];

    private function rows(): array
    {
        return array_column(db_connect()->table('platform_settings')->like('setting', 'mail.', 'after')->get()->getResultArray(), 'value', 'setting');
    }

    /** ¿Hay configuración guardada en la consola? */
    public function fromConsole(): bool
    {
        return isset($this->rows()['mail.host']);
    }

    /** Configuración efectiva sin la contraseña: consola o, si no hay, el .env. */
    public function describe(): array
    {
        $r = $this->rows();
        if (isset($r['mail.host'])) {
            return ['source' => 'console', 'host' => $r['mail.host'], 'port' => (int) ($r['mail.port'] ?? 587), 'crypto' => (string) ($r['mail.crypto'] ?? 'tls'),
                'user' => (string) ($r['mail.user'] ?? ''), 'from' => (string) ($r['mail.from'] ?? ''), 'from_name' => (string) ($r['mail.from_name'] ?? ''),
                'has_pass' => ($r['mail.pass'] ?? '') !== '', 'configured' => ($r['mail.from'] ?? '') !== ''];
        }
        $e = config('Email');

        $none = $e->fromEmail === ''; // sin nada configurado: se sugieren los valores habituales (587 + STARTTLS)

        return ['source' => $none ? 'none' : 'env', 'host' => $e->SMTPHost, 'port' => $none ? 587 : $e->SMTPPort, 'crypto' => $none ? 'tls' : $e->SMTPCrypto,
            'user' => $e->SMTPUser, 'from' => $e->fromEmail, 'from_name' => $e->fromName, 'has_pass' => $e->SMTPPass !== '',
            'configured' => $e->fromEmail !== '' && ($e->protocol !== 'smtp' || $e->SMTPHost !== '')];
    }

    public function configured(): bool
    {
        return $this->describe()['configured'];
    }

    /**
     * Guarda la configuración. $pass vacío conserva la contraseña guardada. Devuelve un error o null.
     *
     * @param array{host:string,port:string,crypto:string,user:string,from:string,from_name:string} $in
     */
    public function save(array $in, string $pass): ?string
    {
        $host = trim($in['host'] ?? '');
        $port = (int) ($in['port'] ?? 0);
        $from = trim($in['from'] ?? '');
        if ($host === '' || ! preg_match('/^[a-z0-9.-]{3,190}$/i', $host)) {
            return 'Escriba el servidor SMTP (por ejemplo, mail.sudominio.com).';
        }
        if ($port < 1 || $port > 65535) {
            return 'El puerto debe ser un número entre 1 y 65535 (normalmente 587 o 465).';
        }
        if (! array_key_exists($in['crypto'] ?? 'x', self::CRYPTOS)) {
            return 'Elija el tipo de cifrado.';
        }
        if (! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return 'Escriba un correo de remitente válido.';
        }
        $vals = ['host' => $host, 'port' => (string) $port, 'crypto' => $in['crypto'], 'user' => mb_substr(trim($in['user'] ?? ''), 0, 190),
            'from' => $from, 'from_name' => mb_substr(trim($in['from_name'] ?? ''), 0, 80)];
        foreach ($vals as $k => $v) {
            $this->write('mail.' . $k, $v);
        }
        if ($pass !== '') {
            $this->write('mail.pass', base64_encode(service('encrypter')->encrypt($pass)));
        }

        return null;
    }

    /** Borra la configuración de la consola (vuelve a usarse la del .env, si la hay). */
    public function clear(): void
    {
        db_connect()->table('platform_settings')->like('setting', 'mail.', 'after')->delete();
    }

    /** Envía un correo de texto. Devuelve un error legible o null si salió bien. */
    public function send(string $to, string $subject, string $text, ?string $replyTo = null, ?string $fromName = null): ?string
    {
        $d = $this->describe();
        if (! $d['configured']) {
            return 'El correo saliente no está configurado.';
        }
        $email = $this->client();
        $email->setFrom($d['from'], $fromName ?: ($d['from_name'] ?: config('AgendaEscolar')->appName));
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $email->setReplyTo($replyTo);
        }
        $email->setTo($to)->setSubject($subject)->setMessage($text);
        try {
            if ($email->send(false)) {
                return null;
            }
            $debug = strip_tags($email->printDebugger(['headers']));
        } catch (\Throwable $e) {
            $debug = $e->getMessage();
        }
        log_message('error', 'Correo: no se pudo enviar a ' . $to . ': ' . mb_substr($debug, 0, 500));

        return 'El servidor no aceptó el correo. ' . self::hint($debug);
    }

    /** Cliente de CodeIgniter con la configuración efectiva (la contraseña se descifra solo aquí). */
    private function client(): \CodeIgniter\Email\Email
    {
        $r = $this->rows();
        if (! isset($r['mail.host'])) {
            return \Config\Services::email(null, false); // .env
        }
        $pass = '';
        if (($r['mail.pass'] ?? '') !== '') {
            try {
                $pass = (string) service('encrypter')->decrypt(base64_decode((string) $r['mail.pass'], true) ?: '');
            } catch (\Throwable $e) {
                log_message('error', 'Correo: no se pudo descifrar la contraseña SMTP (¿cambió encryption.key?)');
            }
        }

        return \Config\Services::email([
            'protocol' => 'smtp', 'SMTPHost' => $r['mail.host'], 'SMTPPort' => (int) ($r['mail.port'] ?? 587), 'SMTPCrypto' => (string) ($r['mail.crypto'] ?? 'tls'),
            'SMTPUser' => (string) ($r['mail.user'] ?? ''), 'SMTPPass' => $pass, 'SMTPTimeout' => 10, 'mailType' => 'text', 'charset' => 'UTF-8',
        ], false);
    }

    /** Pista en palabras según la respuesta del servidor. */
    private static function hint(string $debug): string
    {
        $d = strtolower($debug);

        return match (true) {
            str_contains($d, 'auth') || str_contains($d, '535') => 'Revise el usuario y la contraseña.',
            str_contains($d, 'connect') || str_contains($d, 'timed out') || str_contains($d, 'unable to') => 'No se pudo conectar: revise el servidor, el puerto y el cifrado.',
            str_contains($d, 'sender') || str_contains($d, '553') || str_contains($d, '550') => 'El servidor rechazó el remitente: use un correo de la cuenta configurada.',
            default => 'Revise los datos y el registro en writable/logs.',
        };
    }

    private function write(string $key, string $value): void
    {
        $db  = db_connect();
        $row = ['value' => $value, 'updated_by' => service('auth')->id(), 'updated_at' => date('Y-m-d H:i:s')];
        $q   = $db->table('platform_settings')->where('setting', $key);
        $q->countAllResults(false) ? $q->update($row) : $db->table('platform_settings')->insert($row + ['setting' => $key]);
    }
}
