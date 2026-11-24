<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Página pública de cada colegio (agendaescolar.co/{slug}), editable en vivo por rectoría y coordinación.
 * - institution_sites: una fila por institución. draft = lo que se edita (se guarda solo); published = lo que ve el
 *   público, copiado del borrador al pulsar «Publicar». Ambos en JSON (estructura en App\Libraries\SchoolSite).
 * - news.is_public y events.is_public: las noticias y eventos que el colegio marca para mostrarlos en su página.
 */
class CreateSchoolSites extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'draft'          => ['type' => 'MEDIUMTEXT'],
            'published'      => ['type' => 'MEDIUMTEXT', 'null' => true],
            'published_at'   => ['type' => 'DATETIME', 'null' => true],
            'published_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('institution_id');
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('institution_sites', true, ['ENGINE' => 'InnoDB']);

        foreach (['news', 'events'] as $t) {
            if (! $this->db->fieldExists('is_public', $t)) {
                $this->forge->addColumn($t, ['is_public' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'audience']]);
            }
        }
    }

    public function down()
    {
        foreach (['news', 'events'] as $t) {
            if ($this->db->fieldExists('is_public', $t)) {
                $this->forge->dropColumn($t, 'is_public');
            }
        }
        $this->forge->dropTable('institution_sites', true);
    }
}
