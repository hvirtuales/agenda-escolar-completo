<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Archivos de los cuadernos (bloques «Imagen» y «Archivo»). Se guardan en writable/uploads/notebooks/{institución}/,
 * fuera de la carpeta web, y solo se descargan por controlador con permiso sobre la asignatura (App\Libraries\NotebookFiles).
 * Cuentan para el almacenamiento del plan (plans.storage_gb).
 */
class CreatePageFiles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'workspace_id'   => ['type' => 'INT', 'unsigned' => true],
            'kind'           => ['type' => 'VARCHAR', 'constraint' => 10], // image | file
            'file_path'      => ['type' => 'VARCHAR', 'constraint' => 80],
            'file_name'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime'           => ['type' => 'VARCHAR', 'constraint' => 120],
            'size'           => ['type' => 'INT', 'unsigned' => true],
            'uploaded_by'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'workspace_id']);
        $this->forge->createTable('page_files', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('page_files', true);
    }
}
