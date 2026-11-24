<?php

namespace App\Controllers;

use DateTimeImmutable;

class AttendanceSettings extends BaseController
{
    private const UNITS = ['KBIT', 'RAIT', 'SDIT', 'SMPIT', 'YAYASAN'];

    public function index()
    {
        $rules = db_connect()->table('unit_attendance_rules')
            ->orderBy('unit_code', 'ASC')
            ->orderBy('effective_from', 'DESC')
            ->get()->getResultArray();

        $latest = [];
        foreach ($rules as $rule) {
            if (! isset($latest[$rule['unit_code']])) {
                $latest[$rule['unit_code']] = $rule;
            }
        }

        return view('attendance_settings/index', [
            'title' => 'Pengaturan Absensi',
            'units' => self::UNITS,
            'rules' => $rules,
            'latest' => $latest,
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

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveFrom);
        if ($date === false || $date->format('Y-m-d') !== $effectiveFrom) {
            return redirect()->back()->with('error', 'Tanggal mulai berlaku tidak valid.');
        }
        if ($effectiveFrom <= date('Y-m-d')) {
            return redirect()->back()->with('error', 'Aturan baru hanya boleh mulai berlaku besok atau setelahnya agar rekap historis tidak berubah.');
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
                    'Tanggal berlaku harus setelah aturan terakhir (' . $latest['effective_from'] . '). Untuk menjaga riwayat lama, aturan mundur tidak dapat ditambahkan dari halaman ini.'
                );
            }

            if ($latest && empty($latest['effective_to'])) {
                $previousDay = (new DateTimeImmutable($effectiveFrom))->modify('-1 day')->format('Y-m-d');
                $db->table('unit_attendance_rules')
                    ->where('id', $latest['id'])
                    ->update(['effective_to' => $previousDay]);
            }

            $db->table('unit_attendance_rules')->insert([
                'unit_code' => $unit,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'late_after' => $lateAfter,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database menolak penyimpanan aturan.');
            }

            $db->transCommit();
            return redirect()->to('/pengaturan-absensi')->with('success', 'Aturan keterlambatan ' . $unit . ' berhasil disimpan dan riwayat sebelumnya tetap dipertahankan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
