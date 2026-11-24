<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 *
 * Acceso real (Fase 1): sesión (auth), institución activa (institution), permiso en la institución (can:) y consola
 * (superadmin). La pertenencia de cada registro a la institución activa se comprueba además en modelos y servicios.
 *
 */
$routes->get('/', 'LandingController::index'); // página pública; con sesión, va al inicio
$routes->post('demostracion', 'LandingController::request');
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attempt');
$routes->get('login/verificacion', 'AuthController::mfaForm');
$routes->post('login/verificacion', 'AuthController::mfaVerify');
$routes->post('login/verificacion/cancelar', 'AuthController::mfaCancel');
$routes->post('logout', 'AuthController::logout');
$routes->post('idioma', 'LocaleController::choose'); // selector ES / EN (con o sin sesión)

// Con sesión
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('instituciones', 'InstitutionSelectController::index');
    $routes->get('buscar', 'SearchController::index'); // búsqueda global (alcance según contexto y permisos)
    $routes->get('notificaciones', 'NotificationsController::index');
    $routes->get('notificaciones/(:num)', 'NotificationsController::open/$1');
    $routes->post('notificaciones/leidas', 'NotificationsController::readAll');

    // Consola de una red educativa (grupo de colegios): el acceso por red lo comprueba NetworkController
    $routes->get('red', 'NetworkController::index');
    $routes->post('red/entrar', 'NetworkController::enter');
    $routes->get('red/biblioteca', 'NetworkController::library');
    $routes->get('red/comunicaciones', 'NetworkCommsController::index');
    $routes->post('red/comunicaciones', 'NetworkCommsController::send');
    $routes->get('red/comunicaciones/(:num)', 'NetworkCommsController::show/$1');
    $routes->post('red/comunicaciones/(:num)/retirar', 'NetworkCommsController::withdraw/$1');
    $routes->get('red/administradores', 'NetworkController::members');
    $routes->post('red/administradores', 'NetworkController::addMember');
    $routes->post('red/administradores/(:num)/quitar', 'NetworkController::removeMember/$1');
    $routes->post('red/colegios/(:num)/entrar', 'NetworkController::school/$1');
    $routes->post('instituciones/cambiar', 'InstitutionSelectController::switch');
    $routes->post('suplantacion/terminar', 'ImpersonationController::stop');
    $routes->post('asistente/preguntar', 'AssistantController::ask');
    $routes->get('asistente/imagen', 'AssistantController::image');
    $routes->get('perfil', 'ProfileController::index');
    $routes->post('perfil', 'ProfileController::update');
    $routes->post('perfil/contrasena', 'ProfileController::password');
    $routes->post('perfil/dos-pasos/iniciar', 'ProfileController::mfaStart');
    $routes->post('perfil/dos-pasos/activar', 'ProfileController::mfaEnable');
    $routes->post('perfil/dos-pasos/cancelar', 'ProfileController::mfaCancel');
    $routes->post('perfil/dos-pasos/codigos', 'ProfileController::mfaCodes');
    $routes->post('perfil/dos-pasos/desactivar', 'ProfileController::mfaDisable');
    $routes->get('perfil/foto', 'ProfileController::photo');
    $routes->post('perfil/foto', 'ProfileController::uploadPhoto');
    $routes->post('perfil/foto/quitar', 'ProfileController::deletePhoto');
    $routes->get('ayuda', 'HelpController::index');
    $routes->get('ayuda/(:segment)', 'HelpController::show/$1');

    // Consola de plataforma
    $routes->group('plataforma', ['filter' => 'superadmin'], static function (RouteCollection $routes) {
        $routes->get('/', static fn () => redirect()->to('plataforma/instituciones'));
        $routes->get('redes', 'Platform\NetworksController::index');
        $routes->post('redes', 'Platform\NetworksController::save');
        $routes->post('redes/(:num)', 'Platform\NetworksController::save/$1');
        $routes->post('redes/(:num)/eliminar', 'Platform\NetworksController::delete/$1');
        $routes->post('redes/(:num)/miembros', 'Platform\NetworksController::addMember/$1');
        $routes->post('redes/(:num)/miembros/(:num)/quitar', 'Platform\NetworksController::removeMember/$1/$2');
        $routes->get('instituciones', 'Platform\ConsoleController::institutions');
        $routes->post('instituciones', 'Platform\ConsoleController::createInstitution');
        $routes->post('instituciones/(:num)', 'Platform\ConsoleController::updateInstitution/$1');
        $routes->post('instituciones/(:num)/estado', 'Platform\ConsoleController::setStatus/$1');
        $routes->get('planes', 'Platform\ConsoleController::plansPage');
        $routes->post('planes', 'Platform\ConsoleController::savePlan');
        $routes->post('planes/(:num)', 'Platform\ConsoleController::savePlan/$1');
        $routes->post('planes/(:num)/eliminar', 'Platform\ConsoleController::deletePlan/$1');
        $routes->get('consumo', 'Platform\ConsoleController::usage');
        $routes->get('consumo/exportar', 'Platform\ConsoleController::usageCsv');
        $routes->get('configuracion', 'Platform\ConsoleController::config');
        $routes->post('configuracion/ia/clave', 'Platform\ConsoleController::saveAiKey');
        $routes->post('configuracion/ia/clave/quitar', 'Platform\ConsoleController::clearAiKey');
        $routes->post('configuracion/base-de-datos', 'Platform\ConsoleController::migrate');
        $routes->post('configuracion/contacto', 'Platform\ConsoleController::saveContact');
        $routes->post('configuracion/marca', 'Platform\ConsoleController::saveBrand');
        $routes->post('configuracion/correo', 'Platform\ConsoleController::saveMail');
        $routes->post('configuracion/correo/probar', 'Platform\ConsoleController::testMail');
        $routes->post('configuracion/correo/quitar', 'Platform\ConsoleController::clearMail');
        $routes->post('asistente', 'AssistantController::saveSettings');
        $routes->post('asistente/imagen/quitar', 'AssistantController::clearImage');
        $routes->get('solicitudes', 'Platform\DemoRequestsController::index');
        $routes->get('solicitudes/exportar', 'Platform\DemoRequestsController::csv');
        $routes->post('solicitudes/(:num)', 'Platform\DemoRequestsController::update/$1');
        $routes->get('auditoria', 'Platform\ConsoleController::audit');
        $routes->get('auditoria/exportar', 'Platform\ConsoleController::auditCsv');
    });

    // Con institución activa
    $routes->group('', ['filter' => 'institution'], static function (RouteCollection $routes) {
        $routes->get('inicio', 'HomeController::index');
        $routes->get('archivos/(:num)', 'FilesController::show/$1'); // archivos de los cuadernos (permiso en NotebookFiles::canRead)
        $routes->post('suplantacion/iniciar/(:num)', 'ImpersonationController::start/$1'); // «Ver como»: solo el superadministrador (lo comprueba el controlador)

        // Comunicaciones: mensajes, noticias y eventos (reglas de quién envía o publica en sus servicios)
        $routes->get('mensajes', 'MessagesController::index');
        $routes->get('mensajes/nuevo', 'MessagesController::compose', ['filter' => 'can:content.edit']);
        $routes->get('mensajes/personas', 'MessagesController::people', ['filter' => 'can:content.edit']);
        $routes->post('mensajes/contar', 'MessagesController::count', ['filter' => 'can:content.edit']);
        $routes->post('mensajes', 'MessagesController::send', ['filter' => 'can:content.edit']);
        $routes->get('mensajes/(:num)', 'MessagesController::show/$1');
        $routes->get('noticias', 'NewsController::index');
        $routes->post('noticias', 'NewsController::save', ['filter' => 'can:tenant.users.manage']);
        $routes->get('noticias/(:num)', 'NewsController::show/$1');
        $routes->get('noticias/(:num)/imagen', 'NewsController::cover/$1');
        $routes->post('noticias/(:num)', 'NewsController::save/$1', ['filter' => 'can:tenant.users.manage']);
        $routes->post('noticias/(:num)/eliminar', 'NewsController::delete/$1', ['filter' => 'can:tenant.users.manage']);
        $routes->get('eventos', 'EventsController::index');
        $routes->post('eventos', 'EventsController::save', ['filter' => 'can:tenant.users.manage']);
        $routes->post('eventos/(:num)', 'EventsController::save/$1', ['filter' => 'can:tenant.users.manage']);
        $routes->post('eventos/(:num)/eliminar', 'EventsController::delete/$1', ['filter' => 'can:tenant.users.manage']);

        // Página pública del colegio: editor en vivo (rectoría y coordinación)
        $routes->get('pagina-publica', 'SchoolSiteController::edit', ['filter' => 'can:tenant.users.manage']);
        $routes->post('pagina-publica', 'SchoolSiteController::save', ['filter' => 'can:tenant.users.manage']);
        $routes->post('pagina-publica/publicar', 'SchoolSiteController::publish', ['filter' => 'can:tenant.users.manage']);
        $routes->post('pagina-publica/descartar', 'SchoolSiteController::discard', ['filter' => 'can:tenant.users.manage']);
        $routes->post('pagina-publica/foto', 'SchoolSiteController::upload', ['filter' => 'can:tenant.users.manage']);
        $routes->post('pagina-publica/documento', 'SchoolSiteController::uploadDocument', ['filter' => 'can:tenant.users.manage']);
        $routes->get('pagina-publica/solicitudes', 'SchoolSiteController::requests', ['filter' => 'can:tenant.users.manage']);
        $routes->post('pagina-publica/solicitudes/(:num)', 'SchoolSiteController::requestStatus/$1', ['filter' => 'can:tenant.users.manage']);

        // Mi agenda (docente en «agenda», estudiante en «estudiante/agenda»); notas y recordatorios para ambos
        $routes->get('agenda', 'AgendaController::index');
        $routes->post('agenda', 'AgendaController::store');
        $routes->post('agenda/(:num)', 'AgendaController::update/$1');
        $routes->post('agenda/(:num)/eliminar', 'AgendaController::delete/$1');
        $routes->post('agenda/(:num)/hecha', 'AgendaController::toggle/$1');

        // Docente y coordinación
        // Cuadernos (contenido real). La pertenencia a la oferta se comprueba en service('content').
        $routes->group('cuadernos', ['filter' => 'can:content.edit'], static function (RouteCollection $routes) {
            $routes->get('/', 'NotebookController::show');
            $routes->get('(:segment)', 'NotebookController::show/$1');
            $routes->get('(:segment)/(:num)', 'NotebookController::show/$1/$2');
            $routes->post('(:segment)/secciones', 'NotebookController::createSection/$1');
            $routes->post('(:segment)/secciones/(:num)', 'NotebookController::renameSection/$1/$2');
            $routes->post('(:segment)/secciones/(:num)/eliminar', 'NotebookController::deleteSection/$1/$2');
            $routes->post('(:segment)/paginas', 'NotebookController::createPage/$1');
            $routes->post('(:segment)/paginas/(:num)/guardar', 'NotebookController::save/$1/$2');
            $routes->post('(:segment)/archivos', 'NotebookController::upload/$1'); // imagen o archivo de un bloque
            $routes->post('(:segment)/paginas/(:num)/publicar', 'NotebookController::publish/$1/$2');
            $routes->post('(:segment)/paginas/(:num)/eliminar', 'NotebookController::deletePage/$1/$2');
        });
        // Actividades: retos y evaluaciones.
        $routes->get('actividades', 'ActivitiesController::index', ['filter' => 'can:activities.create']);
        $routes->post('actividades', 'ActivitiesController::create', ['filter' => 'can:activities.create']);
        $routes->get('actividades/(:num)', 'ActivitiesController::edit/$1', ['filter' => 'can:activities.create']);
        $routes->post('actividades/(:num)', 'ActivitiesController::save/$1', ['filter' => 'can:activities.create']);

        // Entregas
        $routes->get('entregas', 'SubmissionsController::index', ['filter' => 'can:submissions.review']);
        $routes->get('entregas/(:num)', 'SubmissionsController::index/$1', ['filter' => 'can:submissions.review']);
        $routes->post('entregas/(:num)/intentos/(:num)', 'SubmissionsController::reviewAttempt/$1/$2', ['filter' => 'can:submissions.grade']);
        $routes->post('entregas/(:num)/(:num)', 'SubmissionsController::grade/$1/$2', ['filter' => 'can:submissions.grade']);
        $routes->get('reportes', 'ReportsController::index', ['filter' => 'can:reports.view']);
        $routes->get('reportes/(:num)', 'ReportsController::index/$1', ['filter' => 'can:reports.view']);
        $routes->get('reportes/(:num)/exportar', 'ReportsController::export/$1', ['filter' => 'can:grades.export']);
        // Estudiantes (docente y coordinación): lista por grupo y ficha
        $routes->get('estudiantes', 'StudentsController::index', ['filter' => 'can:reports.view']);
        $routes->get('docentes', 'TeachersController::index', ['filter' => 'can:academic.structure.manage']);
        $routes->get('docentes/(:num)', 'TeachersController::show/$1', ['filter' => 'can:academic.structure.manage']);
        $routes->get('estudiantes/(:num)', 'StudentsController::show/$1', ['filter' => 'can:reports.view']);
        $routes->post('estudiantes/(:num)/notas', 'StudentsController::addNote/$1', ['filter' => 'can:reports.view']);
        $routes->post('estudiantes/(:num)/notas/(:num)/eliminar', 'StudentsController::deleteNote/$1/$2', ['filter' => 'can:reports.view']);

        // Acudiente: sus estudiantes vinculados (solo lectura)
        $routes->get('acudiente', 'GuardianController::index', ['filter' => 'can:progress.view_linked']);
        $routes->get('acudiente/agenda', 'AgendaController::guardian', ['filter' => 'can:progress.view_linked']);
        $routes->get('acudiente/(:num)', 'GuardianController::show/$1', ['filter' => 'can:progress.view_linked']);
        $routes->post('estudiantes/(:num)/acudientes', 'StudentsController::linkGuardian/$1', ['filter' => 'can:tenant.users.manage']);
        $routes->post('estudiantes/(:num)/acudientes/(:num)/quitar', 'StudentsController::unlinkGuardian/$1/$2', ['filter' => 'can:tenant.users.manage']);

        // IA: copiloto docente (JSON)
        $routes->post('ia/preguntas', 'AiController::questions', ['filter' => 'can:ai.teacher.use']);
        $routes->post('ia/cuaderno/(:num)', 'AiController::copilot/$1', ['filter' => 'can:ai.teacher.use']);
        $routes->post('ia/retroalimentacion/(:num)/(:num)', 'AiController::feedback/$1/$2', ['filter' => 'can:ai.teacher.use']);

        // Biblioteca: bancos de preguntas (los usa quien crea actividades; los edita su autor o la coordinación)
        $routes->get('biblioteca', 'LibraryController::index', ['filter' => 'can:content.edit,activities.create']);
        $routes->post('biblioteca/bancos', 'LibraryController::create', ['filter' => 'can:activities.create']);
        $routes->get('biblioteca/bancos/(:num)', 'LibraryController::show/$1', ['filter' => 'can:activities.create']);
        $routes->get('biblioteca/bancos/(:num)/preguntas', 'LibraryController::questionsJson/$1', ['filter' => 'can:activities.create']);
        $routes->post('biblioteca/bancos/(:num)', 'LibraryController::save/$1', ['filter' => 'can:activities.create']);
        $routes->post('biblioteca/bancos/(:num)/eliminar', 'LibraryController::delete/$1', ['filter' => 'can:activities.create']);
        $routes->post('biblioteca/bancos/(:num)/importar', 'LibraryController::import/$1', ['filter' => 'can:activities.create']);
        $routes->post('biblioteca/bancos/(:num)/evaluacion', 'LibraryController::random/$1', ['filter' => 'can:activities.create']);
        $routes->post('biblioteca/bancos/(:num)/compartir', 'LibraryController::share/$1', ['filter' => 'can:activities.create']);
        $routes->post('biblioteca/plantillas', 'LibraryController::templateFromPage', ['filter' => 'can:content.edit']);
        $routes->post('biblioteca/plantillas/(:num)', 'LibraryController::updateTemplate/$1', ['filter' => 'can:content.edit']);
        $routes->post('biblioteca/plantillas/(:num)/usar', 'LibraryController::useTemplate/$1', ['filter' => 'can:content.edit']);
        $routes->post('biblioteca/plantillas/(:num)/eliminar', 'LibraryController::deleteTemplate/$1', ['filter' => 'can:content.edit']);
        $routes->post('biblioteca/recursos', 'LibraryController::storeResource', ['filter' => 'can:content.edit']);
        $routes->get('biblioteca/recursos/(:num)/descargar', 'LibraryController::download/$1', ['filter' => 'can:content.edit,activities.create']);
        $routes->post('biblioteca/recursos/(:num)/eliminar', 'LibraryController::deleteResource/$1', ['filter' => 'can:content.edit']);
        $routes->get('biblioteca/red/(:num)', 'LibraryController::shared/$1', ['filter' => 'can:activities.create']);
        $routes->post('biblioteca/red/(:num)/copiar', 'LibraryController::copy/$1', ['filter' => 'can:activities.create']);

        // Ajustes de la institución
        // Ajustes: la primera sección que la persona puede abrir (settings_tabs, ui_helper).
        $routes->get('ajustes', static function () {
            $first = array_values(settings_tabs())[0][1] ?? null;

            return $first ? redirect()->to($first) : redirect()->to('inicio')->with('toast', ['type' => 'error', 'msg' => 'No tienes permiso para abrir los ajustes.']);
        });
        $routes->get('ajustes/ia', 'Settings\AiSettingsController::index', ['filter' => 'can:ai.settings.manage']);
        $routes->post('ajustes/ia', 'Settings\AiSettingsController::update', ['filter' => 'can:ai.settings.manage']);
        $routes->post('ajustes/ia/cupos', 'Settings\AiSettingsController::grant', ['filter' => 'can:ai.settings.manage']);
        $routes->post('ajustes/ia/cupos/(:num)/retirar', 'Settings\AiSettingsController::revoke/$1', ['filter' => 'can:ai.settings.manage']);

        $routes->group('ajustes', ['namespace' => 'App\Controllers\Settings'], static function (RouteCollection $routes) {
            // Institución: datos e identidad visual
            $routes->get('institucion', 'InstitutionController::edit', ['filter' => 'can:branding.manage,academic.structure.manage']);
            $routes->post('institucion', 'InstitutionController::update', ['filter' => 'can:branding.manage']);

            // Estructura académica
            $routes->get('estructura', 'StructureController::index', ['filter' => 'can:academic.structure.manage']);
            $routes->get('estructura/grupos/(:num)', 'StructureController::group/$1', ['filter' => 'can:academic.structure.manage']);
            $routes->post('estructura/grupos/(:num)/matricular', 'StructureController::enroll/$1', ['filter' => 'can:academic.structure.manage']);
            $routes->post('estructura/grupos/(:num)/retirar/(:num)', 'StructureController::unenroll/$1/$2', ['filter' => 'can:academic.structure.manage']);
            $routes->post('estructura/oferta/(:num)/docentes', 'StructureController::teachers/$1', ['filter' => 'can:academic.structure.manage']);
            $routes->post('estructura/(:segment)/(:num)/eliminar', 'StructureController::delete/$1/$2', ['filter' => 'can:academic.structure.manage']);
            $routes->post('estructura/(:segment)/(:num)', 'StructureController::save/$1/$2', ['filter' => 'can:academic.structure.manage']);
            $routes->post('estructura/(:segment)', 'StructureController::save/$1', ['filter' => 'can:academic.structure.manage']);

            // Personas
            $routes->get('usuarios', 'UsersController::index', ['filter' => 'can:tenant.users.view,tenant.users.manage']);
            $routes->post('usuarios', 'UsersController::store', ['filter' => 'can:tenant.users.manage']);
            $routes->post('usuarios/(:num)', 'UsersController::update/$1', ['filter' => 'can:tenant.users.manage']);
            $routes->post('usuarios/(:num)/contrasena', 'UsersController::resetPassword/$1', ['filter' => 'can:tenant.users.manage']);
            $routes->post('usuarios/(:num)/dos-pasos', 'UsersController::resetMfa/$1', ['filter' => 'can:tenant.users.manage']);

            // Importador masivo (estudiantes, acudientes y matrícula); exige además estructura académica (lo comprueba el controlador)
            $routes->get('usuarios/importar', 'ImportController::index', ['filter' => 'can:tenant.users.manage']);
            $routes->get('usuarios/importar/plantilla/(xlsx|csv)', 'ImportController::template/$1', ['filter' => 'can:tenant.users.manage']);
            $routes->post('usuarios/importar', 'ImportController::analyze', ['filter' => 'can:tenant.users.manage']);
            $routes->post('usuarios/importar/confirmar', 'ImportController::confirm', ['filter' => 'can:tenant.users.manage']);
            $routes->get('usuarios/importar/credenciales/(:segment)', 'ImportController::credentials/$1', ['filter' => 'can:tenant.users.manage']);
        });

        // Estudiante (y vista previa del docente)
        $routes->group('estudiante', ['filter' => 'can:content.view'], static function (RouteCollection $routes) {
            $routes->get('/', 'StudentController::index');
            $routes->get('retos/(:num)', 'StudentActivityController::show/$1'); // estudiante o vista previa del grupo
            $routes->post('retos/(:num)', 'StudentActivityController::save/$1', ['filter' => 'can:activities.submit']);
            $routes->post('retos/(:num)/tutor', 'AiController::tutor/$1', ['filter' => 'can:ai.student.use']);
            $routes->post('paginas/(:num)/tutor', 'AiController::study/$1', ['filter' => 'can:ai.student.use']);
            $routes->get('evaluaciones/(:num)', 'StudentQuizController::show/$1'); // estudiante o vista previa del grupo
            $routes->get('evaluaciones/(:num)/vista-previa', 'StudentQuizController::preview/$1', ['filter' => 'can:content.edit']);
            $routes->post('evaluaciones/(:num)/iniciar', 'StudentQuizController::start/$1', ['filter' => 'can:activities.submit']);
            $routes->get('evaluaciones/(:num)/intento/(:num)', 'StudentQuizController::attempt/$1/$2', ['filter' => 'can:activities.submit']);
            $routes->post('evaluaciones/(:num)/intento/(:num)/guardar', 'StudentQuizController::save/$1/$2', ['filter' => 'can:activities.submit']);
            $routes->post('evaluaciones/(:num)/intento/(:num)/terminar', 'StudentQuizController::finish/$1/$2', ['filter' => 'can:activities.submit']);
            $routes->get('asignaturas', 'StudentController::notebook');
            $routes->get('asignaturas/(:segment)', 'StudentController::notebook/$1');
            $routes->get('asignaturas/(:segment)/(:segment)', 'StudentController::notebook/$1/$2');
            // Direcciones anteriores («Mis cuadernos», hasta 2026-11-17): redirigen para no romper enlaces guardados
            $routes->get('cuadernos', 'StudentController::legacyNotebook');
            $routes->get('cuadernos/(:any)', 'StudentController::legacyNotebook/$1');
            $routes->get('progreso', 'StudentController::progress');
            $routes->get('agenda', 'AgendaController::student');
            $routes->get('actividades', 'StudentController::activities');
        });
    });
});

// Solo desarrollo y solo desde este equipo: iniciar sesión como una cuenta de demostración (capturas y pruebas).
if (ENVIRONMENT === 'development') {
    $routes->get('dev/entrar', 'DevController::loginAs');
}

// Página pública de cada colegio: agendaescolar.co/{slug}. Van AL FINAL para no tapar ninguna ruta de la plataforma
// (las direcciones reservadas están en App\Libraries\SchoolSite::RESERVED y ningún colegio puede usarlas).
$routes->get('(:segment)/noticias/(:num)/imagen', 'SchoolSiteController::newsCover/$1/$2');
$routes->get('(:segment)/noticias/(:num)', 'SchoolSiteController::news/$1/$2');
$routes->get('(:segment)/foto/(:segment)', 'SchoolSiteController::image/$1/$2');
$routes->get('(:segment)/documento/(:segment)', 'SchoolSiteController::document/$1/$2');
$routes->post('(:segment)/admisiones', 'SchoolSiteController::admission/$1');
$routes->get('(:segment)', 'SchoolSiteController::show/$1');
