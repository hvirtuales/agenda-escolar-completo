<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Estructura académica (documento maestro 3.1, 3.2 y 8.1). Toda tabla lleva institution_id.
 *
 * - Curso o nivel (levels): grado, p. ej. 7.º. Grupo (class_groups): estudiantes concretos de un curso en un periodo.
 * - Oferta académica (subject_offerings): asignatura × grupo × periodo; a ella se asignan docentes y actividades.
 * Las relaciones entre tablas se validan además en el servicio Academic (mismo institution_id en ambos extremos).
 */
class CreateAcademic extends Migration
{
    private array $ts = [
        'created_at' => ['type' => 'DATETIME', 'null' => true],
        'updated_at' => ['type' => 'DATETIME', 'null' => true],
    ];

    private function base(): array
    {
        return [
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'institution_id' => ['type' => 'INT', 'unsigned' => true],
        ];
    }

    private function create(string $table, array $fields, array $keys = [], array $fks = []): void
    {
        $this->forge->addField($this->base() + $fields + $this->ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('institution_id');
        foreach ($keys as $k) {
            $this->forge->addKey($k);
        }
        $this->forge->addForeignKey('institution_id', 'institutions', 'id', 'CASCADE', 'CASCADE');
        foreach ($fks as [$col, $ref, $onDelete]) {
            $this->forge->addForeignKey($col, $ref, 'id', 'CASCADE', $onDelete);
        }
        $this->forge->createTable($table, true, ['ENGINE' => 'InnoDB']);
    }

    public function up()
    {
        $this->create('campuses', [
            'name'    => ['type' => 'VARCHAR', 'constraint' => 120],
            'address' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'status'  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
        ]);

        $this->create('academic_periods', [
            'name'      => ['type' => 'VARCHAR', 'constraint' => 80],
            'starts_on' => ['type' => 'DATE'],
            'ends_on'   => ['type' => 'DATE'],
            'is_current' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ], [['institution_id', 'starts_on']]);

        $this->create('categories', [
            'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
            'color'      => ['type' => 'CHAR', 'constraint' => 7, 'default' => '#6B6B76'],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
        ]);

        $this->create('subjects', [
            'category_id' => ['type' => 'INT', 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
        ], ['category_id'], [['category_id', 'categories', 'RESTRICT']]);

        $this->create('levels', [
            'name'       => ['type' => 'VARCHAR', 'constraint' => 60],
            'sort_order' => ['type' => 'INT', 'default' => 0],
        ]);

        $this->create('class_groups', [
            'level_id'  => ['type' => 'INT', 'unsigned' => true],
            'period_id' => ['type' => 'INT', 'unsigned' => true],
            'campus_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'name'      => ['type' => 'VARCHAR', 'constraint' => 60],
        ], ['level_id', 'period_id'], [
            ['level_id', 'levels', 'RESTRICT'], ['period_id', 'academic_periods', 'RESTRICT'], ['campus_id', 'campuses', 'SET NULL'],
        ]);

        $this->create('class_group_members', [
            'group_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'  => ['type' => 'INT', 'unsigned' => true],
        ], ['user_id'], [['group_id', 'class_groups', 'CASCADE'], ['user_id', 'users', 'CASCADE']]);

        $this->create('subject_offerings', [
            'subject_id'     => ['type' => 'INT', 'unsigned' => true],
            'group_id'       => ['type' => 'INT', 'unsigned' => true],
            'period_id'      => ['type' => 'INT', 'unsigned' => true],
            'hours_per_week' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
        ], [['group_id', 'period_id']], [
            ['subject_id', 'subjects', 'RESTRICT'], ['group_id', 'class_groups', 'CASCADE'], ['period_id', 'academic_periods', 'RESTRICT'],
        ]);

        $this->create('teaching_assignments', [
            'offering_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true],
        ], ['user_id'], [['offering_id', 'subject_offerings', 'CASCADE'], ['user_id', 'users', 'CASCADE']]);

        // Unicidad de negocio: un estudiante no se repite en un grupo, ni una asignatura en un grupo y periodo.
        $this->db->query('CREATE UNIQUE INDEX ux_group_member ON class_group_members (group_id, user_id)');
        $this->db->query('CREATE UNIQUE INDEX ux_offering ON subject_offerings (subject_id, group_id, period_id)');
        $this->db->query('CREATE UNIQUE INDEX ux_teaching ON teaching_assignments (offering_id, user_id)');
    }

    public function down()
    {
        foreach (['teaching_assignments', 'subject_offerings', 'class_group_members', 'class_groups', 'levels', 'subjects', 'categories', 'academic_periods', 'campuses'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
