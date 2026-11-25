<?php

namespace App\\Services;

/**
 * Incremental attendance-log export. This class never writes to Fingerspot pegawai.
 * Employee/device/PIN identity is read only from employee_fingerprint_mappings.
 */
class FingerspotSqlGenerator
{
    private const LOG_COLUMNS = ['sn', 'scan_date', 'pin', 'verifymode', 'inoutmode', 'reserved', 'work_code', 'att_id'];

    public function generate($db, array $device, string $sourceSql, string $label, array $approvedPins = []): array
    {
        $source = (new FingerspotSqlBackupParser())->parse($sourceSql, 'logs');
        if ($source['records'] === []) throw new \\RuntimeException('Backup Fingerspot sumber tidak memiliki riwayat scan.');
        $serials = [];
        foreach ($source['records'] as $row) {
            $serial = trim((string) ($row['machine_no'] ?? ''));
            if ($serial !== '') $serials[$serial] = true;
        }
        if (count($serials) !== 1) throw new \\RuntimeException('Serial mesin pada backup tidak dapat dipastikan.');
        $serial = (string) array_key_first($serials);

        $mappingRows = $db->table('employee_fingerprint_mappings')
            ->select('fingerprint_pin')->where('device_id', (int) $device['id'])
            ->get()->getResultArray();
        $pinCounts = [];
        foreach ($mappingRows as $row) {
            $pin = trim((string) $row['fingerprint_pin']);
            if ($pin !== '') $pinCounts[$pin] = ($pinCounts[$pin] ?? 0) + 1;
        }
        $validPins = []; $ambiguousPins = [];
        foreach ($pinCounts as $pin => $count) {
            if ($count === 1) $validPins[$pin] = true;
            else $ambiguousPins[$pin] = true;
        }
        $approved = array_fill_keys(array_map('strval', $approvedPins), true);
        $validPins = array_intersect_key($validPins, $approved);
        if ($validPins === []) throw new \\RuntimeException('Tidak ada PIN pemetaan perangkat yang disetujui. Ulangi Review PIN.');

        $logs = $db->table('attendance_logs')->where('device_id', (int) $device['id'])
            ->where('machine_no', $serial)->orderBy('scanned_at', 'ASC')->get()->getResultArray();
        $tuples = []; $seen = []; $skippedLogs = 0; $skippedAmbiguous = 0;
        foreach ($logs as $log) {
            $pin = trim((string) ($log['pin'] ?? ''));
            $date = trim((string) ($log['scanned_at'] ?? ''));
            $machineNo = trim((string) ($log['machine_no'] ?? ''));
            if ($machineNo !== $serial || $pin === '' || !isset($validPins[$pin])) {
                if ($pin !== '' && isset($ambiguousPins[$pin])) $skippedAmbiguous++;
                $skippedLogs++; continue;
            }
            $parsedDate = \\DateTime::createFromFormat('!Y-m-d H:i:s', $date);
            if (!$parsedDate || $parsedDate->format('Y-m-d H:i:s') !== $date) { $skippedLogs++; continue; }
            $key = $machineNo . '|' . $date . '|' . $pin;
            if (isset($seen[$key])) { $skippedLogs++; continue; }
            $seen[$key] = true;
            $tuples[] = '(' . implode(',', [
                $this->value($db, $machineNo), $this->value($db, $date), $this->value($db, $pin),
                (string) (int) ($log['verify_mode'] ?? 0), (string) (int) ($log['inout_mode'] ?? 0),
                '0', '0', $this->value($db, '0'),
            ]) . ')';
        }
        if ($tuples === []) throw new \\RuntimeException('Tidak ada scan valid untuk diekspor. Tidak ada file dibuat.');

        $statements = [];
        foreach (array_chunk($tuples, 250) as $chunk) {
            $statements[] = 'INSERT IGNORE INTO att_log (' . implode(',', self::LOG_COLUMNS) . ') VALUES' . "\\n" . implode(",\\n", $chunk) . ';';
        }
        $safeLabel = preg_replace('/[^A-Za-z0-9 _&.-]/', '', $label);
        $sql = "-- NEURO ABSENSI -> Fingerspot Personnel (LOG ONLY)\\n"
            . "-- Target: " . $safeLabel . "\\n"
            . "-- Generated: " . date('Y-m-d H:i:s') . "\\n"
            . "-- Verified machine serial: " . $serial . "\\n"
            . "-- ONLY att_log is modified. pegawai and all master/configuration tables are untouched.\\n"
            . "-- Duplicate scans are ignored by the target unique key (sn, scan_date, pin).\\n\\n"
            . "SET NAMES utf8mb4;\\nSTART TRANSACTION;\\n"
            . implode("\\n\\n", $statements) . "\\nCOMMIT;\\n";

        $report = [
            ['pin' => '-', 'name' => 'Jenis ekspor', 'reason' => 'Log absensi saja; tabel pegawai dan konfigurasi Fingerspot tidak diubah.'],
            ['pin' => '-', 'name' => 'Perangkat', 'reason' => $label . ' — ' . $serial],
            ['pin' => '-', 'name' => 'Pemetaan PIN', 'reason' => count($validPins) . ' PIN perangkat disetujui; ' . count($ambiguousPins) . ' PIN ganda dikecualikan.'],
            ['pin' => '-', 'name' => 'Scan siap diekspor', 'reason' => count($tuples) . ' scan valid.'],
            ['pin' => '-', 'name' => 'Scan dilewati', 'reason' => $skippedLogs . ' scan, termasuk ' . $skippedAmbiguous . ' scan dengan PIN ambigu.'],
        ];
        return ['sql' => $sql, 'report' => $report, 'master' => 0, 'skipped_master' => 0, 'logs' => count($tuples), 'skipped_logs' => $skippedLogs];
    }

    private function value($db, $value): string
    {
        if ($value === null) return 'NULL';
        if (is_int($value) || is_float($value)) return (string) $value;
        return $db->escape((string) $value);
    }
}
