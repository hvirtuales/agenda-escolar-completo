<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Comunicaciones de la institución.
 *
 * - messages + message_recipients: mensajes masivos o particulares. audience = resumen legible de a quién se envió
 *   («Todos los docentes · 7.º A (familias) · 2 personas»); cada destinatario tiene su fila con read_at.
 *   priority: normal | important (lo importante llega aunque la persona apague los avisos de mensajes).
 * - news: noticias y boletines. audience = lista separada por comas de claves de rol (teacher, student, guardian,
 *   coordinator, admin) o «all». status: draft | published. cover_path: imagen en writable/uploads/news/{institución}.
 */
class CreateMessagesAndNews extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'sender_id'      => ['type' => 'INT', 'unsigned' => true],
            'subject'        => ['type' => 'VARCHAR', 'constraint' => 160],
            'body'           => ['type' => 'TEXT'],
            'audience'       => ['type' => 'VARCHAR', 'constraint' => 500],
            'priority'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'normal'],
            'recipients'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'sender_id', 'created_at']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('messages', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'message_id'     => ['type' => 'INT', 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true],
            'read_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['message_id', 'user_id']);
        $this->forge->addKey(['user_id', 'read_at']);
        $this->forge->addForeignKey('message_id', 'messages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('message_recipients', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'author_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'title'          => ['type' => 'VARCHAR', 'constraint' => 160],
            'summary'        => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'body'           => ['type' => 'TEXT'],
            'cover_path'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'audience'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'all'],
            'pinned'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'draft'],
            'published_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'status', 'published_at']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('news', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('news', true);
        $this->forge->dropTable('message_recipients', true);
        $this->forge->dropTable('messages', true);
    }
}
