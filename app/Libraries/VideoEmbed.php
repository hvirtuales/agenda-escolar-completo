<?php

namespace App\Libraries;

/**
 * Bloque «Video» del cuaderno: solo proveedores autorizados (documento maestro: «video embebido mediante proveedores
 * autorizados»). De lo que pegue el docente se extrae únicamente el proveedor y el identificador del video, validados con
 * expresiones estrictas; la dirección del reproductor se arma aquí, nunca con texto del usuario.
 *
 * - YouTube: se usa youtube-nocookie.com (no deja cookies de seguimiento hasta reproducir: hay estudiantes menores).
 * - Vimeo: player.vimeo.com con dnt=1 (sin seguimiento).
 * El mismo análisis existe en app.js (videoBlock) solo para la vista previa del editor; el que vale es este.
 */
class VideoEmbed
{
    public const PROVIDERS = ['youtube' => 'YouTube', 'vimeo' => 'Vimeo'];

    /** ['provider' => 'youtube'|'vimeo', 'id' => …, 'start' => segundos] o null si no es un video permitido. */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 500) {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $p = parse_url($url);
        if (! $p || empty($p['host'])) {
            return null;
        }
        $host = strtolower(preg_replace('/^(www\.|m\.)/i', '', $p['host']));
        $path = $p['path'] ?? '';
        parse_str($p['query'] ?? '', $q);
        $start = self::seconds((string) ($q['t'] ?? $q['start'] ?? ''));

        if (in_array($host, ['youtube.com', 'youtube-nocookie.com', 'music.youtube.com'], true)) {
            $id = $path === '/watch' ? (string) ($q['v'] ?? '') : (preg_match('#^/(?:embed|shorts|live|v)/([^/?]+)#', $path, $m) ? $m[1] : '');
        } elseif ($host === 'youtu.be') {
            $id = ltrim($path, '/');
        } elseif ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
            return preg_match('#^/(?:video/)?(\d{6,12})(?:/([0-9a-f]{6,20}))?/?$#', $path, $m)
                ? ['provider' => 'vimeo', 'id' => $m[1], 'hash' => $m[2] ?? '', 'start' => $start] : null;
        } else {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? ['provider' => 'youtube', 'id' => $id, 'start' => $start] : null;
    }

    /** Dirección del reproductor (solo con datos ya validados por parse()). */
    public static function src(array $v): string
    {
        if ($v['provider'] === 'vimeo') {
            return 'https://player.vimeo.com/video/' . $v['id'] . '?dnt=1' . (! empty($v['hash']) ? '&h=' . $v['hash'] : '')
                . (! empty($v['start']) ? '#t=' . (int) $v['start'] . 's' : '');
        }

        return 'https://www.youtube-nocookie.com/embed/' . $v['id'] . '?rel=0' . (! empty($v['start']) ? '&start=' . (int) $v['start'] : '');
    }

    /** «90», «1m30s» o «1h2m3s» → segundos. */
    private static function seconds(string $t): int
    {
        if (ctype_digit($t)) {
            return min((int) $t, 86400);
        }

        return preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $t, $m) && $t !== ''
            ? min(((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + (int) ($m[3] ?? 0), 86400) : 0;
    }
}
