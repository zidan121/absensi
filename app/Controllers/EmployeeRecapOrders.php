<?php

namespace App\Controllers;

class EmployeeRecapOrders extends BaseController
{
    private const UNITS = [
        'KBIT' => 'KBIT',
        'RAIT' => 'RAIT',
        'SDIT' => 'SDIT',
        'SMPIT' => 'SMPIT',
        'YAYASAN' => 'Yayasan',
        'LAZIS' => 'Lazis',
        'KBIT_LAZIS' => 'Gabungan KBIT + Lazis',
    ];

    public function index()
    {
        $unit = strtoupper(trim((string) $this->request->getGet('unit')));
        if (! isset(self::UNITS[$unit])) {
            $unit = 'KBIT';
        }

        $db = db_connect();
        $employees = $db->table('employees')
            ->select('id, employee_code, full_name, position, unit_code')
            ->whereIn('unit_code', $unit === 'KBIT_LAZIS' ? ['KBIT', 'LAZIS'] : [$unit])
            ->orderBy('full_name', 'ASC')
            ->get()->getResultArray();

        $savedRows = $db->table('employee_recap_orders')
            ->where('unit_code', $unit)
            ->orderBy('sort_order', 'ASC')
            ->get()->getResultArray();
        $saved = [];
        foreach ($savedRows as $row) {
            $saved[(int) $row['employee_id']] = $row;
        }

        $positionRows = $db->table('recap_position_orders')
            ->orderBy('sort_order', 'ASC')
            ->get()->getResultArray();
        $positionRank = [];
        foreach ($positionRows as $index => $row) {
            $positionRank[mb_strtolower(trim($row['position_name']))] = $index;
        }

        $defaultCombined = [
            'ari wuryaningsih' => ['Ari Wuryaningsih, SE', 1],
            'yuli karina' => ['Yuli Karina, S.Pd', 2],
            'winarti' => ['Winarti, S.Pd', 3],
            'sri munarsih' => ['Sri Munarsih, S.Sos', 4],
            'nobela' => ['Nobela Windatul Umah, S.Pd', 5],
            'nikmatul janah' => ['Nikmatul Jannah', 6],
            'nikmatul jannah' => ['Nikmatul Jannah', 6],
            'yuli tri w se' => ['Yuli Tri W SE', 7],
        ];
        $defaultRait = [
            'tri m spd' => ['Tri Mulyaningsih, SPd', 1],
            'yuni w a' => ['Yuni Wulansari, AMa', 2],
            'fitri dwi a' => ['Fitri Dwi Aryani, S.Pd', 3],
            'sulastri' => ['Sulastri, S.Pd', 4],
            'setiati spd' => ['Setiyati, SPdl', 5],
            'musripah spd' => ['Musripah, S.Pd', 6],
            'sri wijiasih spd' => ['Sri Wiji Asih, S.Pd', 7],
            'anista dw' => ['Anista Diyah F, S.Psi', 8],
            'karlina' => ['Karlina, S.Pd', 9],
            'indah s' => ['Indah Setyaningrum, SPd', 10],
            'kiki ayu m' => ['Kiki Ayu M', 11],
            'faridhotun ulfa' => ['Faridhotun Ulfa', 12],
            'darwanto' => ['Darwanto', 13],
            'ariana' => ['Ariana Irawati, S.Pd', 14],
            'deska sofiana' => ['Deska Sofiana', 15],
        ];
        $defaultSmp = [
            'fitri anggraeni' => ['Fitri Anggraeni, SPd', 1],
            'ruchayati r sd' => ['Ruchayati R Sd', 2],
            'ahmad tukul' => ['Ahmad Tukul SPd', 3],
            'ari wahono' => ['Ari Wahono SPd', 4],
            'endang r' => ['Endang R SPd', 5],
            'eko s shum' => ['Eko S Shum', 6],
            'tri widagdo' => ['Tri Widagdo Spd', 7],
            'nita l' => ['Nita L Spd', 8],
            'fathienahas' => ['FathienahAS SPd', 9],
            'fathienah as' => ['FathienahAS SPd', 9],
            'zainul arifin' => ['Zainul Arifin', 10],
            'zainal a' => ['Zainul Arifin', 10],
            'mujiono' => ['Mujiono', 11],
            'mujion' => ['Mujiono', 11],
            "imam safi'i" => ["Imam Safi'i", 12],
            'imam safi' => ["Imam Safi'i", 12],
            'imam safii' => ["Imam Safi'i", 12],
            'nunung megawati' => ['Nunung Megawati', 13],
            'm solikin' => ['M solikin', 14],
            'm soliki' => ['M solikin', 14],
            'nur indah nila khoiriyah' => ['Nur Indah Nila Khoiriyah', 15],
            'bu indah' => ['Nur Indah Nila Khoiriyah', 15],
            'laila yurin nabila' => ['Laila Yurin Nabila', 16],
            'laela' => ['Laila Yurin Nabila', 16],
        ];
        $defaultForUnit = $unit === 'KBIT_LAZIS' ? $defaultCombined
            : ($unit === 'RAIT' ? $defaultRait : ($unit === 'SMPIT' ? $defaultSmp : []));
        if ($defaultForUnit !== []) {
            foreach ($employees as &$employee) {
                $normalizedName = mb_strtolower(trim((string) $employee['full_name']));
                foreach ($defaultForUnit as $needle => [$displayName, $rank]) {
                    if (str_contains($normalizedName, $needle)) {
                        $employee['_default_rank'] = $rank;
                        $employee['_default_display_name'] = $displayName;
                        break;
                    }
                }
            }
            unset($employee);
        }

        // Terapkan preset SMPIT tepat satu kali. Marker mencegah pengaturan manual
        // pengguna tertimpa kembali pada kunjungan halaman berikutnya.
        if ($unit === 'SMPIT') {
            $marker = $db->table('employee_recap_orders')
                ->where('unit_code', 'SMPIT')
                ->where('employee_id', 0)
                ->where('display_name', '__PRESET_SMPIT_V1__')
                ->countAllResults() > 0;

            if (! $marker) {
                $now = date('Y-m-d H:i:s');
                foreach ($employees as $employee) {
                    if (! isset($employee['_default_rank'])) continue;
                    $id = (int) $employee['id'];
                    $existing = $saved[$id] ?? null;
                    $row = [
                        'unit_code' => 'SMPIT',
                        'employee_id' => $id,
                        'sort_order' => (int) $employee['_default_rank'],
                        'display_name' => $employee['_default_display_name'],
                        'updated_at' => $now,
                    ];
                    if ($existing) {
                        $db->table('employee_recap_orders')
                            ->where('unit_code', 'SMPIT')
                            ->where('employee_id', $id)
                            ->update($row);
                    } else {
                        $row['created_at'] = $now;
                        $db->table('employee_recap_orders')->insert($row);
                    }
                    $saved[$id] = $row;
                }

                $db->table('employee_recap_orders')->insert([
                    'unit_code' => 'SMPIT',
                    'employee_id' => 0,
                    'sort_order' => 0,
                    'display_name' => '__PRESET_SMPIT_V1__',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        usort($employees, static function ($a, $b) use ($saved, $positionRank) {
            $idA = (int) $a['id'];
            $idB = (int) $b['id'];
            $hasA = isset($saved[$idA]);
            $hasB = isset($saved[$idB]);
            if ($hasA && $hasB && (int) $saved[$idA]['sort_order'] !== (int) $saved[$idB]['sort_order']) {
                return (int) $saved[$idA]['sort_order'] <=> (int) $saved[$idB]['sort_order'];
            }
            if ($hasA !== $hasB) return $hasA ? -1 : 1;
            $defaultA = $a['_default_rank'] ?? PHP_INT_MAX;
            $defaultB = $b['_default_rank'] ?? PHP_INT_MAX;
            if ($defaultA !== $defaultB) return $defaultA <=> $defaultB;
            $rankA = $positionRank[mb_strtolower(trim((string) ($a['position'] ?? '')))] ?? PHP_INT_MAX;
            $rankB = $positionRank[mb_strtolower(trim((string) ($b['position'] ?? '')))] ?? PHP_INT_MAX;
            if ($rankA !== $rankB) return $rankA <=> $rankB;
            $positionCompare = strcasecmp((string) ($a['position'] ?? ''), (string) ($b['position'] ?? ''));
            return $positionCompare !== 0 ? $positionCompare : strcasecmp((string) $a['full_name'], (string) $b['full_name']);
        });

        foreach ($employees as &$employee) {
            $savedRow = $saved[(int) $employee['id']] ?? [];
            $defaultDisplayName = $employee['_default_display_name'] ?? null;
            $savedDisplayName = $savedRow['display_name'] ?? null;
            $employee['recap_display_name'] = ($defaultDisplayName !== null
                && ($savedDisplayName === null || $savedDisplayName === $employee['full_name']))
                    ? $defaultDisplayName
                    : ($savedDisplayName ?? $employee['full_name']);
            unset($employee['_default_rank'], $employee['_default_display_name']);
        }
        unset($employee);

        return view('employee_recap_orders/index', [
            'title' => 'Urutan Pegawai REKAB',
            'units' => self::UNITS,
            'selectedUnit' => $unit,
            'employees' => $employees,
        ]);
    }

    public function save()
    {
        $unit = strtoupper(trim((string) $this->request->getPost('unit_code')));
        if (! isset(self::UNITS[$unit])) {
            return redirect()->back()->with('error', 'Unit tidak valid.');
        }

        $employeeIds = $this->request->getPost('employee_ids');
        $displayNames = $this->request->getPost('display_names');
        if (! is_array($employeeIds)) {
            $employeeIds = [];
        }
        if (! is_array($displayNames)) {
            $displayNames = [];
        }

        $db = db_connect();
        $actualIds = array_map('intval', array_column(
            $db->table('employees')->select('id')->whereIn('unit_code', $unit === 'KBIT_LAZIS' ? ['KBIT', 'LAZIS'] : [$unit])->get()->getResultArray(),
            'id'
        ));
        $orderedIds = [];
        foreach ($employeeIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0 && in_array($id, $actualIds, true) && ! in_array($id, $orderedIds, true)) {
                $orderedIds[] = $id;
            }
        }
        if (count($orderedIds) !== count($actualIds)) {
            return redirect()->to('/pengaturan-urutan-pegawai?unit=' . urlencode($unit))
                ->with('error', 'Daftar pegawai berubah atau tidak lengkap. Muat ulang halaman sebelum menyimpan.');
        }

        foreach ($orderedIds as $id) {
            $name = trim((string) ($displayNames[$id] ?? ''));
            if ($name === '' || mb_strlen($name) > 150) {
                return redirect()->to('/pengaturan-urutan-pegawai?unit=' . urlencode($unit))
                    ->with('error', 'Nama REKAB wajib diisi dan maksimal 150 karakter.');
            }
        }

        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('employee_recap_orders')->where('unit_code', $unit)->delete();
        foreach ($orderedIds as $index => $id) {
            $db->table('employee_recap_orders')->insert([
                'unit_code' => $unit,
                'employee_id' => $id,
                'sort_order' => $index + 1,
                'display_name' => trim((string) $displayNames[$id]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to('/pengaturan-urutan-pegawai?unit=' . urlencode($unit))
                ->with('error', 'Pengaturan urutan pegawai gagal disimpan.');
        }

        return redirect()->to('/pengaturan-urutan-pegawai?unit=' . urlencode($unit))
            ->with('success', 'Urutan dan nama REKAB pegawai berhasil disimpan.');
    }
}
