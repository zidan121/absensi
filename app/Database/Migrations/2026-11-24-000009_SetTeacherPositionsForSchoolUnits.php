<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SetTeacherPositionsForSchoolUnits extends Migration
{
    public function up()
    {
        // School-unit staff are teachers by default. Foundation and kitchen staff are left unchanged.
        $this->db->table('employees')
            ->whereIn('unit_code', ['KBIT', 'RAIT', 'SDIT', 'SMPIT', 'RAIT_KBIT'])
            ->update([
                'position' => 'Guru',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function down()
    {
        // Intentionally do not reverse employee position data; prior values cannot be reconstructed safely.
    }
}
