<?php

namespace App\Models;

use CodeIgniter\Model;

/** Instituciones (el tenant). */
class InstitutionModel extends Model
{
    /** Zonas horarias que se ofrecen (Consola › Instituciones y Ajustes › Institución usan la misma lista). */
    public const TIMEZONES = ['America/Bogota' => 'Bogotá (UTC−5)', 'America/Mexico_City' => 'Ciudad de México (UTC−6)', 'America/Lima' => 'Lima (UTC−5)', 'America/Santiago' => 'Santiago (UTC−4/−3)', 'America/Argentina/Buenos_Aires' => 'Buenos Aires (UTC−3)'];

    protected $table          = 'institutions';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'name', 'slug', 'tax_id', 'dane_code', 'email', 'phone', 'city', 'country', 'timezone', 'locale',
        'accent_color', 'logo_path', 'plan', 'network_id', 'status',
    ];

    /** Estados con los que se puede entrar a la institución. */
    public const OPEN_STATUSES = ['active', 'trial'];

    /** Genera un slug único a partir del nombre. */
    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        helper(['text', 'url']);
        $base = url_title(convert_accented_characters($name), '-', true) ?: 'institucion';
        $slug = $base;
        $i    = 2;
        while (true) {
            $q = $this->withDeleted()->where('slug', $slug);
            if ($ignoreId !== null) {
                $q->where('id !=', $ignoreId);
            }
            // Las direcciones de la plataforma (login, inicio…) no pueden ser la página pública de un colegio.
            if (! in_array($slug, \App\Libraries\SchoolSite::RESERVED, true) && $q->countAllResults() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }
}
