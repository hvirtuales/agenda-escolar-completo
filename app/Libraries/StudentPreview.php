<?php

namespace App\Libraries;

/**
 * «Vista de estudiante» de docentes, coordinación y superadministrador: muestra la plataforma como la ve un estudiante
 * de un grupo real (cuadernos publicados y actividades asignadas a ese grupo), sin entregas, notas ni conversaciones
 * de ningún estudiante (datos de menores, documento maestro 8.3). Nada se guarda. Úsela con service('studentPreview').
 *
 * Grupos que se pueden previsualizar: coordinación (academic.structure.manage) y superadministrador, todos los del
 * periodo en curso; el docente, los grupos de las asignaturas que dicta. El grupo elegido queda en la sesión.
 */
class StudentPreview
{
    private const KEY = 'preview_group_id';

    /** ¿Quien navega es estudiante en la institución activa? (resuelve actividades y no edita contenido) */
    public function isStudent(): bool
    {
        return service('tenant')->isStudent();
    }

    /** Grupos que la persona puede previsualizar [id, name, level], en orden de nivel. */
    public function groups(): array
    {
        $academic = service('academic');
        $period   = $academic->currentPeriod();
        if (! $period) {
            return [];
        }
        if (service('auth')->isSuperadmin() || service('tenant')->can('academic.structure.manage')) {
            return $academic->groups((int) $period['id']);
        }
        $mine = array_unique(array_column(\App\Libraries\Academic::flatten($academic->catalogFor((int) service('auth')->id(), 'teacher')), 'group_id'));

        return array_values(array_filter($academic->groups((int) $period['id']), static fn ($g) => in_array((int) $g['id'], $mine, true)));
    }

    /** Grupo previsualizado: el elegido en la sesión si sigue permitido; si no, el primero. Null si no hay grupos. */
    public function group(): ?array
    {
        $groups = $this->groups();
        $chosen = (int) session(self::KEY);
        foreach ($groups as $g) {
            if ((int) $g['id'] === $chosen) {
                return $g;
            }
        }

        return $groups[0] ?? null;
    }

    public function groupId(): ?int
    {
        $g = $this->group();

        return $g ? (int) $g['id'] : null;
    }

    /** Cambia el grupo previsualizado. Devuelve false si no está permitido. */
    public function select(int $groupId): bool
    {
        foreach ($this->groups() as $g) {
            if ((int) $g['id'] === $groupId) {
                session()->set(self::KEY, $groupId);

                return true;
            }
        }

        return false;
    }
}
