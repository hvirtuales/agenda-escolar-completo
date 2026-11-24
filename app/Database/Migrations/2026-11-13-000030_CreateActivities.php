<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Actividades (documento maestro 4.4, 4.7 y 8.1), primera entrega: retos.
 *
 * - activities: el reto de una oferta. Pasos, pistas y rúbrica se guardan como JSON en TEXT (no se consultan por SQL;
 *   las tablas rubrics/rubric_criteria y question_banks llegan con las evaluaciones y la biblioteca).
 * - activity_assignments: la actividad asignada al grupo de la oferta, con fechas. Estado derivado de las fechas.
 * - submissions: una por estudiante y asignación (expediente): respuesta, pasos hechos, estado, nota y retroalimentación.
 * Estados de la actividad: draft | published | archived. De la entrega: in_progress | submitted | reviewed | returned.
 */
class CreateActivities extends Migration
{
    private array $ts = [
        'created_at' => ['type' => 'DATETIME', 'null' => true],
        'updated_at' => ['type' => 'DATETIME', 'null' => true],
    ];

    public function up()
    {
        $base = [
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
        ];

        $this->forge->addField($base + [
            'offering_id'  => ['type' => 'INT', 'unsigned' => true],
            'kind'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'challenge'],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 160],
            'objective'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'instructions' => ['type' => 'TEXT', 'null' => true],
            'deliverable'  => ['type' => 'TEXT', 'null' => true],
            'steps'        => ['type' => 'TEXT', 'null' => true],
            'hints'        => ['type' => 'TEXT', 'null' => true],
            'rubric'       => ['type' => 'TEXT', 'null' => true],
            'difficulty'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'medium'], // low | medium | high
            'mode'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'individual'], // individual | pairs | groups
            'ai_mode'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'guide'], // off | guide | explain
            'points'       => ['type' => 'INT', 'default' => 100],
            'retries'      => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'created_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'offering_id']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('offering_id', 'subject_offerings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('activities', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField($base + [
            'activity_id' => ['type' => 'INT', 'unsigned' => true],
            'group_id'    => ['type' => 'INT', 'unsigned' => true],
            'starts_at'   => ['type' => 'DATETIME'],
            'due_at'      => ['type' => 'DATETIME'],
            'allow_late'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ] + $this->ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['activity_id', 'group_id']);
        $this->forge->addKey(['institution_id', 'group_id', 'due_at']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('activity_id', 'activities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('group_id', 'class_groups', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('activity_assignments', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField($base + [
            'assignment_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'in_progress'],
            'answer'        => ['type' => 'TEXT', 'null' => true],
            'steps_done'    => ['type' => 'TEXT', 'null' => true],
            'attempt'       => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'submitted_at'  => ['type' => 'DATETIME', 'null' => true],
            'is_late'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'levels'        => ['type' => 'TEXT', 'null' => true],
            'grade'         => ['type' => 'DECIMAL', 'constraint' => '3,1', 'null' => true],
            'feedback'      => ['type' => 'TEXT', 'null' => true],
            'reviewed_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reviewed_at'   => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['assignment_id', 'user_id']);
        $this->forge->addKey(['institution_id', 'status']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('assignment_id', 'activity_assignments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('submissions', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        foreach (['submissions', 'activity_assignments', 'activities'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
