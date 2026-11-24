<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Biblioteca institucional (documento maestro 4.2 y 4.3): plantillas y recursos.
 *
 * - library_templates: copia reutilizable de un reto, una evaluación (con sus preguntas) o una página del cuaderno.
 *   payload = JSON con el contenido (TEXT, portable; se limita a 60 KB). «Usar» crea un borrador en una asignatura
 *   y suma uses.
 * - library_resources: archivo (writable/uploads/resources, se descarga por controlador) o enlace http(s).
 */
class CreateLibraryItems extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'kind'           => ['type' => 'VARCHAR', 'constraint' => 20], // challenge | quiz | page
            'title'          => ['type' => 'VARCHAR', 'constraint' => 160],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'subject_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'payload'        => ['type' => 'TEXT'],
            'uses'           => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'kind']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('library_templates', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'kind'           => ['type' => 'VARCHAR', 'constraint' => 10], // file | link
            'title'          => ['type' => 'VARCHAR', 'constraint' => 160],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'subject_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'url'            => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'file_path'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'file_name'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'mime'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'size'           => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'subject_id']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('library_resources', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('library_resources', true);
        $this->forge->dropTable('library_templates', true);
    }
}
