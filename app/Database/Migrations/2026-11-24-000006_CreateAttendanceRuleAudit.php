<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAttendanceRuleAudit extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'rule_id' => ['type' => 'INT', 'unsigned' => true],
            'unit_code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'action' => ['type' => 'VARCHAR', 'constraint' => 20],
            'before_data' => ['type' => 'TEXT', 'null' => true],
            'after_data' => ['type' => 'TEXT', 'null' => true],
            'changed_at' => ['type' => 'DATETIME'],
            'changed_by' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('rule_id');
        $this->forge->addKey('changed_at');
        $this->forge->createTable('unit_attendance_rule_audits', true);
    }

    public function down()
    {
        $this->forge->dropTable('unit_attendance_rule_audits', true);
    }
}
