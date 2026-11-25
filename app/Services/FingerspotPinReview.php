<?php

namespace App\Services;

class FingerspotPinReview
{
    public function inspect($db, array $device, string $sourceSql): array
    {
        $parser = new FingerspotSqlBackupParser();
        $sourceEmployees = $parser->parse($sourceSql, 'employees')['records'];
        $sourceLogs = $parser->parse($sourceSql, 'logs')['records'];
        if (!$sourceEmployees) throw new \RuntimeException('Backup Fingerspot tidak memuat data tabel pegawai.');
        $serials = [];
        foreach ($sourceLogs as $row) {
            $serial = trim((string) ($row['machine_no'] ?? ''));
            if ($serial !== '') $serials[$serial] = true;
        }
        if (count($serials) !== 1) throw new \RuntimeException('Serial mesin pada backup tidak dapat dipastikan.');
        $serial = (string) array_key_first($serials);

        $sourceByPin = []; $sourceById = [];
        foreach ($sourceEmployees as $row) {
            $sourceByPin[trim((string) $row['fingerprint_pin'])][] = $row;
            $sourceById[(string) $row['source_employee_id']] = $row;
        }

        $mappings = $db->table('employee_fingerprint_mappings m')
            ->select('m.employee_id, m.fingerprint_pin, m.target_pegawai_id, e.employee_code, e.full_name, e.unit_code, e.is_active')
            ->join('employees e', 'e.id = m.employee_id')
            ->where('m.device_id', (int) $device['id'])
            ->orderBy('e.full_name', 'ASC')->get()->getResultArray();
        $pinCounts = [];
        foreach ($mappings as $mapping) {
            $pin = trim((string) $mapping['fingerprint_pin']);
            if ($pin !== '') $pinCounts[$pin] = ($pinCounts[$pin] ?? 0) + 1;
        }

        $scanRows = $db->table('attendance_logs')->select('pin, COUNT(*) AS total')
            ->where('device_id', (int) $device['id'])->where('machine_no', $serial)
            ->groupBy('pin')->get()->getResultArray();
        $scans = [];
        foreach ($scanRows as $row) $scans[trim((string) $row['pin'])] = (int) $row['total'];

        $rows = []; $approved = []; $knownPins = [];
        foreach ($mappings as $mapping) {
            $pin = trim((string) $mapping['fingerprint_pin']);
            if ($pin === '') continue;
            $knownPins[$pin] = true;
            $matches = [];
            $targetId = trim((string) ($mapping['target_pegawai_id'] ?? ''));
            if ($targetId !== '') {
                if (isset($sourceById[$targetId])) $matches = [$sourceById[$targetId]];
            } else {
                $matches = $sourceByPin[$pin] ?? [];
            }

            $status = 'not_found'; $label = 'Pegawai tidak ditemukan di backup'; $backupName = '';
            if (($pinCounts[$pin] ?? 0) > 1 || count($matches) > 1) {
                $status = 'duplicate'; $label = 'PIN/identitas ganda';
            } elseif (count($matches) === 1) {
                $backupName = trim((string) $matches[0]['full_name']);
                if ($this->normalize((string) $mapping['full_name']) === $this->normalize($backupName)) {
                    $status = 'matched'; $label = 'Identitas cocok'; $approved[] = $pin;
                } else {
                    $status = 'name_mismatch'; $label = 'Nama berbeda — perlu persetujuan manual'; $approved[] = $pin;
                }
            }
            $rows[] = [
                'id' => (int) $mapping['employee_id'], 'pin' => $pin,
                'neuro_name' => (string) $mapping['full_name'], 'backup_name' => $backupName,
                'unit' => (string) ($mapping['unit_code'] ?? '-'),
                'scan_count' => (int) ($scans[$pin] ?? 0), 'status' => $status, 'status_label' => $label,
            ];
        }

        foreach ($scans as $scanPin => $scanCount) {
            if ($scanPin !== '' && !isset($knownPins[$scanPin])) {
                $sourceMatch = $sourceByPin[$scanPin] ?? [];
                $rows[] = [
                    'id' => 0, 'pin' => $scanPin, 'neuro_name' => 'Belum dipetakan di NEURO',
                    'backup_name' => count($sourceMatch) === 1 ? (string) $sourceMatch[0]['full_name'] : '',
                    'unit' => '-', 'scan_count' => $scanCount, 'status' => 'not_found',
                    'status_label' => 'Tambahkan pemetaan pegawai/perangkat terlebih dahulu',
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $pa = trim((string) $a['pin']); $pb = trim((string) $b['pin']);
            $da = ctype_digit($pa); $db = ctype_digit($pb);
            if ($da && $db) {
                $na = ltrim($pa, '0'); $nb = ltrim($pb, '0');
                $na = $na === '' ? '0' : $na; $nb = $nb === '' ? '0' : $nb;
                return strlen($na) <=> strlen($nb) ?: strcmp($na, $nb);
            }
            return $da !== $db ? ($da ? -1 : 1) : strnatcasecmp($pa, $pb);
        });

        return [
            'rows' => $rows, 'eligible_pins' => array_values(array_unique($approved)),
            'serial' => $serial, 'source_employee_count' => count($sourceEmployees),
        ];
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? $name;
    }
}
