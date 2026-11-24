<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mi agenda (documento maestro 4.2: calendario de actividades y fechas límite).
 *
 * - agenda_entries: notas de agenda. offering_id NULL = recordatorio personal (solo lo ve quien lo creó);
 *   con offering_id = nota del docente para los estudiantes del grupo de esa oferta (tarea, salida, material…).
 *   entry_time NULL = todo el día.
 * - agenda_done: quién marcó una nota como hecha (cada estudiante marca la suya).
 * Las fechas de retos y evaluaciones no se copian aquí: salen de activity_assignments.
 */
class CreateAgenda extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'created_by'     => ['type' => 'INT', 'unsigned' => true],
            'offering_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'title'          => ['type' => 'VARCHAR', 'constraint' => 160],
            'notes'          => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'entry_date'     => ['type' => 'DATE'],
            'entry_time'     => ['type' => 'TIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'entry_date']);
        $this->forge->addKey(['offering_id', 'entry_date']);
        $this->forge->addKey(['created_by', 'entry_date']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('agenda_entries', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'       => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'entry_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'  => ['type' => 'INT', 'unsigned' => true],
            'done_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['entry_id', 'user_id']);
        $this->forge->addForeignKey('entry_id', 'agenda_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('agenda_done', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('agenda_done', true);
        $this->forge->dropTable('agenda_entries', true);
    }
}
