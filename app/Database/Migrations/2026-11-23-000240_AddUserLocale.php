<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Idioma de la interfaz elegido por la persona (selector ES/EN de la barra superior). NULL = el de su institución
 * (institutions.locale) o, si no hay, el de la plataforma (Config\App::$defaultLocale). Ver App\Libraries\Locale.
 */
class AddUserLocale extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'locale' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true, 'after' => 'tutor_tts'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'locale');
    }
}
