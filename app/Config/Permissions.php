<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Catálogo de permisos (documento maestro 2.2). Es fijo en el código: cada permiso corresponde a una acción que el
 * sistema comprueba. Los roles (plantillas globales y roles de cada institución) solo eligen cuáles tienen.
 *
 * Para agregar un permiso: declararlo aquí, protegerlo en Routes.php (filtro can:) o con service('tenant')->can(),
 * y decidir si las plantillas base lo incluyen.
 */
class Permissions extends BaseConfig
{
    /** grupo => [permiso => [etiqueta, descripción]] */
    public array $groups = [
        'Contenido' => [
            'content.view'      => ['Ver contenido', 'Ver los cuadernos y páginas publicados de sus grupos.'],
            'content.edit'      => ['Editar contenido', 'Crear y editar páginas y bloques en los cuadernos de sus ofertas.'],
            'content.publish'   => ['Publicar contenido', 'Publicar páginas, y aprobar o devolver contenido en revisión.'],
            'workspaces.manage' => ['Gestionar cuadernos', 'Crear, archivar y reorganizar cuadernos y secciones.'],
        ],
        'Actividades' => [
            'activities.create'  => ['Crear actividades', 'Crear retos y evaluaciones.'],
            'activities.publish' => ['Publicar actividades', 'Publicar retos y evaluaciones.'],
            'activities.assign'  => ['Asignar actividades', 'Asignar actividades a grupos o estudiantes, con fechas.'],
            'activities.submit'  => ['Resolver actividades', 'Resolver retos, presentar evaluaciones y entregar evidencias.'],
        ],
        'Entregas y calificaciones' => [
            'submissions.review' => ['Revisar entregas', 'Ver entregas e intentos y dejar retroalimentación.'],
            'submissions.grade'  => ['Calificar', 'Fijar la calificación oficial de una entrega.'],
            'grades.export'      => ['Exportar calificaciones', 'Descargar notas en CSV o XLSX.'],
        ],
        'Seguimiento' => [
            'reports.view'          => ['Ver reportes', 'Consultar reportes de sus grupos (coordinación: de todos).'],
            'progress.view_linked'  => ['Ver avance de estudiantes vinculados', 'Para acudientes: avance de sus estudiantes.'],
        ],
        'Inteligencia artificial' => [
            'ai.teacher.use'     => ['Usar el copiloto docente', 'Generar borradores de contenido, actividades y retroalimentación.'],
            'ai.student.use'     => ['Usar el tutor', 'Usar el tutor de IA según el modo que permita cada actividad.'],
            'ai.settings.manage' => ['Configurar la IA', 'Reparto de cupos, topes diarios, políticas y cupos adicionales.'],
        ],
        'Institución' => [
            'academic.structure.manage' => ['Estructura académica', 'Periodos, categorías, asignaturas, cursos, grupos y oferta.'],
            'tenant.users.view'         => ['Ver personas', 'Consultar la lista de personas de la institución.'],
            'tenant.users.manage'       => ['Gestionar personas', 'Invitar, cambiar rol, sede y vigencia, y desactivar personas.'],
            'tenant.roles.manage'       => ['Roles y permisos', 'Crear y editar los roles de la institución.'],
            'branding.manage'           => ['Identidad visual', 'Datos de la institución, logo y color de acento.'],
            'billing.manage'            => ['Suscripción', 'Ver el plan y gestionar la suscripción.'],
            'audit.view'                => ['Ver auditoría', 'Consultar el registro de acciones de la institución.'],
            'support.access.grant'      => ['Conceder acceso de soporte', 'Autorizar accesos temporales del equipo de soporte.'],
        ],
    ];

    /**
     * Plantillas base (se crean como plantillas globales y se copian a cada institución nueva).
     * 'all' = todos los permisos del catálogo. Corresponden a los roles de la sección 2.1 del documento maestro.
     */
    public array $baseTemplates = [
        'admin' => [
            'name'        => 'Administrador',
            'description' => 'Acceso total a la institución: configuración, personas, roles, estructura y contenidos.',
            'permissions' => 'all',
        ],
        'coordinator' => [
            'name'        => 'Coordinación académica',
            'description' => 'Estructura académica, contenidos, docentes, reportes de todos los grupos y ajustes de IA.',
            'permissions' => ['content.view', 'content.edit', 'content.publish', 'workspaces.manage', 'activities.create', 'activities.publish',
                'activities.assign', 'submissions.review', 'submissions.grade', 'grades.export', 'reports.view', 'ai.teacher.use',
                'ai.settings.manage', 'academic.structure.manage', 'tenant.users.view', 'tenant.users.manage', 'audit.view'],
        ],
        'teacher' => [
            'name'        => 'Docente',
            'description' => 'Contenido y actividades de sus ofertas, revisión de entregas, reportes de sus grupos y copiloto.',
            'permissions' => ['content.view', 'content.edit', 'content.publish', 'activities.create', 'activities.publish', 'activities.assign',
                'submissions.review', 'submissions.grade', 'grades.export', 'reports.view', 'ai.teacher.use'],
        ],
        'student' => [
            'name'        => 'Estudiante',
            'description' => 'Ve sus cuadernos, resuelve actividades y usa el tutor según lo permita cada una.',
            'permissions' => ['content.view', 'activities.submit', 'ai.student.use'],
        ],
        'guardian' => [
            'name'        => 'Acudiente',
            'description' => 'Consulta el avance de los estudiantes que tenga vinculados.',
            'permissions' => ['progress.view_linked'],
        ],
    ];

    /** Permisos que dan acceso a la administración de personas: la institución nunca puede quedarse sin alguien que los tenga. */
    public array $guardian = ['tenant.users.manage', 'tenant.roles.manage'];

    /** Lista plana de permisos válidos. */
    public function all(): array
    {
        $out = [];
        foreach ($this->groups as $perms) {
            $out = array_merge($out, array_keys($perms));
        }

        return $out;
    }

    public function label(string $perm): string
    {
        foreach ($this->groups as $perms) {
            if (isset($perms[$perm])) {
                return $perms[$perm][0];
            }
        }

        return $perm;
    }
}
