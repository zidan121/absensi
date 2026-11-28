<?php

namespace App\\Database\\Migrations;

use CodeIgniter\\Database\\Migration;

class AddSditFingerprintName extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('fingerprint_name', 'sdit_employees')) {
            $this->forge->addColumn('sdit_employees', [
                'fingerprint_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'full_name',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('fingerprint_name', 'sdit_employees')) {
            $this->forge->dropColumn('sdit_employees', 'fingerprint_name');
        }
    }
}
