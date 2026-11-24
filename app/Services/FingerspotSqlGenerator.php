<?php

namespace App\Services;

class FingerspotSqlGenerator
{
    private const EMPLOYEE_COLUMNS = [
        'pegawai_id','pegawai_pin','pegawai_nip','pegawai_nama','pegawai_alias','pegawai_pwd',
        'pegawai_rfid','pegawai_privilege','pegawai_telp','pegawai_status','tempat_lahir',
        'tgl_lahir','pembagian1_id','pembagian2_id','pembagian3_id','tgl_mulai_kerja',
        'tgl_resign','gender','tgl_masuk_pertama','photo_path','tmp_img','nama_bank',
        'nama_rek','no_rek','new_pegawai_id',
    ];

    public function generate($db, array $device, string $sourceSql, string $label): array
    {
        $parser = new FingerspotSqlBackupParser();
        $source = $parser->parse($sourceSql, 'employees')['records'];
        if ($source === []) throw new \RuntimeException('Arsip sumber tidak memiliki data pegawai yang dapat dibaca.');
        $byPin = [];
        foreach ($source as $row) {
            $pin = trim((string) $row['fingerprint_pin']);
            if ($pin !== '') $byPin[$pin][] = $row;
        }

        $employees = $db->table('employees')->where('fingerprint_device_id', (int) $device['id'])->get()->getResultArray();
        $unitMaps = [];
        foreach ($employees as $employee) {
            $unit = (string) ($employee['unit_code'] ?? '');
            $map = [
                trim((string) ($employee['source_division_1'] ?? '')),
                trim((string) ($employee['source_division_2'] ?? '')),
                trim((string) ($employee['source_division_3'] ?? '')),
            ];
            if ($unit !== '' && !in_array('', $map, true)) $unitMaps[$unit][implode('|', $map)] = $map;
        }

        $pinCounts = [];
        foreach ($employees as $employee) {
            $candidatePin = trim((string) ($employee['fingerprint_pin'] ?? ''));
            if ($candidatePin !== '') $pinCounts[$candidatePin] = ($pinCounts[$candidatePin] ?? 0) + 1;
        }
        $report = [];
        $masterSql = [];
        $knownPins = [];
        $skippedEmployees = 0;
        foreach ($employees as $employee) {
            $pin = trim((string) ($employee['fingerprint_pin'] ?? ''));
            $name = trim((string) ($employee['full_name'] ?? ''));
            if ($pin === '' || $name === '') {
                $report[] = $this->issue($pin ?: '-', $name ?: '(tanpa nama)', 'PIN atau nama belum lengkap; pegawai dilewati.');
                $skippedEmployees++;
                continue;
            }
            if (($pinCounts[$pin] ?? 0) > 1 || isset($knownPins[$pin])) {
                $report[] = $this->issue($pin, $name, 'PIN ganda pada perangkat yang sama; semua baris dengan PIN ini harus diperiksa.');
                $skippedEmployees++;
                continue;
            }
            $matches = $byPin[$pin] ?? [];
            if (count($matches) > 1) {
                $report[] = $this->issue($pin, $name, 'PIN ganda di arsip Fingerspot; pegawai dilewati.');
                $skippedEmployees++;
                continue;
            }

            if ($matches) {
                $old = $matches[0];
                $set = [
                    'pegawai_nip' => $pin,
                    'pegawai_nama' => $name,
                    'pegawai_alias' => $name,
                    'pegawai_status' => (int) ($employee['is_active'] ?? 1) === 1 ? 1 : 0,
                    'tgl_mulai_kerja' => $employee['employment_start'] ?: null,
                    'tgl_resign' => $employee['employment_end'] ?: null,
                ];
                foreach (['pembagian1_id'=>'source_division_1','pembagian2_id'=>'source_division_2','pembagian3_id'=>'source_division_3'] as $column=>$field) {
                    $set[$column] = $old[$field];
                }
                $assignments = [];
                foreach ($set as $column=>$value) $assignments[] = $column . '=' . $this->value($db, $value);
                $masterSql[] = 'UPDATE pegawai SET ' . implode(',', $assignments)
                    . ' WHERE pegawai_pin=' . $this->value($db, $pin) . ';';
                $knownPins[$pin] = true;
                continue;
            }

            $unit = (string) ($employee['unit_code'] ?? '');
            $division = [
                trim((string) ($employee['source_division_1'] ?? '')),
                trim((string) ($employee['source_division_2'] ?? '')),
                trim((string) ($employee['source_division_3'] ?? '')),
            ];
            if (in_array('', $division, true)) {
                $candidates = array_values($unitMaps[$unit] ?? []);
                if (count($candidates) === 1) $division = $candidates[0];
                else {
                    $reason = count($candidates) === 0
                        ? 'Pemetaan pembagian1/2/3 belum lengkap dan tidak ada acuan unit.'
                        : 'Unit memiliki beberapa kemungkinan pembagian; perlu dipilih manual.';
                    $report[] = $this->issue($pin, $name, $reason);
                    $skippedEmployees++;
                    continue;
                }
            }

            $new = [
                'pegawai_id'=>null, 'pegawai_pin'=>$pin, 'pegawai_nip'=>$pin,
                'pegawai_nama'=>$name, 'pegawai_alias'=>$name, 'pegawai_pwd'=>'0', 'pegawai_rfid'=>'0',
                'pegawai_privilege'=>'0', 'pegawai_telp'=>null, 'pegawai_status'=>(int) ($employee['is_active'] ?? 1) === 1 ? 1 : 0,
                'tempat_lahir'=>null, 'tgl_lahir'=>null, 'pembagian1_id'=>$division[0],
                'pembagian2_id'=>$division[1], 'pembagian3_id'=>$division[2],
                'tgl_mulai_kerja'=>$employee['employment_start'] ?: null, 'tgl_resign'=>$employee['employment_end'] ?: null,
                'gender'=>1, 'tgl_masuk_pertama'=>$employee['employment_start'] ?: null, 'photo_path'=>'',
                'tmp_img'=>null, 'nama_bank'=>'', 'nama_rek'=>'', 'no_rek'=>'', 'new_pegawai_id'=>null,
            ];
            $newSet = [
                'pegawai_nip'=>$pin, 'pegawai_nama'=>$name, 'pegawai_alias'=>$name,
                'pegawai_status'=>(int) ($employee['is_active'] ?? 1) === 1 ? 1 : 0,
                'pembagian1_id'=>$division[0], 'pembagian2_id'=>$division[1], 'pembagian3_id'=>$division[2],
                'tgl_mulai_kerja'=>$employee['employment_start'] ?: null, 'tgl_resign'=>$employee['employment_end'] ?: null,
            ];
            $newAssignments = [];
            foreach ($newSet as $column=>$value) $newAssignments[] = $column . '=' . $this->value($db, $value);
            $masterSql[] = 'UPDATE pegawai SET ' . implode(',', $newAssignments)
                . ' WHERE pegawai_pin=' . $this->value($db, $pin) . ';';
            $insertColumns = implode(',', self::EMPLOYEE_COLUMNS);
            $insertValues = '(@neuro_next_pegawai_id := @neuro_next_pegawai_id + 1),' 
                . implode(',', array_map(fn($col) => $this->value($db, $new[$col]), array_slice(self::EMPLOYEE_COLUMNS, 1)));
            $masterSql[] = 'INSERT INTO pegawai (' . $insertColumns . ') SELECT ' . $insertValues
                . ' WHERE NOT EXISTS (SELECT 1 FROM pegawai WHERE pegawai_pin=' . $this->value($db, $pin) . ');';
            $knownPins[$pin] = true;
            $report[] = $this->issue($pin, $name, 'Pegawai baru disiapkan. ID ditentukan aman saat SQL dijalankan; data hanya ditambahkan bila PIN belum ada pada database tujuan.');
        }

        $logs = $db->table('attendance_logs')->where('device_id', (int) $device['id'])->orderBy('scanned_at', 'ASC')->get()->getResultArray();
        $logSql = [];
        $skippedLogs = 0;
        $serials = [];
        foreach ($logs as $log) {
            $pin = trim((string) ($log['pin'] ?? ''));
            $sn = trim((string) ($log['machine_no'] ?? ''));
            $date = trim((string) ($log['scanned_at'] ?? ''));
            if ($pin === '' || !isset($knownPins[$pin]) || $sn === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date)) {
                $skippedLogs++;
                continue;
            }
            $serials[$sn] = true;
            $values = [
                $this->value($db, $sn), $this->value($db, $date), $this->value($db, $pin),
                (string) (int) ($log['verify_mode'] ?? 0), (string) (int) ($log['inout_mode'] ?? 0), '0', '0', $this->value($db, '0'),
            ];
            $logSql[] = 'INSERT IGNORE INTO att_log (sn,scan_date,pin,verifymode,inoutmode,reserved,work_code,att_id) VALUES (' . implode(',', $values) . ');';
        }
        if (count($serials) > 1) {
            $logSql = [];
            $report[] = $this->issue('-', 'Serial mesin', 'Terdapat beberapa serial: ' . implode(', ', array_keys($serials)) . '. Semua scan diblokir dari file SQL sampai sumber mesin dipastikan.');
        } elseif (count($serials) === 1) {
            $report[] = $this->issue('-', 'Serial mesin', 'Serial yang akan digunakan: ' . array_key_first($serials) . '. Pastikan sesuai dengan mesin target Fingerspot.');
        }
        $report[] = $this->issue('-', 'Ringkasan pegawai', count($masterSql) . ' perubahan master disiapkan; ' . $skippedEmployees . ' dilewati.');
        $report[] = $this->issue('-', 'Ringkasan scan', count($logSql) . ' scan disiapkan; ' . $skippedLogs . ' dilewati.');

