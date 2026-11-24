<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Solicitudes de información de admisiones que llegan desde la página pública de cada colegio
 * (formulario «Quiero información»). Las ven rectoría y coordinación en Ajustes › Página pública › Solicitudes.
 */
class CreateSiteRequests extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 120],
            'phone'          => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'email'          => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'grade'          => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'message'        => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'new'],
            'ip_address'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'handled_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'status']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('site_requests', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('site_requests', true);
    }
}
