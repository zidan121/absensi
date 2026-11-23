<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmployeeSchedules extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INT', 'unsigned' => true],
            'effective_from' => ['type' => 'DATE'],
            'effective_to' => ['type' => 'DATE', 'null' => true],
            'start_time' => ['type' => 'TIME'],
            'end_time' => ['type' => 'TIME', 'null' => true],
            'late_after' => ['type' => 'TIME'],
            'work_days' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '1,2,3,4,5,6'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('employee_schedules', true);
        $this->db->query('CREATE INDEX idx_schedule_employee_effective ON employee_schedules (employee_id, effective_from)');
    }

    public function down()
    {
        $this->forge->dropTable('employee_schedules', true);
    }
}
