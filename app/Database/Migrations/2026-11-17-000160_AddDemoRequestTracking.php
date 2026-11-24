<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seguimiento de las solicitudes de demostración en la consola: notas internas, quién la atendió y, si se convirtió,
 * la institución creada. status: new | contacted | won (se creó la institución) | closed (descartada).
 */
class AddDemoRequestTracking extends Migration
{
    public function up()
    {
        $this->forge->addColumn('demo_requests', [
            'notes'          => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true, 'after' => 'status'],
            'handled_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'notes'],
            'institution_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'handled_by'],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('demo_requests', ['notes', 'handled_by', 'institution_id', 'updated_at']);
    }
}
