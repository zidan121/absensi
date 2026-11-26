<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRecapPositionOrders extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'position_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('position_name', 'uq_recap_position_name');
        $this->forge->createTable('recap_position_orders', true);

        $defaults = ['KS', 'Guru / Waka', 'Guru / Kur', 'Guru', 'Lazis'];
        foreach ($defaults as $index => $position) {
            $this->db->table('recap_position_orders')->insert([
                'position_name' => $position,
                'sort_order' => $index + 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('recap_position_orders', true);
    }
}
