<?php

namespace App\Controllers;

use App\Services\AttendanceRecapService;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Recap extends BaseController
{
    private const ALLOWED_UNITS = ['KBIT', 'RAIT', 'SDIT', 'SMPIT', 'YAYASAN'];

    public function index()
    {
        [$startDate, $endDate] = $this->getDateRange();
        $unit = $this->getUnit();
        $data = (new AttendanceRecapService())->build($startDate, $endDate, $unit ?: null);

        return view('recap/index', [
            'title' => 'Rekap Absensi',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'unit' => $unit,
            'data' => $data,
        ]);
    }

    public function export()
    {
        $startDate = (string) $this->request->getGet('start_date');
        $endDate = (string) $this->request->getGet('end_date');
        if (! $this->isValidDate($startDate) || ! $this->isValidDate($endDate) || $endDate < $startDate) {
            return redirect()->to('/rekap')->with('error', 'Rentang tanggal rekap tidak valid.');
        }

        $unit = $this->getUnit();
        $data = (new AttendanceRecapService())->build($startDate, $endDate, $unit ?: null);

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Rekap Absensi');
        $sheet->setCellValue('A1', 'Periode: ' . $startDate . ' s.d. ' . $endDate);
        $sheet->fromArray(['Tanggal', 'Kode Pegawai', 'Nama Pegawai', 'Unit', 'Jam Masuk', 'Jam Pulang', 'Jumlah Scan', 'Ambang Terlambat', 'Terlambat (menit)'], null, 'A3');
        $row = 4;
        foreach ($data['rows'] as $item) {
            $sheet->fromArray([
                $item['date'], $item['employee_code'], $item['full_name'], $item['unit_code'],
                $item['arrival'], $item['departure'], $item['scan_count'], $item['late_after'], $item['late_minutes'],
            ], null, 'A' . $row++);
        }
        foreach (range('A', 'I') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A4');
        $sheet->getStyle('A3:I3')->getFont()->setBold(true);
        $filename = 'rekap-absensi-' . $startDate . '-sampai-' . $endDate . ($unit ? '-' . strtolower($unit) : '-semua-unit') . '.xlsx';
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

    private function getDateRange(): array
    {
        $defaultStart = date('Y-m-01');
        $defaultEnd = date('Y-m-t');
        $startDate = (string) ($this->request->getGet('start_date') ?? $defaultStart);
        $endDate = (string) ($this->request->getGet('end_date') ?? $defaultEnd);

        if (! $this->isValidDate($startDate) || ! $this->isValidDate($endDate) || $endDate < $startDate) {
            return [$defaultStart, $defaultEnd];
        }

        return [$startDate, $endDate];
    }

    private function getUnit(): string
    {
        $unit = strtoupper(trim((string) $this->request->getGet('unit')));
        return in_array($unit, self::ALLOWED_UNITS, true) ? $unit : '';
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
