<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeeRecapOrders extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'unit_code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'employee_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'display_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['unit_code', 'employee_id'], 'uq_recap_order_unit_employee');
        $this->forge->addKey(['unit_code', 'sort_order'], false, false, 'idx_recap_order_unit_sort');
        $this->forge->createTable('employee_recap_orders', true);
    }

    public function down()
    {
        $this->forge->dropTable('employee_recap_orders', true);
    }
}
