<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class AttendanceRecapService
{
    private const DEFAULT_LATE_AFTER = [
        'KBIT' => '07:45:59',
        'RAIT' => '07:15:59',
        'SDIT' => '07:00:59',
        'SMPIT' => '07:00:59',
        'YAYASAN' => '07:45:59',
    ];

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /**
     * Normalize numeric PINs for comparison only. Stored source values are never changed.
     * Alphanumeric PINs remain case-sensitive and are compared exactly.
     */
    private function pinMatchKey(string $pin): string
    {
        $pin = trim($pin);
        if ($pin !== '' && ctype_digit($pin)) {
            $normalized = ltrim($pin, '0');
            return 'N:' . ($normalized === '' ? '0' : $normalized);
        }

        return 'S:' . $pin;
    }

    /**
     * Build a recap for an inclusive date range.
     */
    public function build(string $startDate, string $endDate, ?string $unit = null): array
    {
        $start = $startDate . ' 00:00:00';
        $endExclusive = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';

        // Fetch scans first; resolve identities in PHP so numeric PIN padding can be normalized safely.
        $logs = $this->db->table('attendance_logs')
            ->select('device_id, pin, scanned_at')
            ->where('scanned_at >=', $start)
            ->where('scanned_at <', $endExclusive)
            ->orderBy('scanned_at', 'ASC')
            ->get()->getResultArray();

        $deviceIds = array_values(array_unique(array_map(
            static fn ($log) => (int) $log['device_id'],
            $logs
        )));

        $employeeMap = [];
        $ambiguousKeys = [];
        if ($deviceIds !== []) {
            $employees = $this->db->table('employees')
                ->select('id, fingerprint_device_id, fingerprint_pin, employee_code, full_name, position, unit_code')
                ->whereIn('fingerprint_device_id', $deviceIds)
                ->where('fingerprint_pin IS NOT NULL', null, false)
                ->get()->getResultArray();

            foreach ($employees as $employee) {
                $key = (int) $employee['fingerprint_device_id'] . '|' . $this->pinMatchKey((string) $employee['fingerprint_pin']);
                if (isset($employeeMap[$key])) {
                    // Never guess if two master rows collapse to the same normalized identity.
                    $ambiguousKeys[$key] = true;
                    unset($employeeMap[$key]);
                    continue;
                }
                if (! isset($ambiguousKeys[$key])) {
                    $employeeMap[$key] = $employee;
                }
            }
        }

        $groups = [];
        $unmatched = [];
        foreach ($logs as $log) {
            $identityKey = (int) $log['device_id'] . '|' . $this->pinMatchKey((string) $log['pin']);
            $employee = isset($ambiguousKeys[$identityKey]) ? null : ($employeeMap[$identityKey] ?? null);

            if ($employee === null) {
                $key = $log['device_id'] . '|' . $log['pin'];
                $unmatched[$key] = ($unmatched[$key] ?? 0) + 1;
                continue;
            }
            if ($unit !== null && $unit !== '' && $employee['unit_code'] !== $unit) {
                continue;
            }

            $date = substr($log['scanned_at'], 0, 10);
            $key = $employee['id'] . '|' . $date;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'employee_id' => (int) $employee['id'],
                    'employee_code' => $employee['employee_code'],
                    'fingerprint_pin' => $employee['fingerprint_pin'],
                    'full_name' => $employee['full_name'],
                    'position' => $employee['position'] ?? '',
                    'unit_code' => $employee['unit_code'],
                    'date' => $date,
                    'scans' => [],
                ];
            }
            $groups[$key]['scans'][] = $log['scanned_at'];
        }

        $rows = [];
        foreach ($groups as $group) {
            // Identical timestamps on the same employee/day count as one scan.
            // Keep distinct timestamps only, so a duplicated same-time event does not create a false departure.
            $scans = array_values(array_unique($group['scans'], SORT_STRING));
            sort($scans);
            $first = $scans[0];
            $last = count($scans) > 1 ? $scans[count($scans) - 1] : null;
            $calendarEvent = $this->calendarEventFor(
                $group['employee_id'],
                $group['unit_code'],
                $group['date']
            );
            $schedule = $this->scheduleFor($group['employee_id'], $group['date']);
            $unitRule = $schedule ? null : $this->unitRuleFor($group['unit_code'], $group['date']);
            $lateAfter = $calendarEvent['late_after']
                ?? $schedule['late_after']
                ?? $unitRule['late_after']
                ?? (self::DEFAULT_LATE_AFTER[$group['unit_code']] ?? '07:45:59');
            $arrivalTime = substr($first, 11, 8);
            $isHoliday = ($calendarEvent['event_type'] ?? null) === 'holiday';
            $isLate = ! $isHoliday && $arrivalTime > $lateAfter;
            $lateSeconds = $isLate
                ? max(0, strtotime($group['date'] . ' ' . $arrivalTime) - strtotime($group['date'] . ' ' . $lateAfter))
                : 0;
            $lateMinutes = $isLate ? max(1, (int) ceil($lateSeconds / 60)) : 0;
            $rows[] = [
                'employee_id' => $group['employee_id'],
                'employee_code' => $group['employee_code'],
                'fingerprint_pin' => $group['fingerprint_pin'],
                'full_name' => $group['full_name'],
                'position' => $group['position'],
                'unit_code' => $group['unit_code'],
                'date' => $group['date'],
                'arrival' => $arrivalTime,
                'departure' => $last ? substr($last, 11, 8) : '',
                'scan_count' => count($scans),
                'scans' => array_map(static fn ($scan) => substr($scan, 11, 8), $scans),
                'late_after' => $isHoliday ? '-' : $lateAfter,
                'late_minutes' => $lateMinutes,
                'calendar_event' => $calendarEvent,
                'status' => $isHoliday
                    ? 'Hari Libur (ada scan)'
                    : ($lateMinutes > 0 ? 'Terlambat (' . $lateMinutes . ' menit)' : 'Tepat Waktu'),
                'all_scans' => $scans,
            ];
        }
        usort($rows, static fn ($a, $b) => [$a['unit_code'], $a['full_name'], $a['date']] <=> [$b['unit_code'], $b['full_name'], $b['date']]);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'unit' => $unit,
            'rows' => $rows,
            'unmatched' => $unmatched,
            'summary' => [
                'attendance_days' => count($rows),
                'late_days' => count(array_filter($rows, static fn ($r) => $r['late_minutes'] > 0)),
                'one_scan_days' => count(array_filter($rows, static fn ($r) => $r['scan_count'] === 1)),
                'unmatched_identities' => count($unmatched),
            ],
        ];
    }

    private function calendarEventFor(int $employeeId, string $unitCode, string $date): ?array
    {
        return $this->db->table('work_calendar_events')
            ->groupStart()
                ->where('target_type', 'all')
                ->orGroupStart()
                    ->where('target_type', 'unit')
                    ->where('unit_code', $unitCode)
                ->groupEnd()
                ->orGroupStart()
                    ->where('target_type', 'employee')
                    ->where('employee_id', $employeeId)
                ->groupEnd()
            ->groupEnd()
            ->where('event_date', $date)
            ->orderBy("CASE target_type WHEN 'employee' THEN 1 WHEN 'unit' THEN 2 ELSE 3 END", '', false)
            ->get(1)->getRowArray();
    }

    private function unitRuleFor(string $unitCode, string $date): ?array
    {
        return $this->db->table('unit_attendance_rules')
            ->where('unit_code', $unitCode)
            ->where('effective_from <=', $date)
            ->groupStart()->where('effective_to >=', $date)->orWhere('effective_to', null)->groupEnd()
            ->orderBy('effective_from', 'DESC')
            ->get(1)->getRowArray();
    }

    private function scheduleFor(int $employeeId, string $date): ?array
    {
        return $this->db->table('employee_schedules')
            ->where('employee_id', $employeeId)
            ->where('effective_from <=', $date)
            ->groupStart()->where('effective_to >=', $date)->orWhere('effective_to', null)->groupEnd()
            ->orderBy('effective_from', 'DESC')
            ->get(1)->getRowArray();
    }
}
