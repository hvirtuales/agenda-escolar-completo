<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Observaciones del docente sobre un estudiante (ficha del estudiante). Las ven los docentes que le dictan y la
 * coordinación; nunca el estudiante ni la red. Solo su autor (o la coordinación) las elimina.
 */
class CreateStudentNotes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'student_id'     => ['type' => 'INT', 'unsigned' => true],
            'author_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'offering_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true], // asignatura relacionada (opcional)
            'body'           => ['type' => 'TEXT'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'student_id']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('author_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('student_notes', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('student_notes', true);
    }
}
