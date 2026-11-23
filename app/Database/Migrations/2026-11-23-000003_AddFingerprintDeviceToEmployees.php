<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFingerprintDeviceToEmployees extends Migration
{
    public function up()
    {
        $this->forge->addColumn('employees', [
            'fingerprint_device_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'fingerprint_pin',
            ],
        ]);
        $this->forge->addKey('employees', ['fingerprint_device_id'], false, false, 'idx_employee_fingerprint_device');
        $this->forge->addKey('employees', ['fingerprint_device_id', 'fingerprint_pin'], false, false, 'idx_employee_device_pin');
    }

    public function down()
    {
        $this->forge->dropColumn('employees', 'fingerprint_device_id');
    }
}
