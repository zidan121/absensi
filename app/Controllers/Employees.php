<?php

namespace App\Controllers;

use App\Models\EmployeeModel;

class Employees extends BaseController
{
    private const UNITS = [
        'KBIT' => 'KBIT',
        'RAIT' => 'RAIT',
        'SDIT' => 'SDIT',
        'SMPIT' => 'SMPIT',
        'YAYASAN' => 'Yayasan',
        'RAIT_KBIT' => 'RAIT + KBIT (belum dipisah)',
    ];

    /**
     * Allocate the next numeric fingerprint PIN globally, so one employee
     * identity is not assigned different PINs on different device records.
     */
    private function nextFingerprintPin($db, ?int $deviceId = null): string
    {
        $pins = [];
        $mappingQuery = $db->table('employee_fingerprint_mappings')->select('fingerprint_pin');
        if ($deviceId !== null) {
            $mappingQuery->where('device_id', $deviceId);
        }
        foreach ($mappingQuery->get()->getResultArray() as $row) {
            $pins[] = trim((string) ($row['fingerprint_pin'] ?? ''));
        }

        // Include legacy values during the transition from the old columns.
        $legacyQuery = $db->table('employees')->select('fingerprint_pin');
        if ($deviceId !== null) {
            $legacyQuery->where('fingerprint_device_id', $deviceId);
        }
        foreach ($legacyQuery->get()->getResultArray() as $row) {
            $pins[] = trim((string) ($row['fingerprint_pin'] ?? ''));
        }

        $max = 0;
        foreach ($pins as $pin) {
            if ($pin !== '' && ctype_digit($pin)) {
                $max = max($max, (int) $pin);
            }
        }
        return (string) ($max + 1);
    }

    public function index()
    {
        $model = new EmployeeModel();
        $selectedUnit = strtoupper(trim((string) $this->request->getGet('unit')));
        if ($selectedUnit !== 'UNASSIGNED' && ! array_key_exists($selectedUnit, self::UNITS)) {
            $selectedUnit = 'ALL';
        }

        $counts = ['ALL' => $model->countAllResults(false)];
        foreach (self::UNITS as $code => $label) {
            $counts[$code] = $model->where('unit_code', $code)->countAllResults();
        }
        $counts['UNASSIGNED'] = $model->groupStart()
            ->where('unit_code', null)
            ->orWhere('unit_code', '')
            ->groupEnd()
            ->countAllResults();

        $query = new EmployeeModel();
        if ($selectedUnit === 'UNASSIGNED') {
            $query->groupStart()->where('unit_code', null)->orWhere('unit_code', '')->groupEnd();
        } elseif ($selectedUnit !== 'ALL') {
            $query->where('unit_code', $selectedUnit);
        }

        $employees = $query->findAll();
        // Sort by the numeric part of the employee code, ignoring its unit/prefix (e.g. RAIT:2 before SMPIT:5).
        usort($employees, static function (array $a, array $b): int {
            $numberA = preg_match('/(\d+)$/', (string) ($a['employee_code'] ?? ''), $matchA)
                ? (int) $matchA[1]
                : PHP_INT_MAX;
            $numberB = preg_match('/(\d+)$/', (string) ($b['employee_code'] ?? ''), $matchB)
                ? (int) $matchB[1]
                : PHP_INT_MAX;

            if ($numberA !== $numberB) {
                return $numberA <=> $numberB;
            }

            $codeCompare = strnatcasecmp(
                (string) ($a['employee_code'] ?? ''),
                (string) ($b['employee_code'] ?? '')
            );

            return $codeCompare !== 0
                ? $codeCompare
                : strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
        });

        return view('employees/index', [
            'title' => 'Data Pegawai',
            'employees' => $employees,
            'units' => self::UNITS,
            'selectedUnit' => $selectedUnit,
            'counts' => $counts,
        ]);
    }

    public function create()
    {
        return view('employees/create', ['title' => 'Tambah Pegawai', 'units' => self::UNITS]);
    }

