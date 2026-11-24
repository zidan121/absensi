<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUnitAttendanceRules extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unit_code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'effective_from' => ['type' => 'DATE'],
            'effective_to' => ['type' => 'DATE', 'null' => true],
            'late_after' => ['type' => 'TIME'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['unit_code', 'effective_from'], 'uq_unit_rule_effective');
        $this->forge->addKey(['unit_code', 'effective_from'], false, false, 'idx_unit_rule_lookup');
        $this->forge->createTable('unit_attendance_rules', true);

        $defaults = [
            'KBIT' => '07:45:59',
            'RAIT' => '07:15:59',
            'SDIT' => '07:00:59',
            'SMPIT' => '07:00:59',
            'YAYASAN' => '07:45:59',
        ];
        foreach ($defaults as $unit => $lateAfter) {
            $this->db->table('unit_attendance_rules')->insert([
                'unit_code' => $unit,
                'effective_from' => '2019-01-01',
                'effective_to' => null,
                'late_after' => $lateAfter,
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('unit_attendance_rules', true);
    }
}
