<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modo demostración pública (agendaescolar.demoMode = true en .env):
 * - users.is_demo: cuentas del colegio de demostración (SchoolDemoSeeder). Con el modo demo activo no pueden cambiar
 *   contraseña, correo ni verificación en dos pasos, ni crear o modificar cuentas; el colegio se restaura cada noche
 *   (php spark demo:reset, con cron).
 * - Plan «Demostración»: cupo de IA acotado para que el gasto de la clave de la plataforma tenga techo.
 */
class AddDemoMode extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'is_demo' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'is_superadmin'],
        ]);
        if (! $this->db->table('plans')->where('name', 'Demostración')->countAllResults()) {
            $now = date('Y-m-d H:i:s');
            // ≈ 9.000 consultas al mes (unas 300 al día) con Haiku 5.5: unos USD 6 al mes como máximo.
            $this->db->table('plans')->insert(['name' => 'Demostración', 'price' => null, 'max_users' => 0, 'storage_gb' => 1, 'monthly_tokens' => 27000000,
                'advanced_model' => 1, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'is_demo');
        $this->db->table('plans')->where('name', 'Demostración')->delete();
    }
}
