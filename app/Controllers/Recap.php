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
        // Monthly and multi-month reports may contain many fingerprint scans.
        @set_time_limit(180);
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
        @set_time_limit(300);
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
        $maxScans = 1;
        foreach ($data['rows'] as $item) {
            $maxScans = max($maxScans, count($item['scans'] ?? []));
        }
        $headers = ['PIN', 'Nama', 'Jabatan', 'Tanggal'];
        for ($i = 1; $i <= $maxScans; $i++) {
            $headers[] = 'Scan ' . $i;
            if ($i === 1) {
                $headers[] = 'Status';
            }
        }
        $sheet->fromArray($headers, null, 'A3');

        $row = 4;
        foreach ($data['rows'] as $item) {
            $values = [
                $item['fingerprint_pin'] ?? '',
                $item['full_name'],
                $item['position'] ?? '',
                date('d-m-Y', strtotime($item['date'])),
            ];
            $scans = $item['scans'] ?? [];
            for ($i = 0; $i < $maxScans; $i++) {
                $values[] = $scans[$i] ?? '-';
                if ($i === 0) {
                    $values[] = $item['status'] ?? ((int) $item['late_minutes'] > 0
                        ? 'Terlambat'
                        : 'Tepat Waktu');
                }
            }
            $sheet->fromArray($values, null, 'A' . $row++);
        }
        $lastColumn = $sheet->getHighestColumn();
        foreach (range('A', $lastColumn) as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A4');
        $sheet->getStyle('A3:' . $lastColumn . '3')->getFont()->setBold(true);
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
