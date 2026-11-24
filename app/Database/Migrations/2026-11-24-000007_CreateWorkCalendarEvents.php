<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWorkCalendarEvents extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'event_date' => ['type' => 'DATE'],
            'target_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'unit_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'employee_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'title' => ['type' => 'VARCHAR', 'constraint' => 150],
            'late_after' => ['type' => 'TIME', 'null' => true],
            'note' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['event_date', 'unit_code']);
        $this->forge->addKey(['event_date', 'employee_id']);
        $this->forge->createTable('work_calendar_events', true);
    }

    public function down()
    {
        $this->forge->dropTable('work_calendar_events', true);
    }
}
