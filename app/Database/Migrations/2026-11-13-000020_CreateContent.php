<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contenido de los cuadernos (documento maestro 3.3, 4.3 y 8.1). Toda tabla lleva institution_id.
 *
 * - workspaces: el cuaderno; uno por oferta académica (offering_id) o libre (biblioteca, proyectos).
 * - sections → pages → page_blocks. Los bloques guardan su contenido en `data` (TEXT con JSON, sin consultas sobre él).
 * - content_versions: copia de la página (título, objetivo y bloques) cada vez que se publica, para restaurar.
 * Estados de página (4.3): draft | review | published | archived. El estudiante solo ve published.
 */
class CreateContent extends Migration
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
            'offering_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 160],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
        ] + $this->ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('institution_id');
        $this->forge->addUniqueKey('offering_id');
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('offering_id', 'subject_offerings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('workspaces', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField($base + [
            'workspace_id' => ['type' => 'INT', 'unsigned' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 120],
            'sort_order'   => ['type' => 'INT', 'default' => 0],
        ] + $this->ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['workspace_id', 'sort_order']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sections', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField($base + [
            'workspace_id' => ['type' => 'INT', 'unsigned' => true],
            'section_id'   => ['type' => 'INT', 'unsigned' => true],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 160],
            'kind'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'page'], // page | resources
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'objective'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sort_order'   => ['type' => 'INT', 'default' => 0],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['section_id', 'sort_order']);
        $this->forge->addKey(['workspace_id', 'status']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('section_id', 'sections', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('pages', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField($base + [
            'page_id'    => ['type' => 'INT', 'unsigned' => true],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 20], // heading | text | callout | formula | image | divider
            'data'       => ['type' => 'TEXT', 'null' => true],
            'sort_order' => ['type' => 'INT', 'default' => 0],
        ] + $this->ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['page_id', 'sort_order']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('page_id', 'pages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('page_blocks', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField($base + [
            'page_id'    => ['type' => 'INT', 'unsigned' => true],
            'snapshot'   => ['type' => 'TEXT'],
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['page_id', 'created_at']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('page_id', 'pages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('content_versions', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        foreach (['content_versions', 'page_blocks', 'pages', 'sections', 'workspaces'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
