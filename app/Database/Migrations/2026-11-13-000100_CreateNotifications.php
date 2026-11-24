<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Notificaciones dentro de la plataforma (campana de la barra superior).
 *
 * - Una fila por persona y aviso. institution_id: dónde ocurrió (nulo = plataforma); al abrirla se entra a esa institución.
 * - dedupe_key: evita repetir el mismo aviso (p. ej. «vence mañana» de una actividad para un estudiante). Único por persona.
 */
class CreateNotifications extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true],
            'kind'           => ['type' => 'VARCHAR', 'constraint' => 30], // activity | submission | grade | returned | due | missed | ai | guardian
            'title'          => ['type' => 'VARCHAR', 'constraint' => 200],
            'body'           => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'url'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'dedupe_key'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'read_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'read_at']);
        $this->forge->addUniqueKey(['user_id', 'dedupe_key']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notifications', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('notifications', true);
    }
}
