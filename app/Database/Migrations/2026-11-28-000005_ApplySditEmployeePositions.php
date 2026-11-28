<?php

namespace App\\Database\\Migrations;

use CodeIgniter\\Database\\Migration;

class ApplySditEmployeePositions extends Migration
{
    public function up()
    {
        // Jabatan mengikuti kolom jabatan pada daftar urutan pegawai SDIT.
        $positions = [
            '44' => 'PUSTAKAWAN',
            '4'  => 'GURU/WAKA SISWA',
            '3'  => 'GURU',
            '5'  => 'GURU/KOORD QIROAT',
            '7'  => 'GURU',
            '8'  => 'GURU',
            '10' => 'GURU',
            '13' => 'GURU',
            '12' => 'TU',
            '6'  => 'GURU',
            '9'  => 'GURU',
            '15' => 'GURU',
            '16' => 'GURU',
            '17' => 'GURU',
            '19' => 'GURU',
            '18' => 'GURU',
            '21' => 'GURU',
            '22' => 'GURU',
            '23' => 'GURU',
            '24' => 'GURU',
            '26' => 'GURU',
            '31' => 'GURU',
            '33' => 'GURU',
            '32' => 'GURU',
            '34' => 'GURU',
            '35' => 'GURU',
            '37' => 'GURU',
            '38' => null,
            '41' => null,
            '39' => null,
            '47' => 'GURU',
            '48' => 'GURU',
            '50' => 'GURU',
            '51' => 'GURU',
            '52' => 'GURU',
            '54' => 'GURU',
            '55' => null,
            '56' => 'GURU',
            '59' => 'GURU',
            '60' => 'GURU',
            '62' => 'GURU',
            '63' => 'GURU',
            '57' => 'KOPERASI',
            '58' => 'KOPERASI',
        ];

        foreach ($positions as $pin => $position) {
            $this->db->table('sdit_employees')
                ->where('pin', $pin)
                ->update([
                    'position' => $position,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }

    public function down()
    {
        // Tidak mengosongkan jabatan saat rollback agar data pegawai tidak hilang.
    }
}
