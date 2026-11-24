<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración propia de la plataforma: nombre y barra global.
 * Nombre comercial: Agenda Escolar (decidido el 2026-11-13).
 */
class AgendaEscolar extends BaseConfig
{
    public string $appName = 'Agenda Escolar';

    /**
     * Verificación en dos pasos obligatoria para superadministradores, administradores de red y quien gestiona personas.
     * En .env: agendaescolar.mfaForAdmins = false para desactivar la obligación (por ejemplo, con las cuentas de demostración).
     */
    public bool $mfaForAdmins = true;

    /** Solo para el equipo local: el login muestra también la cuenta del superadministrador. Nunca en el servidor. */
    public bool $loginShowsSuperadmin = false;

    /**
     * Demostración pública (en .env: agendaescolar.demoMode = true). El login muestra las cuentas del colegio de
     * demostración (sin el superadministrador) y esas cuentas (users.is_demo) quedan protegidas: no cambian contraseña,
     * correo ni verificación en dos pasos, no crean ni modifican cuentas, suben archivos pequeños y la IA usa el plan
     * «Demostración». El colegio se restaura con «php spark demo:reset» (cron diario). Ver App\Libraries\Demo.
     */
    public bool $demoMode = false;

    /** Contacto del botón flotante de las páginas públicas (también editable en Consola › Configuración). */
    public string $contactWhatsapp = '';
    public string $contactEmail    = '';

    /** Color de acento cuando no hay institución activa (login, selector). */
    public string $defaultAccent = '#1D4ED8';

    /**
     * Barra global (rail). route: ruta CI4 · match: prefijos que la marcan como actual · can: permisos (null = todos).
     * 'bottom' => true la manda al pie del rail.
     */
    public array $rail = [
        ['label' => 'Inicio',         'icon' => 'house',          'route' => 'inicio',      'match' => ['inicio'],                  'can' => null],
        // Agenda: también para quien gestiona los eventos del colegio (se crean y editan desde aquí).
        ['label' => 'Mi agenda',      'icon' => 'calendar-days',  'route' => 'agenda',      'match' => ['agenda', 'eventos'],       'can' => ['content.edit', 'tenant.users.manage']],
        ['label' => 'Cuadernos',      'icon' => 'notebook-tabs',  'route' => 'cuadernos',   'match' => ['cuadernos'],               'can' => ['content.edit']],
        ['label' => 'Actividades',    'icon' => 'list-checks',    'route' => 'actividades', 'match' => ['actividades'],             'can' => ['activities.create']],
        ['label' => 'Entregas',       'icon' => 'inbox',          'route' => 'entregas',    'match' => ['entregas'],                'can' => ['submissions.review']],
        // Seguimiento: pestañas «Por grupo» (estudiantes) y «Por asignatura» (reportes).
        ['label' => 'Seguimiento',    'icon' => 'chart-column',   'route' => 'estudiantes', 'match' => ['estudiantes', 'reportes'], 'can' => ['reports.view'], 'nav' => 'seguimiento'],
        ['label' => 'Docentes',       'icon' => 'presentation',   'route' => 'docentes',    'match' => ['docentes'],                'can' => ['academic.structure.manage']],
        // Comunicaciones: pestañas «Mensajes» y «Noticias».
        ['label' => 'Comunicaciones', 'icon' => 'mail',           'route' => 'mensajes',    'match' => ['mensajes', 'noticias'],    'can' => null, 'nav' => 'comunicaciones'],
        ['label' => 'Biblioteca',     'icon' => 'library',        'route' => 'biblioteca',  'match' => ['biblioteca'],              'can' => ['content.edit', 'activities.create']],
        // Ajustes (incluye la página pública del colegio): visible con el permiso de cualquiera de sus pestañas.
        ['label' => 'Ajustes',        'icon' => 'settings',       'route' => 'ajustes',     'match' => ['ajustes', 'pagina-publica'], 'can' => ['branding.manage', 'academic.structure.manage', 'tenant.users.view', 'tenant.users.manage', 'ai.settings.manage'], 'bottom' => true],
        ['label' => 'Ayuda',          'icon' => 'circle-help',    'route' => 'ayuda',       'match' => ['ayuda'],                   'can' => null, 'bottom' => true],
    ];

