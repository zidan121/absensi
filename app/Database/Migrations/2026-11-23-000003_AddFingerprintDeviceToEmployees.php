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
        $this->db->query('ALTER TABLE employees ADD INDEX idx_employee_fingerprint_device (fingerprint_device_id)');
        $this->db->query('ALTER TABLE employees ADD INDEX idx_employee_device_pin (fingerprint_device_id, fingerprint_pin)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE employees DROP INDEX idx_employee_fingerprint_device');
        $this->db->query('ALTER TABLE employees DROP INDEX idx_employee_device_pin');
        $this->forge->dropColumn('employees', 'fingerprint_device_id');
    }
}
