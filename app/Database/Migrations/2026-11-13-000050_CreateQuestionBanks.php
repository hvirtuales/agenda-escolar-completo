<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bancos de preguntas (documento maestro 4.3): colecciones de la institución, por asignatura (opcional), tema y
 * dificultad. Las evaluaciones copian las preguntas del banco (no las enlazan): editar el banco no cambia evaluaciones
 * ya presentadas.
 *
 * - question_banks: subject_id nulo = banco general (cualquier asignatura).
 * - bank_questions: mismo formato que questions (opciones y respuesta en JSON TEXT) + difficulty easy | medium | hard.
 */
class CreateQuestionBanks extends Migration
{
    public function up()
    {
        $ts = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'subject_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 120],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'created_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ] + $ts + ['deleted_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'subject_id']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('question_banks', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'bank_id'        => ['type' => 'INT', 'unsigned' => true],
            'type'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'text'           => ['type' => 'TEXT'],
            'options'        => ['type' => 'TEXT', 'null' => true],
            'correct'        => ['type' => 'TEXT', 'null' => true],
            'points'         => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 1],
            'topic'          => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'difficulty'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'medium'],
            'sort_order'     => ['type' => 'INT', 'default' => 0],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['bank_id', 'sort_order']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('bank_id', 'question_banks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('bank_questions', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('bank_questions', true);
        $this->forge->dropTable('question_banks', true);
    }
}
