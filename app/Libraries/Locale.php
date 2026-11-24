<?php

namespace App\Libraries;

/**
 * Idioma de la interfaz (español / inglés). Úsela con service('locale').
 *
 * Se traducen las opciones de la plataforma (menús, botones, títulos, mensajes, ayuda, fechas). El contenido que crean
 * las personas (páginas, actividades, noticias, nombres de asignaturas…) se muestra tal como se escribió.
 *
 * Orden para decidir el idioma de cada petición (LocaleFilter):
 *   1. el que eligió la persona con el selector (users.locale; sin sesión, el de la sesión del navegador),
 *   2. el idioma por defecto de su institución activa (institutions.locale, en Ajustes › Institución),
 *   3. el de la plataforma (Config\App::$defaultLocale).
 * La IA responde en este mismo idioma (Ai\Gateway).
 */
class Locale
{
    public const SUPPORTED = ['es' => 'Español', 'en' => 'English'];

    private ?string $current = null;

    public static function valid(?string $l): bool
    {
        return $l !== null && isset(self::SUPPORTED[$l]);
    }

    /** Idioma que corresponde a quien hace la petición. */
    public function resolve(): string
    {
        $user = service('auth')->user();
        if ($user && self::valid($user['locale'] ?? null)) {
            return $user['locale'];
        }
        if (self::valid(session('locale'))) {
            return session('locale');
        }
        $inst = $user ? service('tenant')->institution() : null;
        if ($inst && self::valid($inst['locale'] ?? null)) {
            return $inst['locale'];
        }

        return self::valid(config('App')->defaultLocale) ? config('App')->defaultLocale : 'es';
    }

    /** Fija el idioma de esta petición (lang(), validación, fechas). */
    public function apply(): string
    {
        $this->current = $this->resolve();
        service('request')->setLocale($this->current);
        \Config\Services::language()->setLocale($this->current);

        return $this->current;
    }

    public function current(): string
    {
        return $this->current ?? $this->apply();
    }

    /** Guarda la elección de la persona (con sesión: en su cuenta; sin sesión: en la sesión del navegador). */
    public function choose(string $l): bool
    {
        if (! self::valid($l)) {
            return false;
        }
        session()->set('locale', $l);
        if ($uid = service('auth')->id()) {
            model(\App\Models\UserModel::class)->update($uid, ['locale' => $l]);
        }

        return true;
    }
}
