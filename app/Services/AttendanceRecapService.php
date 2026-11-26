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
    public function build(string $startDate, string $endDate, string|array|null $unit = null): array
    {
        $units = $unit === null || $unit === '' ? [] : (is_array($unit) ? $unit : [$unit]);
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

        $identityCandidates = [];
        if ($deviceIds !== []) {
            // Resolve identity against current mappings and their effective-dated history.
            // This keeps scans from before a PIN/device reassignment attached to the old employee.
            $current = $this->db->table('employee_fingerprint_mappings m')
                ->select('e.id, m.device_id, m.fingerprint_pin, e.employee_code, e.full_name, e.position, e.unit_code, m.effective_from, NULL AS effective_until')
                ->join('employees e', 'e.id = m.employee_id')
                ->whereIn('m.device_id', $deviceIds)
                ->get()->getResultArray();

            $history = $this->db->table('employee_fingerprint_mapping_history h')
                ->select('e.id, h.device_id, h.fingerprint_pin, e.employee_code, e.full_name, e.position, e.unit_code, h.effective_from, h.effective_until')
                ->join('employees e', 'e.id = h.employee_id')
                ->whereIn('h.device_id', $deviceIds)
                ->get()->getResultArray();

            $seenMappings = [];
            foreach (array_merge($history, $current) as $mapping) {
                $key = (int) $mapping['device_id'] . '|' . $this->pinMatchKey((string) $mapping['fingerprint_pin']);
                $signature = implode('|', [
                    $mapping['id'], $mapping['device_id'], $mapping['fingerprint_pin'],
                    $mapping['effective_from'] ?? '', $mapping['effective_until'] ?? '',
                ]);
                if (isset($seenMappings[$signature])) {
                    continue;
                }
                $seenMappings[$signature] = true;
                $identityCandidates[$key][] = $mapping;
            }
        }

        $groups = [];
        $unmatched = [];
        foreach ($logs as $log) {
            $identityKey = (int) $log['device_id'] . '|' . $this->pinMatchKey((string) $log['pin']);
            $scanDate = substr((string) $log['scanned_at'], 0, 10);
            $candidates = array_values(array_filter(
                $identityCandidates[$identityKey] ?? [],
                static fn ($mapping) =>
                    (empty($mapping['effective_from']) || $mapping['effective_from'] <= $scanDate)
                    && (empty($mapping['effective_until']) || $mapping['effective_until'] >= $scanDate)
            ));
            $candidateEmployees = [];
            foreach ($candidates as $candidate) {
                $candidateEmployees[(int) $candidate['id']] = $candidate;
            }
            $employee = count($candidateEmployees) === 1 ? reset($candidateEmployees) : null;

            if ($employee === null) {
                $key = (int) $log['device_id'] . '|' . (string) $log['pin'];
                if (! isset($unmatched[$key])) {
                    $unmatched[$key] = [
                        'device_id' => (int) $log['device_id'],
                        'pin' => (string) $log['pin'],
                        'scan_count' => 0,
                        'first_scan' => (string) $log['scanned_at'],
                        'last_scan' => (string) $log['scanned_at'],
                    ];
                }
                $unmatched[$key]['scan_count']++;
                $unmatched[$key]['first_scan'] = min($unmatched[$key]['first_scan'], (string) $log['scanned_at']);
                $unmatched[$key]['last_scan'] = max($unmatched[$key]['last_scan'], (string) $log['scanned_at']);
                continue;
            }
            if ($units !== [] && ! in_array($employee['unit_code'], $units, true)) {
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

        // Attach device labels so unmatched identities can be diagnosed from the recap page.
        if ($unmatched !== []) {
            $unmatchedDeviceIds = array_values(array_unique(array_column($unmatched, 'device_id')));
            $devices = $this->db->table('devices')
                ->select('id, device_code, device_name')
                ->whereIn('id', $unmatchedDeviceIds)
                ->get()->getResultArray();
            $deviceLabels = [];
            foreach ($devices as $device) {
                $deviceLabels[(int) $device['id']] = $device;
            }
            foreach ($unmatched as &$identity) {
                $device = $deviceLabels[$identity['device_id']] ?? null;
                $identity['device_code'] = $device['device_code'] ?? ('Device #' . $identity['device_id']);
                $identity['device_name'] = $device['device_name'] ?? 'Perangkat tidak ditemukan';
            }
            unset($identity);
            $unmatched = array_values($unmatched);
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
            $weeklySchedule = $this->weeklyScheduleFor($group['unit_code'], $group['date']);
            // Individual work days override the unit default for this employee and period.
            $workDays = $schedule['work_days'] ?? $weeklySchedule['work_days'] ?? null;
            $weekday = (int) (new \DateTimeImmutable($group['date']))->format('N');
            $isWeeklyOff = $workDays !== null
                && ! in_array($weekday, array_map('intval', explode(',', $workDays)), true);
            $specialWorkday = in_array($calendarEvent['event_type'] ?? '', ['late_start', 'workday'], true);
            $isNonWorkingDay = ! $isHoliday && $isWeeklyOff && ! $specialWorkday;
            $isLate = ! $isHoliday && ! $isNonWorkingDay && $arrivalTime > $lateAfter;
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
                    : ($isNonWorkingDay
                        ? 'Libur Mingguan (ada scan)'
                        : ($lateMinutes > 0
                            ? 'Terlambat'
                            : ($specialWorkday
                                ? ($calendarEvent['title'] . ' — Tepat Waktu')
                                : 'Tepat Waktu'))),
                'all_scans' => $scans,
            ];
        }
        // Sort attendance details by fingerprint PIN (natural order), then chronologically per PIN.
        usort($rows, static function ($a, $b): int {
            $pinCompare = strnatcasecmp(
                (string) ($a['fingerprint_pin'] ?? ''),
                (string) ($b['fingerprint_pin'] ?? '')
            );

            if ($pinCompare !== 0) {
                return $pinCompare;
            }

            return [$a['date'], $a['unit_code'], $a['full_name']]
                <=> [$b['date'], $b['unit_code'], $b['full_name']];
        });

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'unit' => $units,
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

    private function weeklyScheduleFor(string $unitCode, string $date): ?array
    {
        return $this->db->table('unit_weekly_schedules')
            ->where('unit_code', $unitCode)
            ->where('effective_from <=', $date)
            ->groupStart()->where('effective_to >=', $date)->orWhere('effective_to', null)->groupEnd()
            ->orderBy('effective_from', 'DESC')
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
