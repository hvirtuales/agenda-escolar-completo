<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Inteligencia artificial: planes y cupos (documento maestro 5.8), consumo medido y conversaciones del tutor.
 *
 * - plans: cupo mensual de tokens por institución (institutions.plan guarda el nombre del plan).
 * - ai_settings: una fila por institución (reparto por rol en %, tope diario por persona según su rol, interruptores).
 *   La fila se bloquea (SELECT … FOR UPDATE) al reservar, para que solicitudes simultáneas no pasen el cupo.
 * - ai_usage: cada solicitud. Mientras corre queda status = reserved con reserved_tokens; al terminar, done con los
 *   tokens reales (tokens = entrada + salida, ponderados por modelo) y su costo; si falla, failed sin consumo.
 * - ai_grants: cupo adicional temporal a una persona o a un rol, con motivo y vencimiento (queda en la auditoría).
 * - ai_messages: memoria acotada del tutor por persona y contexto (p. ej. una asignación).
 */
class CreateAi extends Migration
{
    public function up()
    {
        $ts = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 40],
            'price'          => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'max_users'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'storage_gb'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'monthly_tokens' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'advanced_model' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'sort_order'     => ['type' => 'INT', 'default' => 0],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('plans', true, ['ENGINE' => 'InnoDB']);

        $now = date('Y-m-d H:i:s');
        $this->db->table('plans')->insertBatch([
            ['name' => 'Prueba', 'price' => 0, 'max_users' => 50, 'storage_gb' => 1, 'monthly_tokens' => 200000, 'advanced_model' => 0, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Básico', 'price' => 180, 'max_users' => 300, 'storage_gb' => 10, 'monthly_tokens' => 600000, 'advanced_model' => 0, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Institucional', 'price' => 520, 'max_users' => 1000, 'storage_gb' => 50, 'monthly_tokens' => 2000000, 'advanced_model' => 1, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Red educativa', 'price' => null, 'max_users' => 0, 'storage_gb' => 0, 'monthly_tokens' => 0, 'advanced_model' => 1, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id'    => ['type' => 'INT', 'unsigned' => true],
            'enabled'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'share_teacher'     => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 60],
            'share_coordinator' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 10],
            'share_student'     => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 30],
            'daily_teacher'     => ['type' => 'INT', 'unsigned' => true, 'default' => 50000],
            'daily_coordinator' => ['type' => 'INT', 'unsigned' => true, 'default' => 80000],
            'daily_student'     => ['type' => 'INT', 'unsigned' => true, 'default' => 10000],
            'label_ai'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'alert_80'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'updated_by'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('institution_id');
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ai_settings', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id'  => ['type' => 'INT', 'unsigned' => true],
            'user_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'bucket'          => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'feature'         => ['type' => 'VARCHAR', 'constraint' => 40],
            'model'           => ['type' => 'VARCHAR', 'constraint' => 60],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'reserved'], // reserved | done | failed
            'reserved_tokens' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'input_tokens'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'output_tokens'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'tokens'          => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'cost_usd'        => ['type' => 'DECIMAL', 'constraint' => '12,6', 'default' => 0],
            'latency_ms'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'prompt_version'  => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'error'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'created_at']);
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('ai_usage', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true], // persona, o
            'bucket'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], // un rol completo
            'tokens'         => ['type' => 'INT', 'unsigned' => true],
            'reason'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'expires_at'     => ['type' => 'DATETIME'],
            'created_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'expires_at']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ai_grants', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true],
            'context'        => ['type' => 'VARCHAR', 'constraint' => 40], // p. ej. assignment:12
            'role'           => ['type' => 'VARCHAR', 'constraint' => 10], // user | assistant
            'content'        => ['type' => 'TEXT'],
            'simulated'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'user_id', 'context']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ai_messages', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        foreach (['ai_messages', 'ai_grants', 'ai_usage', 'ai_settings', 'plans'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
