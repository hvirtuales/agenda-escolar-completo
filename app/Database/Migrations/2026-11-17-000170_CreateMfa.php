<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Verificación en dos pasos con aplicación autenticadora (TOTP, RFC 6238; documento maestro 10).
 *
 * - users.totp_secret: secreto base32 CIFRADO con la clave de la aplicación (encryption.key en .env). Si se pierde esa
 *   clave, nadie puede volver a validar códigos: hay que quitar la verificación a cada persona y volver a activarla.
 * - users.totp_enabled_at: cuándo la activó (NULL = no la tiene). users.totp_last_step: último paso de 30 s aceptado,
 *   para que un mismo código no sirva dos veces.
 * - user_recovery_codes: 10 códigos de respaldo de un solo uso, guardados como hash.
 */
class CreateMfa extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'totp_secret'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'must_change_password'],
            'totp_enabled_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'totp_secret'],
            'totp_last_step'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'totp_enabled_at'],
        ]);

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'code_hash'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'used_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_recovery_codes', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('user_recovery_codes', true);
        $this->forge->dropColumn('users', ['totp_secret', 'totp_enabled_at', 'totp_last_step']);
    }
}
