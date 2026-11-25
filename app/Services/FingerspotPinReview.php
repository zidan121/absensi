<?php

namespace App\Services;

class FingerspotPinReview
{
    public function inspect($db, array $device, string $sourceSql): array
    {
        $parser = new FingerspotSqlBackupParser();
        $sourceEmployees = $parser->parse($sourceSql, 'employees')['records'];
        $sourceLogs = $parser->parse($sourceSql, 'logs')['records'];
        if (!$sourceEmployees) {
            throw new \RuntimeException('Backup Fingerspot tidak memuat data tabel pegawai. Simpan ulang backup yang menyertakan tabel pegawai dan att_log.');
        }
        $serials = [];
        foreach ($sourceLogs as $row) {
            $serial = trim((string) ($row['machine_no'] ?? ''));
            if ($serial !== '') $serials[$serial] = true;
        }
        if (count($serials) !== 1) throw new \RuntimeException('Serial mesin pada backup tidak dapat dipastikan.');
        $sourceByPin = [];
        foreach ($sourceEmployees as $row) $sourceByPin[trim((string) $row['fingerprint_pin'])][] = $row;

        $employees = $db->table('employees')
            ->select('id, employee_code, fingerprint_pin, full_name, unit_code, is_active')
            ->where('fingerprint_device_id', (int) $device['id'])
            ->orderBy('full_name', 'ASC')->get()->getResultArray();
        $counts = [];
        foreach ($employees as $employee) {
            $pin = trim((string) ($employee['fingerprint_pin'] ?? ''));
            if ($pin !== '') $counts[$pin] = ($counts[$pin] ?? 0) + 1;
        }
        $scanRows = $db->table('attendance_logs')->select('pin, COUNT(*) AS total')
            ->where('device_id', (int) $device['id'])->where('machine_no', (string) array_key_first($serials))
            ->groupBy('pin')->get()->getResultArray();
        $scans = [];
        foreach ($scanRows as $row) $scans[trim((string) $row['pin'])] = (int) $row['total'];

        $rows = []; $approved = [];
        foreach ($employees as $employee) {
            $pin = trim((string) ($employee['fingerprint_pin'] ?? ''));
            if ($pin === '') continue;
            $matches = $sourceByPin[$pin] ?? [];
            $status = 'not_found'; $label = 'PIN tidak ditemukan'; $backupName = '';
            if (($counts[$pin] ?? 0) > 1 || count($matches) > 1) {
                $status = 'duplicate'; $label = 'PIN ganda';
            } elseif (count($matches) === 1) {
                $backupName = trim((string) $matches[0]['full_name']);
                if ($this->normalize($employee['full_name']) === $this->normalize($backupName)) {
                    $status = 'matched'; $label = 'PIN dan nama cocok';
                    $approved[] = $pin;
                } else {
                    $status = 'name_mismatch'; $label = 'PIN sama, nama berbeda';
                    $approved[] = $pin; // Checkbox is unchecked by default; explicit manual approval required.
                }
            }
            $rows[] = [
                'id'=>(int)$employee['id'], 'pin'=>$pin, 'neuro_name'=>(string)$employee['full_name'],
                'backup_name'=>$backupName, 'unit'=>(string)($employee['unit_code'] ?? '-'),
                'scan_count'=>(int)($scans[$pin] ?? 0), 'status'=>$status, 'status_label'=>$label,
            ];
        }
        $knownNeuroPins = [];
        foreach ($employees as $employee) {
            $known = trim((string) ($employee['fingerprint_pin'] ?? ''));
            if ($known !== '') $knownNeuroPins[$known] = true;
        }
        foreach ($scans as $scanPin => $scanCount) {
            if ($scanPin !== '' && !isset($knownNeuroPins[$scanPin])) {
                $rows[] = [
                    'id'=>0, 'pin'=>$scanPin, 'neuro_name'=>'Pegawai belum dipetakan',
                    'backup_name'=>isset($sourceByPin[$scanPin]) && count($sourceByPin[$scanPin]) === 1 ? (string)$sourceByPin[$scanPin][0]['full_name'] : '',
                    'unit'=>'-', 'scan_count'=>$scanCount, 'status'=>'not_found',
                    'status_label'=>'PIN scan belum terhubung ke pegawai NEURO',
                ];
            }
        }
        // Sort PINs ascending numerically (1, 2, 10, 11), not by employee name.
        usort($rows, static function (array $a, array $b): int {
            $pinA = trim((string) $a['pin']);
            $pinB = trim((string) $b['pin']);
            $digitsA = ctype_digit($pinA);
            $digitsB = ctype_digit($pinB);
            if ($digitsA && $digitsB) {
                $normA = ltrim($pinA, '0');
                $normB = ltrim($pinB, '0');
                $normA = $normA === '' ? '0' : $normA;
                $normB = $normB === '' ? '0' : $normB;
                return strlen($normA) <=> strlen($normB) ?: strcmp($normA, $normB);
            }
            if ($digitsA !== $digitsB) return $digitsA ? -1 : 1;
            return strnatcasecmp($pinA, $pinB);
        });

        return ['rows'=>$rows,'eligible_pins'=>array_values(array_unique($approved)),'serial'=>(string)array_key_first($serials),'source_employee_count'=>count($sourceEmployees)];
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? $name;
    }
}
