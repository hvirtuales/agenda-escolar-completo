<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Base de la plataforma (documento maestro 7 y 8.1): instituciones, personas, roles y permisos, membresías y auditoría.
 *
 * - institutions es el tenant. Toda tabla de negocio llevará institution_id.
 * - institution_users es la membresía: un rol por institución, sede opcional y vigencia; fuera de las fechas la
 *   persona no entra a la institución aunque la membresía siga activa.
 * - roles con institution_id NULL son plantillas globales; con institution_id, roles de la institución.
 * Portabilidad (9.5): Forge, VARCHAR en vez de ENUM, TINYINT(1) para banderas, TEXT para JSON.
 */
class CreateBase extends Migration
{
    public function up()
    {
        $ts = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 160],
            'slug'         => ['type' => 'VARCHAR', 'constraint' => 160],
            'tax_id'       => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'dane_code'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'email'        => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'phone'        => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'city'         => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'country'      => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'Colombia'],
            'timezone'     => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'America/Bogota'],
            'locale'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'es'],
            'accent_color' => ['type' => 'CHAR', 'constraint' => 7, 'default' => '#1D4ED8'],
            'logo_path'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'plan'         => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Prueba'],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active | trial | suspended
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('institutions', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 120],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 160],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'is_superadmin' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active | inactive
            'last_login_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'template_key'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'source_template_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'name'               => ['type' => 'VARCHAR', 'constraint' => 60],
            'description'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'name']);
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('roles', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'role_id'    => ['type' => 'INT', 'unsigned' => true],
            'permission' => ['type' => 'VARCHAR', 'constraint' => 60],
        ]);
        $this->forge->addPrimaryKey(['role_id', 'permission']);
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('role_permissions', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true],
            'role_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'campus_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true], // FK a campuses cuando exista la tabla
            'starts_on'      => ['type' => 'DATE', 'null' => true],
            'ends_on'        => ['type' => 'DATE', 'null' => true],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active | invited | inactive
            'last_access_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['institution_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('institution_users', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'role_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'role_name'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'user_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'action'         => ['type' => 'VARCHAR', 'constraint' => 20],
            'detail'         => ['type' => 'TEXT', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'created_at']);
        $this->forge->createTable('role_audit', true, ['ENGINE' => 'InnoDB']);

        // Auditoría general (10): solo se inserta; nunca se edita ni se borra desde la aplicación.
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'action'         => ['type' => 'VARCHAR', 'constraint' => 60],
            'detail'         => ['type' => 'TEXT', 'null' => true],
            'ip_address'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['institution_id', 'created_at']);
        $this->forge->addKey(['action', 'created_at']);
        $this->forge->createTable('audit_logs', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        foreach (['audit_logs', 'role_audit', 'institution_users', 'role_permissions', 'roles', 'users', 'institutions'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
