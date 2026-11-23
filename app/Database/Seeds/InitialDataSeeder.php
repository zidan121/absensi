<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialDataSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;
        foreach ([
            ['device_code' => 'SMP-REVO16B', 'device_name' => 'Mesin Fingerprint SMPIT', 'unit_scope' => 'SMPIT', 'model_name' => 'Revo-16B', 'is_active' => 1],
            ['device_code' => 'RAKB-REVO185BNC', 'device_name' => 'Mesin Fingerprint RAIT & KBIT', 'unit_scope' => 'RAIT,KBIT', 'model_name' => 'Revo-185BNC', 'is_active' => 1],
        ] as $device) {
            if (! $db->table('devices')->where('device_code', $device['device_code'])->countAllResults()) {
                $db->table('devices')->insert($device);
            }
        }

        if (! $db->table('app_users')->where('username', 'admin')->countAllResults()) {
            $db->table('app_users')->insert([
                'username' => 'admin',
                'password_hash' => password_hash('GantiPassword123!', PASSWORD_DEFAULT),
                'full_name' => 'Administrator',
                'is_active' => 1,
            ]);
        }
    }
}