    /** Barra global de la consola de plataforma (superadministrador). */
    public array $platformRail = [
        ['label' => 'Instituciones', 'icon' => 'school',        'route' => 'plataforma/instituciones', 'match' => ['plataforma/instituciones'], 'can' => null],
        ['label' => 'Solicitudes',   'icon' => 'inbox',         'route' => 'plataforma/solicitudes',   'match' => ['plataforma/solicitudes'],   'can' => null],
        ['label' => 'Redes',         'icon' => 'network',       'route' => 'plataforma/redes',         'match' => ['plataforma/redes'],         'can' => null],
        ['label' => 'Planes',        'icon' => 'layers',        'route' => 'plataforma/planes',        'match' => ['plataforma/planes'],        'can' => null],
        ['label' => 'IA',            'icon' => 'sparkles',      'route' => 'plataforma/consumo',       'match' => ['plataforma/consumo'],       'can' => null],
        ['label' => 'Auditoría',     'icon' => 'scroll-text',   'route' => 'plataforma/auditoria',     'match' => ['plataforma/auditoria'],     'can' => null],
        ['label' => 'Configuración', 'icon' => 'settings',      'route' => 'plataforma/configuracion', 'match' => ['plataforma/configuracion'], 'can' => null, 'bottom' => true],
    ];

    /** Barra global de la consola de una red educativa (administradores y analistas de la red). */
    public array $networkRail = [
        ['label' => 'Resumen',               'icon' => 'chart-column', 'route' => 'red',                'match' => ['red'],                'can' => null],
        ['label' => 'Comunicaciones',        'icon' => 'megaphone',    'route' => 'red/comunicaciones', 'match' => ['red/comunicaciones'], 'can' => null],
        ['label' => 'Biblioteca compartida', 'icon' => 'library',      'route' => 'red/biblioteca',     'match' => ['red/biblioteca'],     'can' => null],
        ['label' => 'Administradores',       'icon' => 'users',        'route' => 'red/administradores', 'match' => ['red/administradores'], 'can' => null],
        ['label' => 'Ayuda',                 'icon' => 'circle-help',  'route' => 'ayuda',              'match' => ['ayuda'],              'can' => null, 'bottom' => true],
    ];

    /** Barra global del acudiente (solo ve a sus estudiantes vinculados). */
    public array $guardianRail = [
        ['label' => 'Mis estudiantes', 'icon' => 'users',         'route' => 'acudiente',        'match' => ['acudiente'],             'can' => null],
        ['label' => 'Agenda',          'icon' => 'calendar-days', 'route' => 'acudiente/agenda', 'match' => ['acudiente/agenda'],      'can' => null],
        ['label' => 'Comunicaciones',  'icon' => 'mail',          'route' => 'mensajes',         'match' => ['mensajes', 'noticias'],  'can' => null, 'nav' => 'comunicaciones'],
        ['label' => 'Ayuda',           'icon' => 'circle-help',   'route' => 'ayuda',            'match' => ['ayuda'],                 'can' => null, 'bottom' => true],
    ];

    /** Barra global del estudiante. */
    public array $studentRail = [
        // Inicio incluye el progreso (el antiguo «Mi progreso» redirige aquí).
        ['label' => 'Inicio',          'icon' => 'house',         'route' => 'estudiante',             'match' => ['estudiante', 'estudiante/progreso'], 'can' => null],
        ['label' => 'Mi agenda',       'icon' => 'calendar-days', 'route' => 'estudiante/agenda',      'match' => ['estudiante/agenda'],    'can' => null],
        ['label' => 'Mis asignaturas', 'icon' => 'notebook-tabs', 'route' => 'estudiante/asignaturas', 'match' => ['estudiante/asignaturas'], 'can' => null],
        ['label' => 'Mis actividades', 'icon' => 'list-checks',   'route' => 'estudiante/actividades', 'match' => ['estudiante/actividades', 'estudiante/retos', 'estudiante/evaluaciones'], 'can' => null],
        ['label' => 'Comunicaciones',  'icon' => 'mail',          'route' => 'mensajes',               'match' => ['mensajes', 'noticias'], 'can' => null, 'nav' => 'comunicaciones'],
        ['label' => 'Ayuda',           'icon' => 'circle-help',   'route' => 'ayuda?desde=estudiante', 'match' => ['ayuda'],                'can' => null, 'bottom' => true],
    ];
}
