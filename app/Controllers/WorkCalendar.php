<?php

namespace App\Controllers;

use DateTimeImmutable;

class WorkCalendar extends BaseController
{
    private const UNITS = ['KBIT', 'RAIT', 'SDIT', 'SMPIT', 'YAYASAN', 'LAZIS'];

    public function index()
    {
        $db = db_connect();
        $events = $db->table('work_calendar_events e')
            ->select('e.*, employees.full_name')
            ->join('employees', 'employees.id = e.employee_id', 'left')
            ->orderBy('e.event_date', 'DESC')
            ->orderBy('e.id', 'DESC')
            ->get(150)->getResultArray();

        $employees = $db->table('employees')
            ->select('id, full_name, unit_code, fingerprint_pin')
            ->where('is_active', 1)
            ->orderBy('full_name', 'ASC')
            ->get()->getResultArray();

        return view('work_calendar/index', [
            'title' => 'Kalender Kerja',
            'units' => self::UNITS,
            'events' => $events,
            'employees' => $employees,
        ]);
    }

    public function store()
    {
        $start = (string) $this->request->getPost('start_date');
        $end = (string) $this->request->getPost('end_date');
        $targetType = (string) $this->request->getPost('target_type');
        $unit = strtoupper(trim((string) $this->request->getPost('unit_code')));
        $employeeId = (int) $this->request->getPost('employee_id');
        $eventType = (string) $this->request->getPost('event_type');
        $title = trim((string) $this->request->getPost('title'));
        $lateAfter = $this->normalizeTime((string) $this->request->getPost('late_after'));
        $note = trim((string) $this->request->getPost('note'));

        if (! $this->validDate($start) || ! $this->validDate($end) || $end < $start) {
            return redirect()->back()->withInput()->with('error', 'Rentang tanggal tidak valid.');
        }
        $days = (int) ((new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days) + 1;
        if ($days > 366) {
            return redirect()->back()->withInput()->with('error', 'Satu pengaturan maksimal 366 hari.');
        }
        if (! in_array($targetType, ['all', 'unit', 'employee'], true)) {
            return redirect()->back()->withInput()->with('error', 'Target pengaturan tidak valid.');
        }
        if ($targetType === 'unit' && ! in_array($unit, self::UNITS, true)) {
            return redirect()->back()->withInput()->with('error', 'Unit tidak valid.');
        }
        if ($targetType === 'employee' && ! db_connect()->table('employees')->where('id', $employeeId)->countAllResults()) {
            return redirect()->back()->withInput()->with('error', 'Pegawai tidak ditemukan.');
        }
        if (! in_array($eventType, ['holiday', 'late_start', 'workday'], true)) {
            return redirect()->back()->withInput()->with('error', 'Jenis kalender tidak valid.');
        }
        if ($title === '') {
            $title = match ($eventType) {
                'holiday' => 'Hari Libur',
                'late_start' => 'Masuk Siang',
                default => 'Hari Kerja Khusus',
            };
        }
        if ($eventType !== 'holiday' && $lateAfter === null) {
            return redirect()->back()->withInput()->with('error', 'Isi batas tidak terlambat untuk hari kerja khusus.');
        }

        $db = db_connect();
        $target = [
            'target_type' => $targetType,
            'unit_code' => $targetType === 'unit' ? $unit : null,
            'employee_id' => $targetType === 'employee' ? $employeeId : null,
        ];

        $db->transBegin();
        try {
            $date = new DateTimeImmutable($start);
            $last = new DateTimeImmutable($end);
            while ($date <= $last) {
                $eventDate = $date->format('Y-m-d');
                $duplicate = $db->table('work_calendar_events')
                    ->where('event_date', $eventDate)
                    ->where('target_type', $targetType);
                if ($targetType === 'unit') {
                    $duplicate->where('unit_code', $unit);
                } elseif ($targetType === 'employee') {
                    $duplicate->where('employee_id', $employeeId);
                }
                if ($duplicate->countAllResults() > 0) {
                    throw new \RuntimeException('Sudah ada pengaturan kalender untuk target pada tanggal ' . $date->format('d-m-Y') . '. Hapus atau periksa pengaturan yang lama.');
                }

                $db->table('work_calendar_events')->insert($target + [
                    'event_date' => $eventDate,
                    'event_type' => $eventType,
                    'title' => $title,
                    'late_after' => $eventType === 'holiday' ? null : $lateAfter,
                    'note' => $note !== '' ? $note : null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $date = $date->modify('+1 day');
            }
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Pengaturan kalender gagal disimpan.');
            }
            $db->transCommit();
            return redirect()->to('/kalender-kerja')->with('success', 'Pengaturan kalender berhasil disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function delete(int $id)
    {
        $db = db_connect();
        $db->table('work_calendar_events')->where('id', $id)->delete();
        return redirect()->to('/kalender-kerja')->with('success', 'Pengaturan kalender dihapus.');
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return $value . ':59';
        }
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value)) {
            return $value;
        }
        return null;
    }
}
