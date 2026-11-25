<?php

namespace App\Services;

/**
 * Generate a safe, incremental SQL import for an existing Fingerspot Personnel database.
 *
 * The generated script ONLY inserts attendance rows into att_log. It never changes
 * pegawai, schedules, divisions, devices, or any other Fingerspot master/configuration.
 */
class FingerspotSqlGenerator
{
    private const LOG_COLUMNS = [
        'sn',
        'scan_date',
        'pin',
        'verifymode',
        'inoutmode',
        'reserved',
        'work_code',
        'att_id',
    ];

    public function generate($db, array $device, string $sourceSql, string $label, array $approvedPins = []): array
    {
        $parser = new FingerspotSqlBackupParser();
        $source = $parser->parse($sourceSql, 'logs');

        if ($source['records'] === []) {
            throw new \RuntimeException('Backup Fingerspot sumber tidak memiliki riwayat scan yang dapat dibaca.');
        }

        // Use the original Fingerspot backup only to verify the machine serial.
        // Do not import or update employee master data from this file.
        $serials = [];
        foreach ($source['records'] as $row) {
            $serial = trim((string) ($row['machine_no'] ?? ''));
            if ($serial !== '') {
                $serials[$serial] = true;
            }
        }

        if (count($serials) !== 1) {
            throw new \RuntimeException(
                'Backup SMPIT harus menunjukkan tepat satu serial mesin. Ditemukan: '
                . (count($serials) ? implode(', ', array_keys($serials)) : 'tidak ada serial')
                . '. Periksa backup sumber sebelum membuat SQL.'
            );
        }

        $serial = (string) array_key_first($serials);

        $employees = $db->table('employees')
            ->select('fingerprint_pin')
            ->where('fingerprint_device_id', (int) $device['id'])
            ->where('fingerprint_pin IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        $pinCounts = [];
        foreach ($employees as $employee) {
            $pin = trim((string) ($employee['fingerprint_pin'] ?? ''));
            if ($pin !== '') {
                $pinCounts[$pin] = ($pinCounts[$pin] ?? 0) + 1;
            }
        }

        $validPins = [];
        $ambiguousPins = [];
        foreach ($pinCounts as $pin => $count) {
            if ($count === 1) {
                $validPins[$pin] = true;
            } else {
                $ambiguousPins[$pin] = true;
            }
        }

        $approved = array_fill_keys(array_map('strval', $approvedPins), true);
        $validPins = array_intersect_key($validPins, $approved);
        if ($validPins === []) {
            throw new \\RuntimeException('Tidak ada PIN unik yang disetujui dari halaman Review PIN. Lakukan review terlebih dahulu.');
        }

        $logs = $db->table('attendance_logs')
            ->where('device_id', (int) $device['id'])
            ->where('machine_no', $serial)
            ->orderBy('scanned_at', 'ASC')
            ->get()
            ->getResultArray();

        $tuples = [];
        $seen = [];
        $skippedLogs = 0;
        $skippedAmbiguous = 0;

        foreach ($logs as $log) {
            $pin = trim((string) ($log['pin'] ?? ''));
            $date = trim((string) ($log['scanned_at'] ?? ''));
            $machineNo = trim((string) ($log['machine_no'] ?? ''));

            if ($machineNo !== $serial || $pin === '' || !isset($validPins[$pin])) {
                if ($pin !== '' && isset($ambiguousPins[$pin])) {
                    $skippedAmbiguous++;
                }
                $skippedLogs++;
                continue;
            }

            $parsedDate = \DateTime::createFromFormat('!Y-m-d H:i:s', $date);
            if (!$parsedDate || $parsedDate->format('Y-m-d H:i:s') !== $date) {
                $skippedLogs++;
                continue;
            }

            $key = $machineNo . '|' . $date . '|' . $pin;
            if (isset($seen[$key])) {
                $skippedLogs++;
                continue;
            }
            $seen[$key] = true;

            $tuples[] = '(' . implode(',', [
                $this->value($db, $machineNo),
                $this->value($db, $date),
                $this->value($db, $pin),
                (string) (int) ($log['verify_mode'] ?? 0),
                (string) (int) ($log['inout_mode'] ?? 0),
                '0',
                '0',
                $this->value($db, '0'),
            ]) . ')';
        }

        if ($tuples === []) {
            throw new \RuntimeException('Tidak ada scan yang lolos validasi untuk serial mesin ' . $serial . '. Tidak ada file SQL dibuat.');
        }

        $insertStatements = [];
        foreach (array_chunk($tuples, 250) as $chunk) {
            $insertStatements[] = 'INSERT IGNORE INTO att_log ('
                . implode(',', self::LOG_COLUMNS)
                . ') VALUES' . "\n"
                . implode(",\n", $chunk) . ';';
        }

        $sql = "-- NEURO ABSENSI -> Fingerspot Personnel (LOG ONLY)\n"
            . "-- Target: " . preg_replace('/[^A-Za-z0-9 _&.-]/', '', $label) . "\n"
            . "-- Generated: " . date('Y-m-d H:i:s') . "\n"
            . "-- Machine serial verified from the stored Fingerspot source backup: " . $serial . "\n"
            . "-- This is an incremental import script, NOT a full database backup.\n"
            . "-- ONLY att_log is modified. pegawai and all master/configuration tables are untouched.\n"
            . "-- Duplicate scans are ignored by the target table's unique key (sn, scan_date, pin).\n"
            . "-- Select the existing Fingerspot database (for SMPIT: fin_pro) before importing.\n\n"
            . "SET NAMES utf8mb4;\n"
            . "START TRANSACTION;\n"
            . implode("\n\n", $insertStatements) . "\n"
            . "COMMIT;\n";

        $report = [
            ['pin' => '-', 'name' => 'Jenis ekspor', 'reason' => 'Log absensi saja. Tidak ada perubahan pada tabel pegawai maupun konfigurasi Fingerspot.'],
            ['pin' => '-', 'name' => 'Serial mesin', 'reason' => $serial . ' — diverifikasi dari backup SQL Fingerspot SMPIT yang disimpan di aplikasi.'],
            ['pin' => '-', 'name' => 'PIN pegawai', 'reason' => count($validPins) . ' PIN unik dipakai untuk menyaring scan; ' . count($ambiguousPins) . ' PIN ganda dikecualikan.'],
            ['pin' => '-', 'name' => 'Scan siap diekspor', 'reason' => count($tuples) . ' scan valid.'],
            ['pin' => '-', 'name' => 'Scan dilewati', 'reason' => $skippedLogs . ' scan dilewati, termasuk ' . $skippedAmbiguous . ' scan dengan PIN ganda atau ambigu.'],
        ];

        return [
            'sql' => $sql,
            'report' => $report,
            'master' => 0,
            'skipped_master' => 0,
            'logs' => count($tuples),
            'skipped_logs' => $skippedLogs,
        ];
    }

    private function value($db, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return $db->escape((string) $value);
    }
}
