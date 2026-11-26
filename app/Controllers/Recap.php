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
        $hasSearched = $this->request->getGet('show') === '1';
        $data = $hasSearched
            ? (new AttendanceRecapService())->build($startDate, $endDate, $unit ?: null)
            : [
                'rows' => [],
                'unmatched' => [],
                'summary' => [
                    'attendance_days' => 0,
                    'late_days' => 0,
                    'one_scan_days' => 0,
                    'unmatched_identities' => 0,
                ],
            ];

        return view('recap/index', [
            'title' => 'Rekap Absensi',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'unit' => $unit,
            'data' => $data,
            'hasSearched' => $hasSearched,
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
        $sheet->setTitle('DATA');

        // Susunan mengikuti template Excel contoh: kelompok Pegawai dan Data scanlog.
        $sheet->setCellValue('A1', 'Pegawai');
        $sheet->setCellValue('D1', 'Data scanlog');
        $sheet->setCellValue('J1', 'MAX JAM MASUK');
        $sheet->fromArray(
            ['PIN', 'Nama', 'Jabatan', 'Tanggal', 'Scan 1', 'Status', 'Scan 2', 'Scan 3'],
            null,
            'A2'
        );

        $row = 3;
        foreach ($data['rows'] as $item) {
            $scans = $item['scans'] ?? [];
            $values = [
                $item['fingerprint_pin'] ?? '',
                $item['full_name'],
                $item['position'] ?? '',
                date('d-m-Y', strtotime($item['date'])),
                $scans[0] ?? '',
                $item['status'] ?? ((int) ($item['late_minutes'] ?? 0) > 0
                    ? 'Terlambat'
                    : 'Tepat Waktu'),
                $scans[1] ?? '',
                $scans[2] ?? '',
            ];
            $sheet->fromArray($values, null, 'A' . $row++);
        }

        $lastRow = max(2, $row - 1);
        $sheet->getDefaultRowDimension()->setRowHeight(12);
        $sheet->getStyle('A1:H' . $lastRow)->getFont()->setName('Arial')->setSize(8);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('D1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H' . $lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('A1:H2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A3:E' . $lastRow)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('F3:F' . $lastRow)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:H2')->getAlignment()->setWrapText(true);
        $sheet->getStyle('J1')->getFont()->setName('Calibri')->setSize(11);
        $sheet->getStyle('J1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle('J1:J2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(9.18);
        $sheet->getColumnDimension('B')->setWidth(19.18);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(11.91);
        $sheet->getColumnDimension('E')->setWidth(9.18);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(9.18);
        $sheet->getColumnDimension('H')->setWidth(9.18);
        $sheet->getColumnDimension('I')->setWidth(3);
        $sheet->getColumnDimension('J')->setWidth(14.54);
        $sheet->freezePane('A3');
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
