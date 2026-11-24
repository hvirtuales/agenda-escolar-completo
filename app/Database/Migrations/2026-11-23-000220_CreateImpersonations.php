<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Suplantación de soporte («Ver como»): el superadministrador ve la plataforma como una persona de una institución, en
 * solo lectura y por tiempo limitado. Cada sesión queda aquí (quién, a quién, por qué, desde dónde, cuándo y cómo
 * terminó); la aplicación solo inserta y cierra filas, nunca las borra.
 */
class CreateImpersonations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'impersonator_id' => ['type' => 'INT', 'unsigned' => true],
            'target_id'       => ['type' => 'INT', 'unsigned' => true],
            'institution_id'  => ['type' => 'INT', 'unsigned' => true],
            'reason'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'ip_address'      => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'started_at'      => ['type' => 'DATETIME'],
            'expires_at'      => ['type' => 'DATETIME'],
            'ended_at'        => ['type' => 'DATETIME', 'null' => true],
            'end_reason'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], // manual | expired | logout | revoked
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('impersonator_id');
        $this->forge->addKey('target_id');
        $this->forge->addKey('institution_id');
        $this->forge->createTable('impersonations', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('impersonations', true);
    }
}
