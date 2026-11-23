<?php

namespace App\Controllers;

use App\Services\AttendanceRecapService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Recap extends BaseController
{
    public function index()
    {
        $month = (string) ($this->request->getGet('month') ?? date('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        $unit = strtoupper(trim((string) $this->request->getGet('unit')));
        $allowed = ['KBIT', 'RAIT', 'SDIT', 'SMPIT', 'YAYASAN'];
        if (! in_array($unit, $allowed, true)) $unit = '';

        $data = (new AttendanceRecapService())->build($month, $unit ?: null);
        return view('recap/index', [
            'title' => 'Rekap Absensi',
            'month' => $month,
            'unit' => $unit,
            'data' => $data,
        ]);
    }

    public function export()
    {
        $month = (string) $this->request->getGet('month');
        $unit = strtoupper(trim((string) $this->request->getGet('unit')));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return redirect()->to('/rekap')->with('error', 'Bulan rekap tidak valid.');
        }
        $allowed = ['KBIT', 'RAIT', 'SDIT', 'SMPIT', 'YAYASAN'];
        if (! in_array($unit, $allowed, true)) $unit = '';
        $data = (new AttendanceRecapService())->build($month, $unit ?: null);

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Rekap Absensi');
        $sheet->fromArray(['Tanggal', 'Kode Pegawai', 'Nama Pegawai', 'Unit', 'Jam Masuk', 'Jam Pulang', 'Jumlah Scan', 'Ambang Terlambat', 'Terlambat (menit)'], null, 'A1');
        $row = 2;
        foreach ($data['rows'] as $item) {
            $sheet->fromArray([
                $item['date'], $item['employee_code'], $item['full_name'], $item['unit_code'],
                $item['arrival'], $item['departure'], $item['scan_count'], $item['late_after'], $item['late_minutes'],
            ], null, 'A' . $row++);
        }
        foreach (range('A', 'I') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $filename = 'rekap-absensi-' . $month . ($unit ? '-' . strtolower($unit) : '-semua-unit') . '.xlsx';
        $response = service('response');
        $response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->setHeader('Cache-Control', 'max-age=0');
        ob_start();
        (new Xlsx($book))->save('php://output');
        $output = ob_get_clean();
        $book->disconnectWorksheets();
        return $response->setBody($output);
    }
}
