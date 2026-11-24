<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Evaluaciones (documento maestro 4.5 y 13.4). Una evaluación es una actividad de kind = 'quiz' con su configuración,
 * sus preguntas y los intentos de cada estudiante.
 *
 * - questions: tipos single | multiple | truefalse | numeric | short | long. Opciones y respuestas correctas en JSON (TEXT).
 * - attempts: cada intento guarda sus respuestas (JSON), puntaje automático, puntaje manual de las abiertas y nota.
 *   Estados: in_progress | pending_review (tiene abiertas sin calificar) | graded.
 */
class CreateQuizzes extends Migration
{
    public function up()
    {
        $this->forge->addColumn('activities', [
            'time_limit'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0, 'after' => 'retries'], // minutos; 0 = sin límite
            'max_attempts' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1, 'after' => 'time_limit'],
            'shuffle'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'max_attempts'],
            'results_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'after_close', 'after' => 'shuffle'], // after_attempt | after_close
        ]);

        $ts = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'activity_id'    => ['type' => 'INT', 'unsigned' => true],
            'type'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'text'           => ['type' => 'TEXT'],
            'options'        => ['type' => 'TEXT', 'null' => true],
            'correct'        => ['type' => 'TEXT', 'null' => true],
            'points'         => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 1],
            'topic'          => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'sort_order'     => ['type' => 'INT', 'default' => 0],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['activity_id', 'sort_order']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('activity_id', 'activities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('questions', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'assignment_id'  => ['type' => 'INT', 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true],
            'number'         => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'in_progress'],
            'answers'        => ['type' => 'TEXT', 'null' => true],
            'manual'         => ['type' => 'TEXT', 'null' => true], // puntos que el docente da a las preguntas abiertas
            'auto_score'     => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'score'          => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'max_score'      => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'grade'          => ['type' => 'DECIMAL', 'constraint' => '3,1', 'null' => true],
            'started_at'     => ['type' => 'DATETIME'],
            'finished_at'    => ['type' => 'DATETIME', 'null' => true],
            'over_time'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'feedback'       => ['type' => 'TEXT', 'null' => true],
            'reviewed_by'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['assignment_id', 'user_id', 'number']);
        $this->forge->addKey(['institution_id', 'status']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('assignment_id', 'activity_assignments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('attempts', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('attempts', true);
        $this->forge->dropTable('questions', true);
        $this->forge->dropColumn('activities', ['time_limit', 'max_attempts', 'shuffle', 'results_mode']);
    }
}
