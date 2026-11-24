<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Asignatura de un recordatorio personal (p. ej. el estudiante anota «Repasar fracciones» en Matemáticas).
 * Es solo una etiqueta privada: no cambia quién ve el recordatorio. No confundir con offering_id, que convierte la
 * entrada en una nota del docente para todo el grupo.
 */
class AddAgendaTagOffering extends Migration
{
    public function up()
    {
        $this->forge->addColumn('agenda_entries', [
            'tag_offering_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'offering_id'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('agenda_entries', 'tag_offering_id');
    }
}