    public function store()
    {
        $rules = [
            'employee_code' => 'required|max_length[40]',
            'full_name' => 'required|max_length[150]',
            'unit_code' => 'required|in_list[KBIT,RAIT,SDIT,SMPIT,YAYASAN]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $unitCode = (string) $this->request->getPost('unit_code');
        $deviceCode = match ($unitCode) {
            'SMPIT' => 'SMP-REVO16B',
            'RAIT', 'KBIT' => 'RAKB-REVO185BNC',
            default => null,
        };

        $db->transBegin();
        try {
            $device = $deviceCode
                ? $db->table('devices')->where('device_code', $deviceCode)->where('is_active', 1)->get()->getRowArray()
                : null;
            $pin = $this->nextFingerprintPin($db, $device ? (int) $device['id'] : null);

            $model = new EmployeeModel();
            $employeeId = $model->insert([
                'employee_code' => trim($this->request->getPost('employee_code')),
                'fingerprint_pin' => $pin,
                'fingerprint_device_id' => $device ? (int) $device['id'] : null,
                'unit_code' => $unitCode,
                'full_name' => trim($this->request->getPost('full_name')),
                'position' => trim((string) $this->request->getPost('position')),
                'is_active' => 1,
            ], true);

            if (! $employeeId || $db->transStatus() === false) {
                throw new \RuntimeException('Data pegawai gagal disimpan. Periksa apakah kode pegawai sudah digunakan.');
            }

            if ($device) {
                $db->table('employee_fingerprint_mappings')->insert([
                    'employee_id' => (int) $employeeId,
                    'device_id' => (int) $device['id'],
                    'fingerprint_pin' => $pin,
                    'effective_from' => date('Y-m-d'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $db->table('employee_fingerprint_mapping_history')->insert([
                    'employee_id' => (int) $employeeId,
                    'device_id' => (int) $device['id'],
                    'fingerprint_pin' => $pin,
                    'action' => 'assigned',
                    'effective_from' => date('Y-m-d'),
                    'notes' => 'Pemetaan awal otomatis saat pegawai dibuat.',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $db->transCommit();

            $message = 'Pegawai berhasil ditambahkan. PIN fingerprint utama: ' . $pin . '.';
            if ($deviceCode && ! $device) {
                $message .= ' Perangkat ' . $deviceCode . ' belum terdaftar/aktif, sehingga perlu dipetakan sebelum sinkronisasi.';
            } elseif (! $deviceCode) {
                $message .= ' Unit ini belum memiliki pemetaan perangkat fingerprint; PIN tetap tersimpan dan perangkat dapat ditentukan kemudian.';
            }

            return redirect()->to('/pegawai/' . $employeeId . '/edit')
                ->with('success', $message);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Create employee failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Pegawai gagal ditambahkan: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $employee = (new EmployeeModel())->find((int) $id);
        if (! $employee) {
            return redirect()->to('/pegawai')->with('error', 'Data pegawai tidak ditemukan.');
        }

        return view('employees/edit', [
            'title' => 'Edit Data Pegawai',
            'employee' => $employee,
            'units' => self::UNITS,
            'returnUnit' => (string) ($this->request->getGet('unit') ?? 'ALL'),
        ]);
    }

    public function update($id)
    {
        $model = new EmployeeModel();
        $employee = $model->find((int) $id);
        if (! $employee) {
            return redirect()->to('/pegawai')->with('error', 'Data pegawai tidak ditemukan.');
        }

        $employeeCode = trim((string) $this->request->getPost('employee_code'));
        $fullName = trim((string) $this->request->getPost('full_name'));
        $unitCode = strtoupper(trim((string) $this->request->getPost('unit_code')));
        $allowedUnits = array_keys(self::UNITS);

        if ($employeeCode === '' || strlen($employeeCode) > 40 || $fullName === '' || strlen($fullName) > 150 || ! in_array($unitCode, $allowedUnits, true)) {
            return redirect()->back()->withInput()->with('error', 'Kode, nama, atau unit pegawai tidak valid.');
        }

        $duplicate = $model->where('employee_code', $employeeCode)->where('id !=', (int) $id)->first();
        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Kode pegawai sudah digunakan oleh pegawai lain.');
        }

        $start = trim((string) $this->request->getPost('employment_start'));
        $end = trim((string) $this->request->getPost('employment_end'));
        foreach (['Tanggal mulai kerja' => $start, 'Tanggal berhenti' => $end] as $label => $date) {
            if ($date !== '' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date) || date('Y-m-d', strtotime($date)) !== $date)) {
                return redirect()->back()->withInput()->with('error', $label . ' tidak valid.');
            }
        }
        if ($start !== '' && $end !== '' && $end < $start) {
            return redirect()->back()->withInput()->with('error', 'Tanggal berhenti tidak boleh sebelum tanggal mulai kerja.');
        }

        // Identitas perangkat/PIN dikelola terpisah agar perubahan unit tidak
        // diam-diam memindahkan pegawai atau mengganti PIN di mesin fingerprint.
        $model->update((int) $id, [
            'employee_code' => $employeeCode,
            'full_name' => $fullName,
            'unit_code' => $unitCode,
            'position' => trim((string) $this->request->getPost('position')),
            'is_active' => $this->request->getPost('is_active') === '1' ? 1 : 0,
            'employment_start' => $start !== '' ? $start : null,
            'employment_end' => $end !== '' ? $end : null,
        ]);

        $returnUnit = strtoupper(trim((string) $this->request->getPost('return_unit')));
        $redirectUnit = $returnUnit === 'ALL' || $returnUnit === 'UNASSIGNED' || in_array($returnUnit, $allowedUnits, true) ? $returnUnit : $unitCode;
        $url = $redirectUnit === 'ALL' ? '/pegawai' : '/pegawai?unit=' . urlencode($redirectUnit);

        return redirect()->to($url)->with('success', 'Data pegawai berhasil diperbarui.');
    }
}
