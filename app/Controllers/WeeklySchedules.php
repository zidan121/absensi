<?php

namespace App\Controllers;

use DateTimeImmutable;

class WeeklySchedules extends BaseController
{
    private const UNITS = ['KBIT', 'RAIT', 'SDIT', 'SMPIT', 'YAYASAN'];
    private const DAYS = [1, 2, 3, 4, 5, 6, 7];

    public function index()
    {
        $db = db_connect();
        $rows = $db->table('unit_weekly_schedules')
            ->orderBy('unit_code', 'ASC')
            ->orderBy('effective_from', 'DESC')
            ->get()->getResultArray();
        return view('weekly_schedules/index', [
            'title' => 'Jadwal Hari Kerja',
            'units' => self::UNITS,
            'days' => self::DAYS,
            'rows' => $rows,
        ]);
    }

    public function store()
    {
        $unit = strtoupper(trim((string) $this->request->getPost('unit_code')));
        $from = (string) $this->request->getPost('effective_from');
        $toInput = trim((string) $this->request->getPost('effective_to'));
        $to = $toInput === '' ? null : $toInput;
        $days = array_values(array_unique(array_map('intval', (array) $this->request->getPost('work_days'))));
        sort($days);

        if (! in_array($unit, self::UNITS, true) || ! $this->validDate($from) || ($to !== null && ! $this->validDate($to))) {
            return redirect()->back()->withInput()->with('error', 'Unit atau tanggal tidak valid.');
        }
        if ($to !== null && $to < $from) {
            return redirect()->back()->withInput()->with('error', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }
        if ($days === [] || array_diff($days, self::DAYS) !== []) {
            return redirect()->back()->withInput()->with('error', 'Pilih minimal satu hari kerja yang valid.');
        }

        $db = db_connect();
        $overlap = $db->table('unit_weekly_schedules')
            ->where('unit_code', $unit)
            ->where('effective_from <=', $to ?? '9999-12-31')
            ->groupStart()->where('effective_to >=', $from)->orWhere('effective_to', null)->groupEnd()
            ->countAllResults();
        if ($overlap > 0) {
            return redirect()->back()->withInput()->with('error', 'Periode jadwal bertabrakan dengan jadwal unit yang sudah ada. Edit/hapus jadwal lama atau pilih tanggal yang tidak bertabrakan.');
        }

        $db->table('unit_weekly_schedules')->insert([
            'unit_code' => $unit,
            'effective_from' => $from,
            'effective_to' => $to,
            'work_days' => implode(',', $days),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return redirect()->to('/jadwal-hari-kerja')->with('success', 'Jadwal hari kerja berhasil disimpan.');
    }

    public function delete(int $id)
    {
        db_connect()->table('unit_weekly_schedules')->where('id', $id)->delete();
        return redirect()->to('/jadwal-hari-kerja')->with('success', 'Jadwal berhasil dihapus.');
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
