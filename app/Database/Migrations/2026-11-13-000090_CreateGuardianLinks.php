<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vínculo acudiente ↔ estudiante en una institución (documento maestro 2.1, rol Acudiente). Lo crea la administración
 * (tenant.users.manage). El acudiente (permiso progress.view_linked) ve en solo lectura el progreso, las actividades y
 * la retroalimentación de sus estudiantes vinculados; nunca las observaciones internas de los docentes.
 */
class CreateGuardianLinks extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'guardian_id'    => ['type' => 'INT', 'unsigned' => true],
            'student_id'     => ['type' => 'INT', 'unsigned' => true],
            'relationship'   => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Acudiente'],
            'created_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['institution_id', 'guardian_id', 'student_id']);
        $this->forge->addKey(['institution_id', 'student_id']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('guardian_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('guardian_links', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('guardian_links', true);
    }
}
