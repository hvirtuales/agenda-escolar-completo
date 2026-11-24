<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Solicitudes de demostración desde la página pública (landing). No pertenecen a ninguna institución.
 * Al llegar una, se avisa en la campana a los superadministradores. status: new | contacted | closed.
 */
class CreateDemoRequests extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 160],
            'phone'       => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'institution' => ['type' => 'VARCHAR', 'constraint' => 160],
            'role'        => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'city'        => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'students'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'message'     => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'new'],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->createTable('demo_requests', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('demo_requests', true);
    }
}
