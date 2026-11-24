<?php

/**
 * Ayudas de interfaz del design system (íconos, badges, marca, formatos).
 * Base tomada de las otras plataformas (PETI/PMS); lo propio de Agenda Escolar está al final.
 */

if (! function_exists('icon')) {
    /** Ícono lucide. Siempre decorativo: acompáñelo de texto o de aria-label en el botón. */
    function icon(string $name, int $size = 16, string $class = ''): string
    {
        return '<i data-lucide="' . esc($name, 'attr') . '" class="icon ' . esc($class, 'attr')
            . '" style="width:' . $size . 'px;height:' . $size . 'px" aria-hidden="true"></i>';
    }
}

if (! function_exists('badge')) {
    /** Badge de estado. Tonos: ok, warn, danger, neutral, accent. */
    function badge(string $text, string $tone = 'neutral'): string
    {
        return '<span class="badge badge-' . esc($tone, 'attr') . '">' . esc($text) . '</span>';
    }
}

if (! function_exists('my_avatar')) {
    /** Avatar de la persona en sesión: su foto (perfil/foto) o sus iniciales. Decorativo: el nombre siempre va al lado. */
    function my_avatar(array $user, int $px = 24): string
    {
        $style = 'width:' . $px . 'px;height:' . $px . 'px' . ($px >= 48 ? ';font-size:var(--text-base)' : '');
        if (! empty($user['avatar_path'])) {
            return '<img class="avatar" src="' . site_url('perfil/foto') . '?v=' . esc(substr(md5($user['avatar_path']), 0, 8), 'url') . '" alt="" style="' . $style . ';object-fit:cover">';
        }

        return '<span class="avatar" aria-hidden="true" style="' . $style . '">' . esc(initials($user['name'])) . '</span>';
    }
}

if (! function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $out   = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $out ?: '?';
    }
}

if (! function_exists('t')) {
    /**
     * Texto traducido (app/Language/{es,en}/…). Si la clave no existe, devuelve $fallback (o la clave). Los {0}, {1}…
     * se reemplazan con $args (formato ICU de CodeIgniter: también admite plurales).
     */
    function t(string $key, array $args = [], ?string $fallback = null): string
    {
        $s = lang($key, $args);
        if (is_array($s)) {
            return $fallback ?? $key;
        }

        return $s === $key && $fallback !== null ? $fallback : (string) $s;
    }
}

if (! function_exists('date_fmt')) {
    /** Arma una fecha con el patrón del idioma (Dates.*): {d} día, {m}/{M} mes corto/largo, {w}/{W} día corto/largo, {y} año. */
    function date_fmt(string $pattern, int $ts): string
    {
        $ms = (array) lang('Dates.monthsShort');
        $ml = (array) lang('Dates.months');
        $ds = (array) lang('Dates.daysShort');
        $dl = (array) lang('Dates.days');
        $n  = (int) date('n', $ts) - 1;
        $w  = (int) date('N', $ts);

        return strtr((string) lang('Dates.' . $pattern), [
            '{d}' => date('j', $ts), '{y}' => date('Y', $ts), '{m}' => $ms[$n] ?? '', '{M}' => $ml[$n] ?? '', '{w}' => $ds[$w] ?? '', '{W}' => ucfirst($dl[$w] ?? ''),
        ]);
    }
}

if (! function_exists('date_human')) {
    /** «23 nov 2026, 3:00 pm» / «Nov 23, 2026, 3:00 pm» */
    function date_human(?string $datetime, bool $withTime = true): string
    {
        if (! $datetime) {
            return '—';
        }
        $ts  = strtotime($datetime);
        $out = date_fmt('human', $ts);

        return $withTime ? $out . ', ' . date((string) lang('Dates.time'), $ts) : $out;
    }
}

if (! function_exists('date_short')) {
    /** «mar 10 nov» / «Tue, Nov 10» (con el año si no es el actual) */
    function date_short(?string $date, bool $weekday = true): string
    {
        if (! $date) {
            return '—';
        }
        $ts = strtotime($date);

        return date_fmt($weekday ? 'short' : 'shortNoDay', $ts) . (date('Y', $ts) !== date('Y') ? ' ' . date('Y', $ts) : '');
    }
}