        $sql = "-- NEURO ABSENSI to Fingerspot Personnel - SQL MERGE\n"
            . "-- Target: " . $label . "\n-- Generated: " . date('Y-m-d H:i:s') . "\n"
            . "-- Jalankan pada database Fingerspot yang SUDAH ADA. Backup dahulu.\n"
            . "-- Hanya tabel pegawai dan att_log yang diubah. Tidak ada DROP/CREATE.\n"
            . "SET NAMES utf8mb4;\nSTART TRANSACTION;\nSET @neuro_next_pegawai_id := (SELECT COALESCE(MAX(pegawai_id), 0) FROM pegawai);\n"
            . implode("\n", $masterSql) . "\n" . implode("\n", $logSql) . "\nCOMMIT;\n";
        return ['sql'=>$sql, 'report'=>$report, 'master'=>count($masterSql), 'skipped_master'=>$skippedEmployees, 'logs'=>count($logSql), 'skipped_logs'=>$skippedLogs];
    }

    private function value($db, $value): string
    {
        if ($value === null) return 'NULL';
        if (is_int($value) || is_float($value)) return (string) $value;
        return $db->escape((string) $value);
    }

    private function issue(string $pin, string $name, string $reason): array
    {
        return ['pin'=>$pin, 'name'=>$name, 'reason'=>$reason];
    }
}
