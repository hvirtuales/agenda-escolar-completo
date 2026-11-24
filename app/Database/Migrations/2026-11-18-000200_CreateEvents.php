<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Calendario de eventos de la institución (reuniones de padres, actos, festivos, salidas, jornadas deportivas…).
 * Los crean rectoría y coordinación; aparecen en «Mi agenda» de cada persona según su público y en «Eventos».
 * category: academic | meeting | cultural | sports | holiday | other. audience: como news (claves de rol o «all»).
 */
class CreateEvents extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'title'          => ['type' => 'VARCHAR', 'constraint' => 160],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 2000, 'null' => true],
            'location'       => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'category'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'other'],
            'starts_at'      => ['type' => 'DATETIME'],
            'ends_at'        => ['type' => 'DATETIME', 'null' => true],
            'all_day'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'audience'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'all'],
            'created_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'starts_at']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('events', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('events', true);
    }
}
