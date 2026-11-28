<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ApplySditOfficialEmployeeOrder extends Migration
{
    private array $employees = [
        '14' => ['Arga Akthobaron SPd', 'Arga Akthobaron, SPd', 1],
        '44' => ['Ida Gayatri', 'Gayatri Ida Susanti, SPd', 2],
        '4' => ['Hendra Dwi  S SPd', 'Hendra Dwi S, SPd', 3],
        '3' => ['Nurul H SPd', 'Nurul H, SPd', 4],
        '5' => ['Sutarti SPd', 'Sutarti, SPd', 5],
        '7' => ['Tri Khurniawati SPd', 'Tri Khurniawati, SPd', 6],
        '8' => ['Jatmi Puji Astuti SPdI', 'Jatmi Puji Astuti, SPdI', 7],
        '10' => ['Eko Nur Sayekti SPd', 'Eko Nur Sayekti, SPd', 8],
        '13' => ['Istiqomah SPdI', 'Istiqomah, SPdI', 9],
        '12' => ['Widiyati S SKom', 'Widyati Setyaningsih, S.Kom', 10],
        '6' => ['Hadi Sasmito', 'Hadi Sasmito', 11],
        '9' => ['Ahmad Rofii', "Ahmad Rofi'I, S.Pd.I", 12],
        '15' => ['Khoirunnisa SPd', 'Khoirun Nisa, SPd', 13],
        '16' => ['Dita Elvy Januriska SPd', 'Dita Elvi Januriska, SPd', 14],
        '17' => ['Tri Kusumawati SPd', 'Tri Kusumawati, SPd', 15],
        '19' => ['Ahmad Thoifin SPd', 'Ahmad Thoifin, SPd', 16],
        '18' => ['Retno Wulandari SPd', 'Retno Wulandari, SPd', 17],
        '21' => ['Mustarom SPdI', 'Mustarom, SPdI', 18],
        '22' => ['Edi Saputro SPd', 'Edi Saputro, SPd', 19],
        '23' => ['Laila Agustina SPdI', 'Laela Agustina, SPdI', 20],
        '24' => ['Aprilia Wahyu Puspa Dewi SPd', 'Apriliana Wahyu Puspa Dewi, S.Pd', 21],
        '26' => ['Uswatun Khasanah SPdI', 'Uswatun Khasanah, S.Pd.I', 22],
        '31' => ['Mika Setya Anggraini SPd', 'Mika Setya Anggraini, S.Pd', 23],
        '33' => ['Yuliana SPd', 'Yuliana, S.Pd', 24],
        '32' => ['Retno Arrayyan SPd', 'Retno Arrayan, S.Pd', 25],
        '34' => ['Nurkholis SPd', 'Nur Kholis, AH, S.Pd.I', 26],
        '35' => ['Evy Rukhayati SPdI', 'Evy Rukhayati, S.Pd', 27],
        '37' => ['Siti Ningrahayu SPd', 'Siti Ningrahayu, S.Pd', 28],
        '38' => ['Muhammad Nursolikin', 'Muhammad Nursolikin', 29],
        '41' => ['Purjiyem', 'Purjiyem', 30],
        '39' => ['Kuswanto', 'Kuswanto', 31],
        '47' => ['Rifatun Nisak', 'Rifatun Nisak', 32],
        '48' => ['Fitria Zahrul F', 'Fitria Zahrul Fata', 33],
        '50' => ['Fida Ainul Akhv', 'Fida Ainul Akhvia', 34],
        '51' => ['Nurhayati', 'Nurhayati', 35],
        '52' => ['Muniro', 'Muniro', 36],
        '54' => ['Ulya Sekar', 'Ulya Sekar Arum D.', 37],
        '55' => ['Maryana', 'Maryana', 38],
        '56' => ['Lisa Istivani', 'Lisa Istifani Fachidah', 39],
        '59' => ['Sri Pujiyanti', 'Sri Pujiyanti', 40],
        '60' => ['Ika Setiawati', 'Ika Setiawati', 41],
        '62' => ['Nabila', 'Aisyah Nabilah Azhaar', 42],
        '63' => ['Nadia M', 'Nadia Milatina Aqilah', 43],
        '57' => ['Sri Wahyuni', 'Sri Wahyuni', 44],
        '58' => ['Mahmudah', 'Mahmudah', 45],
        '61' => ['Rhea Fitri', 'Rhea Fitri Gouvani', 46],
    ];

    public function up()
    {
        if (!$this->db->fieldExists('display_order', 'sdit_employees')) {
            $this->forge->addColumn('sdit_employees', [
                'display_order' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'fingerprint_name',
                ],
            ]);
        }

        foreach ($this->employees as $pin => [$fingerprintName, $fullName, $order]) {
            $this->db->table('sdit_employees')->where('pin', $pin)->update([
                'full_name' => $fullName,
                'fingerprint_name' => $fingerprintName,
                'display_order' => $order,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('display_order', 'sdit_employees')) {
            $this->forge->dropColumn('sdit_employees', 'display_order');
        }
    }
}
