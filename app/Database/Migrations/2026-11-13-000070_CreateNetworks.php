<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Redes educativas: un nivel opcional por encima de las instituciones (grupo de colegios).
 *
 * - La institución sigue siendo el tenant: sus datos siguen aislados. La red solo agrupa.
 * - networks: monthly_tokens > 0 = cupo común de IA para todos sus colegios (un nivel más de 5.8); 0 = sin cupo común.
 * - institutions.network_id: a qué red pertenece (nulo = independiente).
 * - network_users: quién administra la red. role: admin (consola de la red y entrar a sus colegios, auditado) |
 *   analyst (solo la consola: reportes agregados, sin entrar a los colegios).
 * - question_banks.network_shared: el banco se ofrece a los demás colegios de la red, que pueden copiarlo.
 */
class CreateNetworks extends Migration
{
    public function up()
    {
        $ts = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 160],
            'slug'           => ['type' => 'VARCHAR', 'constraint' => 160],
            'city'           => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'accent_color'   => ['type' => 'CHAR', 'constraint' => 7, 'default' => '#1D4ED8'],
            'monthly_tokens' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active | suspended
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('networks', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addColumn('institutions', [
            'network_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'plan'],
        ]);
        $this->forge->addForeignKey('network_id', 'networks', 'id', 'SET NULL', 'CASCADE', 'institutions_network_fk');
        $this->forge->processIndexes('institutions');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'network_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'role'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'admin'], // admin | analyst
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['network_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('network_id', 'networks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('network_users', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addColumn('question_banks', [
            'network_shared' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'description'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('question_banks', 'network_shared');
        $this->forge->dropTable('network_users', true);
        $this->forge->dropForeignKey('institutions', 'institutions_network_fk');
        $this->forge->dropColumn('institutions', 'network_id');
        $this->forge->dropTable('networks', true);
    }
}
