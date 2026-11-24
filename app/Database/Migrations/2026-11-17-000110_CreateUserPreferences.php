<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mi perfil (documento maestro 6.6, 10 y 11.1): foto, preferencias de accesibilidad y canales de cada aviso.
 *
 * - users.avatar_path: foto recortada a 256 px en writable/uploads/avatars (se sirve por controlador, nunca desde public).
 * - users.text_size (normal | large | xlarge), reduce_motion y tutor_tts: se aplican en el layout para toda la sesión.
 * - notification_prefs: una fila por persona y tema (Notifier::TOPICS). Sin fila = valores por defecto.
 *   in_app = 0 deja de crear el aviso en la campana; email se respetará cuando la plataforma envíe correos.
 */
class CreateUserPreferences extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'avatar_path'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'status'],
            'text_size'           => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'normal', 'after' => 'avatar_path'],
            'reduce_motion'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'text_size'],
            'tutor_tts'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'reduce_motion'],
            'password_changed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'tutor_tts'],
        ]);

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'topic'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'in_app'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'email'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'topic']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notification_prefs', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('notification_prefs', true);
        $this->forge->dropColumn('users', ['avatar_path', 'text_size', 'reduce_motion', 'tutor_tts', 'password_changed_at']);
    }
}
