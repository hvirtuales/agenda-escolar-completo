<?php

namespace App\Database\Seeds;

use App\Models\UserModel;
use CodeIgniter\Database\Seeder;

/**
 * Datos de demostración: plantillas de rol y el colegio de demostración (SchoolDemoSeeder: «Colegio Horizonte»).
 * Uso: php spark db:seed DemoSeeder
 *
 * Solo en DESARROLLO crea además un superadministrador de prueba (admin@agendaescolar.co, contraseña DEMO_PASSWORD) si
 * no hay ninguno. En producción nunca: el superadministrador lo crea el instalador con la contraseña que se elija.
 */
class DemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Agenda-2026';

    public function run()
    {
        service('rbac')->ensureBaseTemplates();
        if (ENVIRONMENT === 'development' && ! model(UserModel::class)->where('is_superadmin', 1)->countAllResults()) {
            model(UserModel::class)->insert([
                'name' => 'Equipo Agenda Escolar', 'email' => 'admin@agendaescolar.co', 'password_hash' => password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT),
                'is_superadmin' => 1, 'status' => 'active',
            ]);
            echo "Superadministrador de desarrollo: admin@agendaescolar.co\n";
        }
        $this->call(SchoolDemoSeeder::class);
    }
}
