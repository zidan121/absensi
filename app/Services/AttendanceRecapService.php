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
     * Build a recap for an inclusive date range.
     */
    public function build(string $startDate, string $endDate, ?string $unit = null): array
    {
        $start = $startDate . ' 00:00:00';
        $endExclusive = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';

        $builder = $this->db->table('attendance_logs l')
            ->select('l.device_id, l.pin, l.scanned_at, e.id AS employee_id, e.employee_code, e.full_name, e.unit_code')
            ->join('employees e', 'e.fingerprint_device_id = l.device_id AND e.fingerprint_pin = l.pin', 'left')
            ->where('l.scanned_at >=', $start)
            ->where('l.scanned_at <', $endExclusive)
            ->orderBy('l.scanned_at', 'ASC');

        if ($unit !== null && $unit !== '') {
            $builder->where('e.unit_code', $unit);
        }

        $logs = $builder->get()->getResultArray();
        $groups = [];
        $unmatched = [];
        foreach ($logs as $log) {
            if (empty($log['employee_id'])) {
                $key = $log['device_id'] . '|' . $log['pin'];
                $unmatched[$key] = ($unmatched[$key] ?? 0) + 1;
                continue;
            }
            $date = substr($log['scanned_at'], 0, 10);
            $key = $log['employee_id'] . '|' . $date;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'employee_id' => (int) $log['employee_id'],
                    'employee_code' => $log['employee_code'],
                    'full_name' => $log['full_name'],
                    'unit_code' => $log['unit_code'],
                    'date' => $date,
                    'scans' => [],
                ];
            }
            $groups[$key]['scans'][] = $log['scanned_at'];
        }

        $rows = [];
        foreach ($groups as $group) {
            $scans = $group['scans'];
            sort($scans);
            $first = $scans[0];
            $last = count($scans) > 1 ? $scans[count($scans) - 1] : null;
            $schedule = $this->scheduleFor($group['employee_id'], $group['date']);
            $lateAfter = $schedule['late_after'] ?? (self::DEFAULT_LATE_AFTER[$group['unit_code']] ?? '07:45:59');
            $isLate = substr($first, 11, 8) > $lateAfter;
            $rows[] = [
                'employee_id' => $group['employee_id'],
                'employee_code' => $group['employee_code'],
                'full_name' => $group['full_name'],
                'unit_code' => $group['unit_code'],
                'date' => $group['date'],
                'arrival' => substr($first, 11, 8),
                'departure' => $last ? substr($last, 11, 8) : '',
                'scan_count' => count($scans),
                'late_after' => $lateAfter,
                'late_minutes' => $isLate ? max(0, (int) floor((strtotime($group['date'] . ' ' . $first) - strtotime($group['date'] . ' ' . $lateAfter)) / 60)) : 0,
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
