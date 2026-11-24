<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Número de documento como identidad (importador masivo y entrada con documento).
 * - users.doc_type (TI, CC, RC, CE, PA, PPT, NUIP) y users.doc_number (normalizado, único en toda la plataforma:
 *   una persona = una cuenta, aunque esté en varios colegios).
 * - users.email pasa a opcional (muchos estudiantes no tienen correo); sigue siendo único cuando existe.
 */
class AddUserDocument extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('doc_number', 'users')) {
            $this->forge->addColumn('users', [
                'doc_type'   => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true, 'after' => 'email'],
                'doc_number' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'doc_type'],
            ]);
            $this->db->query('CREATE UNIQUE INDEX users_doc_number_unique ON ' . $this->db->prefixTable('users') . ' (doc_number)');
        }
        $this->forge->modifyColumn('users', ['email' => ['name' => 'email', 'type' => 'VARCHAR', 'constraint' => 160, 'null' => true]]);
    }

    public function down()
    {
        if ($this->db->fieldExists('doc_number', 'users')) {
            $this->db->query('DROP INDEX users_doc_number_unique ON ' . $this->db->prefixTable('users'));
            $this->forge->dropColumn('users', ['doc_type', 'doc_number']);
        }
    }
}
