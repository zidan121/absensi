<?php

namespace App\Controllers;

use DateTimeImmutable;

class AttendanceSettings extends BaseController
{
    private const UNITS = ['KBIT', 'RAIT', 'SDIT', 'SMPIT', 'YAYASAN'];

    public function index()
    {
        $db = db_connect();
        $rules = $db->table('unit_attendance_rules')
            ->orderBy('unit_code', 'ASC')
            ->orderBy('effective_from', 'DESC')
            ->get()->getResultArray();

        $latest = [];
        foreach ($rules as $rule) {
            if (! isset($latest[$rule['unit_code']])) {
                $latest[$rule['unit_code']] = $rule;
            }
        }

        $audits = $db->table('unit_attendance_rule_audits')
            ->orderBy('changed_at', 'DESC')
            ->get(50)->getResultArray();

        return view('attendance_settings/index', [
            'title' => 'Pengaturan Absensi',
            'units' => self::UNITS,
            'rules' => $rules,
            'latest' => $latest,
            'audits' => $audits,
        ]);
    }

    public function saveUnitRule()
    {
        $unit = strtoupper(trim((string) $this->request->getPost('unit_code')));
        $effectiveFrom = (string) $this->request->getPost('effective_from');
        $lateAfter = (string) $this->request->getPost('late_after');

        if (! in_array($unit, self::UNITS, true)) {
            return redirect()->back()->with('error', 'Unit tidak valid.');
        }
        if (! $this->validDate($effectiveFrom)) {
            return redirect()->back()->with('error', 'Tanggal mulai berlaku tidak valid.');
        }
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $lateAfter)) {
            return redirect()->back()->with('error', 'Format batas keterlambatan harus HH:MM:SS.');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $latest = $db->table('unit_attendance_rules')
                ->where('unit_code', $unit)
                ->orderBy('effective_from', 'DESC')
                ->get(1)->getRowArray();

            if ($latest && $effectiveFrom <= $latest['effective_from']) {
                throw new \RuntimeException(
                    'Tanggal mulai aturan baru harus setelah aturan terakhir. Untuk memperbaiki aturan lama, gunakan tombol Edit pada riwayat.'
                );
            }

            if ($latest && empty($latest['effective_to'])) {
                $previousDay = (new DateTimeImmutable($effectiveFrom))->modify('-1 day')->format('Y-m-d');
                $beforeLatest = $latest;
                $afterLatest = $latest;
                $afterLatest['effective_to'] = $previousDay;
                $db->table('unit_attendance_rules')
                    ->where('id', $latest['id'])
                    ->update(['effective_to' => $previousDay, 'updated_at' => date('Y-m-d H:i:s')]);
                $this->writeAudit($db, (int) $latest['id'], $unit, 'close_period', $beforeLatest, $afterLatest);
            }

            $db->table('unit_attendance_rules')->insert([
                'unit_code' => $unit,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'late_after' => $lateAfter,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $newId = (int) $db->insertID();
            $this->writeAudit($db, $newId, $unit, 'create', null, [
                'unit_code' => $unit,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'late_after' => $lateAfter,
            ]);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database menolak penyimpanan aturan.');
            }
            $db->transCommit();
            return redirect()->to('/pengaturan-absensi')->with('success', 'Aturan keterlambatan berhasil disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function updateRule(int $id)
    {
        $db = db_connect();
        $rule = $db->table('unit_attendance_rules')->where('id', $id)->get()->getRowArray();
        if (! $rule) {
            return redirect()->to('/pengaturan-absensi')->with('error', 'Aturan tidak ditemukan.');
        }

        $effectiveFrom = (string) $this->request->getPost('effective_from');
        $effectiveToInput = trim((string) $this->request->getPost('effective_to'));
        $effectiveTo = $effectiveToInput === '' ? null : $effectiveToInput;
        $lateAfter = (string) $this->request->getPost('late_after');

        if (! $this->validDate($effectiveFrom) || ($effectiveTo !== null && ! $this->validDate($effectiveTo))) {
            return redirect()->back()->with('error', 'Tanggal aturan tidak valid.');
        }
        if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            return redirect()->back()->with('error', 'Tanggal berlaku sampai tidak boleh sebelum tanggal mulai.');
        }
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $lateAfter)) {
            return redirect()->back()->with('error', 'Format batas keterlambatan harus HH:MM:SS.');
        }

        $overlap = $db->table('unit_attendance_rules')
            ->where('unit_code', $rule['unit_code'])
            ->where('id !=', $id)
            ->where('effective_from <=', $effectiveTo ?? '9999-12-31')
            ->groupStart()
                ->where('effective_to >=', $effectiveFrom)
                ->orWhere('effective_to', null)
            ->groupEnd()
            ->countAllResults();

        if ($overlap > 0) {
            return redirect()->back()->with('error', 'Periode ini bertabrakan dengan aturan lain pada unit yang sama. Sesuaikan tanggalnya terlebih dahulu.');
        }

        $before = $rule;
        $after = [
            'unit_code' => $rule['unit_code'],
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'late_after' => $lateAfter,
        ];

        $db->transBegin();
        try {
            $db->table('unit_attendance_rules')->where('id', $id)->update($after + ['updated_at' => date('Y-m-d H:i:s')]);
            $this->writeAudit($db, $id, $rule['unit_code'], 'update', $before, $after);
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Perubahan aturan gagal disimpan.');
            }
            $db->transCommit();
            return redirect()->to('/pengaturan-absensi')->with('success', 'Aturan berhasil dikoreksi. Rekap periode terkait akan menggunakan aturan terbaru.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function deleteRule(int $id)
    {
        $db = db_connect();
        $rule = $db->table('unit_attendance_rules')->where('id', $id)->get()->getRowArray();
        if (! $rule) {
            return redirect()->to('/pengaturan-absensi')->with('error', 'Aturan tidak ditemukan.');
        }

        $remaining = $db->table('unit_attendance_rules')
            ->where('unit_code', $rule['unit_code'])
            ->where('id !=', $id)
            ->countAllResults();
        if ($remaining === 0) {
            return redirect()->to('/pengaturan-absensi')->with('error', 'Aturan terakhir untuk unit tidak boleh dihapus. Edit aturan tersebut jika ada kesalahan.');
        }

        $db->transBegin();
        try {
            $this->writeAudit($db, $id, $rule['unit_code'], 'delete', $rule, null);
            $db->table('unit_attendance_rules')->where('id', $id)->delete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Aturan gagal dihapus.');
            }
            $db->transCommit();
            return redirect()->to('/pengaturan-absensi')->with('success', 'Aturan dihapus dan perubahan dicatat dalam riwayat audit.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function writeAudit($db, int $ruleId, string $unit, string $action, ?array $before, ?array $after): void
    {
        $db->table('unit_attendance_rule_audits')->insert([
            'rule_id' => $ruleId,
            'unit_code' => $unit,
            'action' => $action,
            'before_data' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_data' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
            'changed_at' => date('Y-m-d H:i:s'),
            'changed_by' => (string) (session()->get('username') ?? 'admin'),
        ]);
    }
}