if (! function_exists('date_long')) {
    /** «Lunes 23 de noviembre» / «Monday, November 23» */
    function date_long(?string $date): string
    {
        return $date ? date_fmt('long', strtotime($date)) : '—';
    }
}
if (! function_exists('plural')) {
    function plural(int $n, string $one, string $many): string
    {
        return $n . ' ' . ($n === 1 ? $one : $many);
    }
}

/* ---------- Marca dinámica (port de brand.ts) ---------- */

if (! function_exists('brand_valid')) {
    function brand_valid(?string $hex): string
    {
        return ($hex && preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) ? strtoupper($hex) : \App\Libraries\Brand::get()['accent']; // marca de la plataforma
    }
}

if (! function_exists('brand_luminance')) {
    function brand_luminance(string $hex): float
    {
        $rgb = sscanf($hex, '#%02x%02x%02x');
        $lin = array_map(static function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }
}

if (! function_exists('brand_contrast')) {
    function brand_contrast(string $a, string $b): float
    {
        $la = brand_luminance($a);
        $lb = brand_luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}

if (! function_exists('brand_best_ink')) {
    function brand_best_ink(string $bg, string $light = '#FFFFFF', string $dark = '#18181B'): string
    {
        return brand_contrast($bg, $light) >= brand_contrast($bg, $dark) ? $light : $dark;
    }
}

if (! function_exists('brand_vars')) {
    /** Variables CSS de marca: --brand, --brand-ink, --brand-dark, --brand-ink-dark. */
    function brand_vars(?string $hex): string
    {
        $brand = brand_valid($hex);
        $ink   = brand_best_ink($brand);

        // Versión para modo oscuro: aclarar en pasos de 5 % hasta lograr 4,5:1 contra el fondo oscuro.
        [$r, $g, $b] = sscanf($brand, '#%02x%02x%02x');
        $dark        = $brand;
        for ($i = 0; $i <= 20 && brand_contrast($dark, '#0F0F11') < 4.5; $i++) {
            $t    = $i * 0.05;
            $dark = sprintf('#%02X%02X%02X', (int) round($r + (255 - $r) * $t), (int) round($g + (255 - $g) * $t), (int) round($b + (255 - $b) * $t));
        }
        $inkDark = brand_best_ink($dark, '#FFFFFF', '#18181B');

        return "--brand:{$brand};--brand-ink:{$ink};--brand-dark:{$dark};--brand-ink-dark:{$inkDark}";
    }
}

if (! function_exists('field_error')) {
    /** Texto de error de validación de un campo (tras redirect()->withInput()). */
    function field_error(string $field): string
    {
        $errors = session('errors') ?? [];

        return isset($errors[$field]) ? '<p class="field-error">' . esc($errors[$field]) . '</p>' : '';
    }
}

/* ---------- Agenda Escolar ---------- */

if (! function_exists('rail_items')) {
    /**
     * Ítems de la barra global filtrados por permisos y marcados como actuales.
     *
     * @param list<string> $perms permisos de la persona en la institución activa
     */
    function rail_items(array $perms, ?array $items = null): array
    {
        $out  = ['top' => [], 'bottom' => []];
        $best = '';
        foreach ($items ?? config('AgendaEscolar')->rail as $item) {
            if ($item['can'] !== null && array_intersect($item['can'], $perms) === []) {
                continue;
            }
            $item['hit'] = '';
            foreach ($item['match'] as $m) {
                if ((url_is($m) || url_is($m . '/*')) && strlen($m) > strlen($item['hit'])) {
                    $item['hit'] = $m;
                }
            }
            if (strlen($item['hit']) > strlen($best)) {
                $best = $item['hit'];
            }
            $out[empty($item['bottom']) ? 'top' : 'bottom'][] = $item;
        }
        // Solo queda activo el ítem con la coincidencia más específica (estudiante vs. estudiante/retos).
        foreach ($out as &$list) {
            foreach ($list as &$item) {
                $item['active'] = $best !== '' && $item['hit'] === $best;
            }
            unset($item);
        }
        unset($list);

        return $out;
    }
}

if (! function_exists('cat_color')) {
    /**
     * Color de categoría (dato configurable por la institución) validado para emitirlo crudo en style="--cat:…".
     * esc($hex, 'css') no sirve: produce «\23 …», que no es un color válido.
     */
    function cat_color(?string $hex): string
    {
        return ($hex && preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) ? strtoupper($hex) : '#6B6B76';
    }
}

if (! function_exists('page_kind')) {
    /** Ícono y nombre de cada tipo de página del cuaderno. */
    function page_kind(string $kind): array
    {
        return match ($kind) {
            'challenge' => ['icon' => 'target', 'label' => 'Reto'],
            'quiz'      => ['icon' => 'clipboard-list', 'label' => 'Evaluación'],
            'resources' => ['icon' => 'paperclip', 'label' => 'Recursos'],
            default     => ['icon' => 'file-text', 'label' => 'Página'],
        };
    }
}

if (! function_exists('content_status_badge')) {
    /** Estados de contenido y actividad (4.3) y de asignación (4.4). */
    function content_status_badge(string $status): string
    {
        return match ($status) {
            'draft'     => badge('Borrador', 'neutral'),
            'review'    => badge('En revisión', 'warn'),
            'published' => badge('Publicado', 'ok'),
            'archived'  => badge('Archivado', 'neutral'),
            'scheduled' => badge('Programada', 'neutral'),
            'open'      => badge('Abierta', 'accent'),
            'closed'    => badge('Cerrada', 'neutral'),
            default     => badge($status),
        };
    }
}

if (! function_exists('notif_icon')) {
    /** Ícono de cada tipo de notificación. */
    function notif_icon(string $kind): string
    {
        return [
            'activity' => 'list-checks', 'submission' => 'inbox', 'grade' => 'award', 'returned' => 'undo-2',
            'due' => 'alarm-clock', 'missed' => 'calendar-x', 'ai' => 'sparkles', 'guardian' => 'link',
        ][$kind] ?? 'bell';
    }
}

if (! function_exists('audit_label')) {
    /** Nombre legible de una acción de la auditoría. */
    function audit_label(string $action): string
    {
        return [
            'auth.login' => 'Inició sesión', 'superadmin.enter' => 'El superadministrador entró', 'network.enter' => 'Un administrador de red entró',
            'ai.settings.update' => 'Cambió los ajustes de IA', 'ai.grant.create' => 'Concedió cupo adicional de IA', 'ai.grant.revoke' => 'Retiró un cupo adicional de IA',
            'bank.create' => 'Creó un banco de preguntas', 'bank.delete' => 'Eliminó un banco de preguntas', 'bank.share' => 'Compartió un banco con la red',
            'bank.unshare' => 'Dejó de compartir un banco', 'bank.copy' => 'Copió un banco de la red', 'guardian.link' => 'Vinculó un acudiente',
            'guardian.unlink' => 'Desvinculó un acudiente', 'users.created' => 'Invitó a una persona', 'users.updated' => 'Actualizó a una persona', 'users.password_reset' => 'Asignó una contraseña temporal', 'messages.send' => 'Envió un mensaje', 'news.publish' => 'Publicó una noticia', 'site.publish' => 'Publicó la página pública del colegio', 'platform.mail.update' => 'Configuró el correo saliente', 'platform.brand.update' => 'Cambió la marca de la plataforma', 'users.import' => 'Importó personas', 'users.import_credentials' => 'Descargó las contraseñas de una importación', 'platform.mail.test' => 'Envió un correo de prueba', 'platform.mail.clear' => 'Quitó la configuración de correo', 'platform.mail.denied' => 'Intento fallido de cambiar el correo saliente', 'news.delete' => 'Eliminó una noticia', 'network.message' => 'Envió un mensaje de la red', 'network.news' => 'Publicó una noticia de la red', 'network.event' => 'Creó un evento de la red', 'network.withdraw' => 'Retiró una comunicación de la red', 'users.mfa_reset' => 'Quitó la verificación en dos pasos de una persona',
            'mfa.enable' => 'Activó la verificación en dos pasos', 'mfa.disable' => 'Desactivó la verificación en dos pasos', 'mfa.codes' => 'Generó nuevos códigos de respaldo',
            'institution.updated' => 'Actualizó los datos de la institución', 'network.save' => 'Guardó una red educativa', 'network.delete' => 'Eliminó una red educativa',
            'network.member.add' => 'Agregó un administrador de red', 'network.member.remove' => 'Quitó un administrador de red',
            'platform.institution.create' => 'Creó una institución', 'platform.institution.update' => 'Actualizó una institución',
            'platform.institution.status' => 'Cambió el estado de una institución', 'platform.plan.save' => 'Guardó un plan', 'platform.plan.delete' => 'Eliminó un plan',
            'platform.audit.export' => 'Exportó la auditoría', 'platform.demo.update' => 'Actualizó una solicitud de demostración', 'platform.assistant.update' => 'Actualizó el asistente de la interfaz', 'impersonation.start' => 'Empezó a ver como otra persona', 'impersonation.end' => 'Terminó de ver como otra persona', 'impersonation.denied' => 'Intento fallido de ver como otra persona', 'platform.contact.update' => 'Cambió el contacto comercial', 'platform.db.migrate' => 'Actualizó la base de datos', 'platform.db.failed' => 'Falló la actualización de la base de datos', 'platform.db.denied' => 'Intento fallido de actualizar la base de datos', 'platform.ai_key.update' => 'Cambió la clave de API de IA', 'platform.ai_key.clear' => 'Quitó la clave de API de IA', 'platform.ai_key.denied' => 'Intento fallido de cambiar la clave de IA', 'profile.password' => 'Cambió su contraseña',
            'library.template.create' => 'Creó una plantilla', 'library.template.delete' => 'Eliminó una plantilla',
            'library.resource.create' => 'Agregó un recurso a la biblioteca', 'library.resource.delete' => 'Eliminó un recurso de la biblioteca',
        ][$action] ?? $action;
    }
}

if (! function_exists('settings_tabs')) {
    /**
     * Secciones de Ajustes que la persona puede abrir: clave => [etiqueta, ruta]. Las usa la barra de pestañas y
     * «/ajustes», que lleva a la primera disponible. «Página pública» abre el editor en vivo de la página del colegio.
     */
    function settings_tabs(): array
    {
        $t = service('tenant');

        return array_filter([
            'institucion'    => $t->can('branding.manage', 'academic.structure.manage') ? ['Institución', 'ajustes/institucion'] : null,
            'estructura'     => $t->can('academic.structure.manage') ? ['Estructura académica', 'ajustes/estructura'] : null,
            'usuarios'       => $t->can('tenant.users.view', 'tenant.users.manage') ? ['Personas', 'ajustes/usuarios'] : null,
            'ia'             => $t->can('ai.settings.manage') ? ['Inteligencia artificial', 'ajustes/ia'] : null,
            'pagina-publica' => $t->can('tenant.users.manage') ? ['Página pública', 'pagina-publica'] : null,
        ]);
    }
}

if (! function_exists('temp_password')) {
    /** Contraseña temporal de 12 caracteres en grupos de 4, sin caracteres que se confunden (0/O, 1/l/I). */
    function temp_password(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $temp     = '';
        for ($i = 0; $i < 12; $i++) {
            $temp .= ($i && $i % 4 === 0 ? '-' : '') . $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $temp;
    }
}

if (! function_exists('person_id')) {
    /** Cómo identificar a una persona en pantalla: su correo o, si no tiene, «Doc. TI 1.012.345.678». */
    function person_id(array $u): string
    {
        if (! empty($u['email'])) {
            return $u['email'];
        }
        $doc = \App\Models\UserModel::docLabel($u['doc_type'] ?? null, $u['doc_number'] ?? null);

        return $doc !== '' ? 'Doc. ' . $doc : '—';
    }
}
