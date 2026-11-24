<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * users.must_change_password: la contraseña la puso otra persona (contraseña inicial o temporal). Mientras esté en 1,
 * el filtro auth solo deja entrar a Mi perfil para cambiarla; al cambiarla vuelve a 0.
 */
class AddMustChangePassword extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'must_change_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'password_changed_at'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'must_change_password');
    }
}
