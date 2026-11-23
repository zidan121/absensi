<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFingerprintMasterFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('employees', [
            'fingerprint_pin' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true, 'after' => 'employee_code'],
            'unit_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'fingerprint_pin'],
            'source_employee_id' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true, 'after' => 'unit_code'],
            'source_division_1' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'source_division_2' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'source_division_3' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'employment_start' => ['type' => 'DATE', 'null' => true],
            'employment_end' => ['type' => 'DATE', 'null' => true],
        ]);
        $this->forge->addKey(['unit_code', 'fingerprint_pin'], false, true, 'uq_employee_unit_pin');
    }

    public function down()
    {
        $this->forge->dropColumn('employees', [
            'fingerprint_pin', 'unit_code', 'source_employee_id',
            'source_division_1', 'source_division_2', 'source_division_3',
            'employment_start', 'employment_end',
        ]);
    }
}
