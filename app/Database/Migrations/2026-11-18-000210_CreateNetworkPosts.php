<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Comunicaciones de una red educativa: un registro por envío (mensaje, noticia o evento) y una copia en cada colegio
 * elegido (messages / news / events con network_post_id). Las copias se ven con la marca de la red y el colegio no las
 * edita. La red solo ve cifras agregadas por colegio (enviados, leídos), nunca listas de personas.
 */
class CreateNetworkPosts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'network_id' => ['type' => 'INT', 'unsigned' => true],
            'kind'       => ['type' => 'VARCHAR', 'constraint' => 10], // message | news | event
            'title'      => ['type' => 'VARCHAR', 'constraint' => 160],
            'audience'   => ['type' => 'VARCHAR', 'constraint' => 60],
            'schools'    => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'author_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['network_id', 'created_at']);
        $this->forge->addForeignKey('network_id', 'networks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('network_posts', true, ['ENGINE' => 'InnoDB']);

        foreach (['messages', 'news', 'events'] as $t) {
            $this->forge->addColumn($t, ['network_post_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true]]);
            $this->db->query("ALTER TABLE `{$t}` ADD INDEX `{$t}_network_post` (`network_post_id`)");
        }
    }

    public function down()
    {
        foreach (['messages', 'news', 'events'] as $t) {
            $this->forge->dropColumn($t, 'network_post_id');
        }
        $this->forge->dropTable('network_posts', true);
    }
}
