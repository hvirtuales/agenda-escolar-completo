<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ajustes de la plataforma editables desde la consola (clave → valor). Primer uso: el asistente flotante de la interfaz
 * (assistant.enabled, assistant.name, assistant.avatar). Los secretos siguen en .env, nunca aquí.
 */
class CreatePlatformSettings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'setting'    => ['type' => 'VARCHAR', 'constraint' => 60],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('setting');
        $this->forge->createTable('platform_settings', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('platform_settings', true);
    }
}
