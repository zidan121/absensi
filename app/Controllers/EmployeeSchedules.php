<?php

namespace App\Controllers;

use DateTimeImmutable;

class EmployeeSchedules extends BaseController
{
    private const DAYS = [1, 2, 3, 4, 5, 6, 7];

    public function index(int $employeeId)
    {
        $db = db_connect();
        $employee = $db->table('employees')->where('id', $employeeId)->get()->getRowArray();
        if (!$employee) {
            return redirect()->to('/pegawai')->with('error', 'Pegawai tidak ditemukan.');
        }

        $schedules = $db->table('employee_schedules')
            ->where('employee_id', $employeeId)
            ->orderBy('effective_from', 'DESC')
            ->get()->getResultArray();

        return view('employees/schedules', [
            'title' => 'Jadwal Individu Pegawai',
            'employee' => $employee,
            'schedules' => $schedules,
            'days' => [
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
                5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
            ],
        ]);
    }

    public function save(int $employeeId)
    {
        $db = db_connect();
        $employee = $db->table('employees')->where('id', $employeeId)->get()->getRowArray();
        if (!$employee) {
            return redirect()->to('/pegawai')->with('error', 'Pegawai tidak ditemukan.');
        }

        $from = trim((string) $this->request->getPost('effective_from'));
        $toInput = trim((string) $this->request->getPost('effective_to'));
        $to = $toInput === '' ? null : $toInput;
        $start = trim((string) $this->request->getPost('start_time'));
        $end = trim((string) $this->request->getPost('end_time'));
        $lateAfter = trim((string) $this->request->getPost('late_after'));
        $days = array_values(array_unique(array_map('intval', (array) $this->request->getPost('work_days'))));
        sort($days);

        if (!$this->validDate($from) || ($to !== null && !$this->validDate($to))) {
            return redirect()->back()->withInput()->with('error', 'Tanggal berlaku jadwal tidak valid.');
        }
        if ($to !== null && $to < $from) {
            return redirect()->back()->withInput()->with('error', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }
        if (!$this->validTime($start) || !$this->validTime($lateAfter) || ($end !== '' && !$this->validTime($end))) {
            return redirect()->back()->withInput()->with('error', 'Format jam harus HH:MM atau HH:MM:SS.');
        }
        if ($lateAfter < $start) {
            return redirect()->back()->withInput()->with('error', 'Batas tidak terlambat tidak boleh sebelum jam mulai kerja.');
        }
        if ($days === [] || array_diff($days, self::DAYS) !== []) {
            return redirect()->back()->withInput()->with('error', 'Pilih minimal satu hari kerja yang valid.');
        }

        $overlap = $db->table('employee_schedules')
            ->where('employee_id', $employeeId)
            ->where('effective_from <=', $to ?? '9999-12-31')
            ->groupStart()->where('effective_to >=', $from)->orWhere('effective_to', null)->groupEnd()
            ->countAllResults();
        if ($overlap > 0) {
            return redirect()->back()->withInput()->with('error', 'Periode jadwal bertabrakan dengan jadwal pegawai yang sudah ada.');
        }

        $now = date('Y-m-d H:i:s');
        $db->table('employee_schedules')->insert([
            'employee_id' => $employeeId,
            'effective_from' => $from,
            'effective_to' => $to,
            'start_time' => $start,
            'end_time' => $end !== '' ? $end : null,
            'late_after' => $lateAfter,
            'work_days' => implode(',', $days),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'Jadwal gagal disimpan.');
        }
        return redirect()->to('/pegawai/' . $employeeId . '/jadwal')->with('success', 'Jadwal individu berhasil disimpan.');
    }

    public function delete(int $employeeId, int $scheduleId)
    {
        $db = db_connect();
        $db->table('employee_schedules')
            ->where('id', $scheduleId)
            ->where('employee_id', $employeeId)
            ->delete();
        return redirect()->to('/pegawai/' . $employeeId . '/jadwal')->with('success', 'Jadwal individu berhasil dihapus.');
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validTime(string $value): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) === 1;
    }
}
