<?php

namespace App\Libraries;

/**
 * Demostración pública. Úsela con service('demo').
 *
 * - enabled(): el modo demo está activo (agendaescolar.demoMode = true en .env) o es el entorno de desarrollo.
 * - restricted(): la persona actual es una cuenta de demostración y el modo demo está activo en producción: no puede
 *   cambiar contraseña, correo ni verificación en dos pasos, ni crear o modificar cuentas, y sube archivos pequeños.
 * - accounts(): las cuentas que el login ofrece. El superadministrador solo si agendaescolar.loginShowsSuperadmin = true
 *   (únicamente en el .env local; el instalador nunca la escribe, así que en el servidor nunca aparece).
 * El colegio de demostración lo crea Database\Seeds\SchoolDemoSeeder y lo restaura el comando demo:reset.
 */
class Demo
{
    public const MAX_UPLOAD = 2 * 1024 * 1024; // subidas de las cuentas demo (2 MB)

    /** Cuentas que muestra el login: [correo, clave de rol (Auth.demo*), descripción]. Las crea SchoolDemoSeeder. */
    public const ACCOUNTS = [
        ['rectoria@horizonte.demo', 'demoRector', 'Claudia Restrepo'],
        ['coordinacion@horizonte.demo', 'demoCoordinator', 'Martha Ospina'],
        ['jorge.ramirez@horizonte.demo', 'demoTeacher', 'Jorge Ramírez · Matemáticas'],
        ['mariana.lopez@horizonte.demo', 'demoStudent', 'Mariana López · 7.º A'],
        ['familia.lopez@horizonte.demo', 'demoGuardian', 'Patricia López'],
    ];

    public function enabled(): bool
    {
        return (bool) config('AgendaEscolar')->demoMode || ENVIRONMENT === 'development';
    }

    /** ¿Las protecciones aplican a la persona actual? (solo con el modo demo activo, no en desarrollo). */
    public function restricted(): bool
    {
        $u = service('auth')->user();

        return (bool) config('AgendaEscolar')->demoMode && $u && ! empty($u['is_demo']);
    }

    /** Cuentas de demostración que existen y están activas (para el login). */
    public function accounts(): array
    {
        $list = [];
        if ($this->enabled()) {
            $emails = array_column(self::ACCOUNTS, 0);
            $have   = array_column(db_connect()->table('users')->select('email')->whereIn('email', $emails)->where('status', 'active')
                ->where('is_superadmin', 0)->get()->getResultArray(), 'email');
            $list   = array_values(array_filter(self::ACCOUNTS, static fn ($a) => in_array($a[0], $have, true)));
        }
        // Solo en el equipo local: también la cuenta del superadministrador de desarrollo.
        if (config('AgendaEscolar')->loginShowsSuperadmin) {
            $super = db_connect()->table('users')->select('email, name')->where('is_superadmin', 1)->where('status', 'active')
                ->orderBy('id')->get()->getRowArray();
            if ($super) {
                array_unshift($list, [$super['email'], 'demoSuper', $super['name']]); // de primera
            }
        }

        return $list;
    }
}
