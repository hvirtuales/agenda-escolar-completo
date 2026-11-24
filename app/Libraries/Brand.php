<?php

namespace App\Libraries;

/**
 * Marca de la plataforma: color de acento y fuente. Los edita el superadministrador en Consola › Configuración › Marca.
 *
 * - Color: el de la consola, la página principal, el inicio de sesión y las redes o colegios sin color propio
 *   (cada colegio conserva el suyo de Ajustes › Institución). Sin nada guardado: agendaescolar.defaultAccent.
 * - Fuente: la de toda la plataforma. Las fuentes viven en public/assets/fonts (licencia OFL), sin CDN.
 * Se guardan en platform_settings (brand.accent, brand.font).
 */
class Brand
{
    /** Fuentes disponibles: clave => [nombre, pila CSS, archivos woff2 [peso => archivo] ('*' = variable)]. */
    public const FONTS = [
        'sistema'    => ['Sistema (Segoe UI)', '"Segoe UI Variable", "Segoe UI", system-ui, -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif', []],
        'inter'      => ['Inter', '"Inter", system-ui, sans-serif', ['*' => 'inter.woff2']],
        'nunito'     => ['Nunito', '"Nunito", system-ui, sans-serif', ['*' => 'nunito.woff2']],
        'montserrat' => ['Montserrat', '"Montserrat", system-ui, sans-serif', ['*' => 'montserrat.woff2']],
        'poppins'    => ['Poppins', '"Poppins", system-ui, sans-serif', [400 => 'poppins-400.woff2', 500 => 'poppins-500.woff2', 600 => 'poppins-600.woff2', 700 => 'poppins-700.woff2']],
    ];

    /** Colores sugeridos (además del selector libre). */
    public const SWATCHES = ['#15803D', '#1D4ED8', '#0E7490', '#7C3AED', '#B91C1C', '#C2410C', '#334155'];

    private static ?array $cache = null;

    /** ['accent' => '#RRGGBB', 'font' => clave de FONTS] */
    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rows = [];
        try {
            $rows = array_column(db_connect()->table('platform_settings')->like('setting', 'brand.', 'after')->get()->getResultArray(), 'value', 'setting');
        } catch (\Throwable $e) {
            // Sin base de datos (instalador): valores por defecto.
        }
        $accent = (string) ($rows['brand.accent'] ?? '');
        $font   = (string) ($rows['brand.font'] ?? 'sistema');

        return self::$cache = [
            'accent' => preg_match('/^#[0-9A-Fa-f]{6}$/', $accent) ? strtoupper($accent) : strtoupper(config('AgendaEscolar')->defaultAccent),
            'font'   => isset(self::FONTS[$font]) ? $font : 'sistema',
        ];
    }

    /** Guarda la marca. Devuelve un error o null. */
    public static function save(string $accent, string $font): ?string
    {
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
            return 'Elija un color válido.';
        }
        if (! isset(self::FONTS[$font])) {
            return 'Elija una de las fuentes de la lista.';
        }
        $db = db_connect();
        foreach (['brand.accent' => strtoupper($accent), 'brand.font' => $font] as $k => $v) {
            $row = ['value' => $v, 'updated_by' => service('auth')->id(), 'updated_at' => date('Y-m-d H:i:s')];
            $q   = $db->table('platform_settings')->where('setting', $k);
            $q->countAllResults(false) ? $q->update($row) : $db->table('platform_settings')->insert($row + ['setting' => $k]);
        }
        self::$cache = null;

        return null;
    }

    /** @font-face de una fuente (vacío para la del sistema). */
    public static function fontFace(string $key): string
    {
        [$name, , $files] = self::FONTS[$key] ?? self::FONTS['sistema'];
        $css = '';
        foreach ($files as $weight => $file) {
            $url  = base_url('assets/fonts/' . $file);
            $css .= '@font-face{font-family:"' . $name . '";font-style:normal;font-display:swap;font-weight:' . ($weight === '*' ? '100 900' : $weight) . ';src:url("' . $url . '") format("woff2");}';
        }

        return $css;
    }

    /** CSS que aplica la fuente elegida a toda la página (va en el <head>). */
    public static function css(): string
    {
        $key = self::get()['font'];
        if ($key === 'sistema') {
            return '';
        }

        return self::fontFace($key) . ':root{--font-sans:' . self::FONTS[$key][1] . ';}';
    }
}
