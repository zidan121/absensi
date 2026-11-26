<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveLazisFromRecapPositionDefaults extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('recap_position_orders')) {
            $this->db->table('recap_position_orders')
                ->where('position_name', 'Lazis')
                ->delete();
        }
    }

    public function down()
    {
        if ($this->db->tableExists('recap_position_orders')) {
            $exists = $this->db->table('recap_position_orders')
                ->where('position_name', 'Lazis')
                ->countAllResults();
            if ($exists === 0) {
                $this->db->table('recap_position_orders')->insert([
                    'position_name' => 'Lazis',
                    'sort_order' => 5,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
