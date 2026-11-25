<?php

namespace App\Controllers;

use App\Models\EmployeeFingerprintMappingModel;

class EmployeeFingerprintMappings extends BaseController
{
    public function index($employeeId)
    {
        $db = db_connect();
        $employee = $db->table('employees')->where('id', (int) $employeeId)->get()->getRowArray();
        if (!$employee) return redirect()->to('/pegawai')->with('error', 'Pegawai tidak ditemukan.');

        $devices = $db->table('devices')->orderBy('device_name', 'ASC')->get()->getResultArray();
        $mappings = $db->table('employee_fingerprint_mappings m')
            ->select('m.*, d.device_code, d.device_name')
            ->join('devices d', 'd.id = m.device_id')
            ->where('m.employee_id', (int) $employeeId)
            ->orderBy('d.device_name', 'ASC')->get()->getResultArray();

        return view('employees/fingerprint_mappings', [
            'title' => 'Pemetaan Fingerprint',
            'employee' => $employee,
            'devices' => $devices,
            'mappings' => $mappings,
        ]);
    }

    public function save($employeeId)
    {
        $employeeId = (int) $employeeId;
        $db = db_connect();
        $employee = $db->table('employees')->where('id', $employeeId)->get()->getRowArray();
        if (!$employee) return redirect()->to('/pegawai')->with('error', 'Pegawai tidak ditemukan.');

        $deviceId = (int) $this->request->getPost('device_id');
        $pin = trim((string) $this->request->getPost('fingerprint_pin'));
        $targetIdRaw = trim((string) $this->request->getPost('target_pegawai_id'));
        $effectiveFrom = trim((string) $this->request->getPost('effective_from'));
        $notes = trim((string) $this->request->getPost('notes'));

        if ($deviceId < 1 || $pin === '' || !ctype_digit($pin) || strlen($pin) > 32) {
            return redirect()->back()->withInput()->with('error', 'Pilih perangkat dan isi PIN angka maksimal 32 digit.');
        }
        if ($effectiveFrom !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveFrom) || date('Y-m-d', strtotime($effectiveFrom)) !== $effectiveFrom)) {
            return redirect()->back()->withInput()->with('error', 'Tanggal mulai pemetaan tidak valid.');
        }
        $device = $db->table('devices')->where('id', $deviceId)->get()->getRowArray();
        if (!$device) return redirect()->back()->with('error', 'Perangkat tidak ditemukan.');
        $targetId = $targetIdRaw === '' ? null : (int) $targetIdRaw;

        $db->transBegin();
        try {
            $existing = $db->table('employee_fingerprint_mappings')
                ->where('employee_id', $employeeId)->where('device_id', $deviceId)->get()->getRowArray();
            $conflict = $db->table('employee_fingerprint_mappings')
                ->where('device_id', $deviceId)->where('fingerprint_pin', $pin)
                ->where('employee_id !=', $employeeId)->get()->getRowArray();
            if ($conflict) throw new \RuntimeException('PIN ini sudah dipakai pegawai lain pada perangkat yang sama.');

            if ($existing && (string) $existing['fingerprint_pin'] !== $pin) {
                $db->table('employee_fingerprint_mapping_history')->insert([
                    'employee_id' => $employeeId, 'device_id' => $deviceId,
                    'fingerprint_pin' => $existing['fingerprint_pin'],
                    'target_pegawai_id' => $existing['target_pegawai_id'],
                    'action' => 'replaced', 'effective_from' => $existing['effective_from'],
                    'effective_until' => date('Y-m-d'), 'notes' => 'PIN diganti. ' . $notes,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $payload = [
                'fingerprint_pin' => $pin,
                'target_pegawai_id' => $targetId,
                'effective_from' => $effectiveFrom !== '' ? $effectiveFrom : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($existing) {
                $db->table('employee_fingerprint_mappings')->where('id', $existing['id'])->update($payload);
                $action = (string) $existing['fingerprint_pin'] === $pin ? 'updated' : 'replaced';
            } else {
                $payload += ['employee_id' => $employeeId, 'device_id' => $deviceId, 'created_at' => date('Y-m-d H:i:s')];
                $db->table('employee_fingerprint_mappings')->insert($payload);
                $action = 'assigned';
            }
            $db->table('employee_fingerprint_mapping_history')->insert([
                'employee_id' => $employeeId, 'device_id' => $deviceId,
                'fingerprint_pin' => $pin, 'target_pegawai_id' => $targetId,
                'action' => $action, 'effective_from' => $effectiveFrom !== '' ? $effectiveFrom : null,
                'notes' => $notes !== '' ? $notes : null, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($db->transStatus() === false) throw new \RuntimeException('Gagal menyimpan pemetaan.');
            $db->transCommit();
            return redirect()->to('/pegawai/' . $employeeId . '/perangkat')->with('success', 'Pemetaan fingerprint berhasil disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
