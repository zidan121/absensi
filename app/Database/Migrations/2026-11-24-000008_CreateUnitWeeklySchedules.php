<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUnitWeeklySchedules extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unit_code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'effective_from' => ['type' => 'DATE'],
            'effective_to' => ['type' => 'DATE', 'null' => true],
            'work_days' => ['type' => 'VARCHAR', 'constraint' => 20],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['unit_code', 'effective_from']);
        $this->forge->createTable('unit_weekly_schedules', true);

        // Initial weekly patterns: KBIT and SDIT work Monday-Friday.
        // Other units retain the six-day pattern until the administrator adjusts it.
        $defaults = [
            'KBIT' => '1,2,3,4,5',
            'SDIT' => '1,2,3,4,5',
            'RAIT' => '1,2,3,4,5,6',
            'SMPIT' => '1,2,3,4,5,6',
            'YAYASAN' => '1,2,3,4,5,6',
        ];
        foreach ($defaults as $unit => $days) {
            $this->db->table('unit_weekly_schedules')->insert([
                'unit_code' => $unit,
                'effective_from' => '2019-01-01',
                'effective_to' => null,
                'work_days' => $days,
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('unit_weekly_schedules', true);
    }
}
