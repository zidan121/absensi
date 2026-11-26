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


    /**
     * Export the additional two-sheet employee recap format.
     * The original detailed export remains available through export().
     */
    public function exportEmployeeSummary()
    {
        @set_time_limit(300);
        $startDate = (string) $this->request->getGet('start_date');
        $endDate = (string) $this->request->getGet('end_date');
        if (! $this->isValidDate($startDate) || ! $this->isValidDate($endDate) || $endDate < $startDate) {
            return redirect()->to('/rekap')->with('error', 'Rentang tanggal rekap tidak valid.');
        }

        $unit = $this->getUnit();
        $data = (new AttendanceRecapService())->build($startDate, $endDate, $unit ?: null);
        $db = db_connect();

        // Daftar pegawai mengikuti unit yang dipilih, termasuk pegawai tanpa scan.
        $employeeQuery = $db->table('employees')
            ->select('id, employee_code, full_name, position, unit_code')
            ->orderBy('full_name', 'ASC');
        if ($unit !== '') {
            $employeeQuery->where('unit_code', $unit);
        }
        $employees = $employeeQuery->get()->getResultArray();

        // Urutan jabatan hanya memengaruhi susunan baris sheet REKAB.
        $positionOrders = $db->table('recap_position_orders')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('position_name', 'ASC')
            ->get()->getResultArray();
        $positionRank = [];
        foreach ($positionOrders as $index => $positionOrder) {
            $positionRank[mb_strtolower(trim($positionOrder['position_name']))] = $index;
        }
        usort($employees, static function ($a, $b) use ($positionRank) {
            $positionA = trim((string) ($a['position'] ?? ''));
            $positionB = trim((string) ($b['position'] ?? ''));
            $rankA = $positionRank[mb_strtolower($positionA)] ?? PHP_INT_MAX;
            $rankB = $positionRank[mb_strtolower($positionB)] ?? PHP_INT_MAX;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            $positionCompare = strcasecmp($positionA, $positionB);
            return $positionCompare !== 0
                ? $positionCompare
                : strcasecmp((string) $a['full_name'], (string) $b['full_name']);
        });

        // Ambil satu PIN yang paling baru/terakhir terdaftar untuk ditampilkan pada sheet DATA.
        // Identitas internal employee_id tetap dipakai sebagai kunci rumus agar nama ganda tidak tercampur.
        $mappingQuery = $db->table('employee_fingerprint_mappings')
            ->select('employee_id, fingerprint_pin')
            ->orderBy('id', 'ASC');
        $mappings = $mappingQuery->get()->getResultArray();
        $pinByEmployee = [];
        foreach ($mappings as $mapping) {
            $pinByEmployee[(int) $mapping['employee_id']] = (string) $mapping['fingerprint_pin'];
        }

        $book = new Spreadsheet();
        $summary = $book->getActiveSheet();
        $summary->setTitle('REKAB');
        $summary->mergeCells('A1:G1');
        $summary->mergeCells('A2:G2');
        $summary->setCellValue('A1', 'REKAB ABSEN ' . ($unit ?: 'SEMUA UNIT'));
        $summary->setCellValue('A2', date('d-m-Y', strtotime($startDate)) . ' s.d. ' . date('d-m-Y', strtotime($endDate)));
        $summary->fromArray(['NO', 'NAMA', 'NAMA di Fringer', 'JABATAN', 'KEDATANGAN', 'TERLAMBAT', 'TEPAT WAKTU'], null, 'A3');

        $summaryRow = 4;
        $number = 1;
        foreach ($employees as $employee) {
            $employeeId = (int) $employee['id'];
            $summary->fromArray([
                $number++,
                $employee['full_name'],
                // Skema saat ini belum menyimpan nama terpisah dari mesin fingerprint.
                $employee['full_name'],
                $employee['position'] ?? '',
            ], null, 'A' . $summaryRow);
            $summary->setCellValue('E' . $summaryRow, '=COUNTIFS(DATA!$I$3:$I$' . max(3, count($data['rows']) + 2) . ',$H' . $summaryRow . ')');
            $summary->setCellValue('F' . $summaryRow, '=COUNTIFS(DATA!$I$3:$I$' . max(3, count($data['rows']) + 2) . ',$H' . $summaryRow . ',DATA!$F$3:$F$' . max(3, count($data['rows']) + 2) . ',"Terlambat")');
            $summary->setCellValue('G' . $summaryRow, '=E' . $summaryRow . '-F' . $summaryRow);
            $summary->setCellValue('H' . $summaryRow, $employeeId);
            $summaryRow++;
        }
        $summaryLastRow = max(3, $summaryRow - 1);
        $summary->getColumnDimension('H')->setVisible(false);
        $summary->getStyle('A1:G' . $summaryLastRow)->getFont()->setName('Arial')->setSize(8);
        $summary->getStyle('A1:G3')->getFont()->setBold(true);
        $summary->getStyle('A1:G2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $summary->getStyle('A3:G' . $summaryLastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $summary->getStyle('A3:G' . $summaryLastRow)->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $summary->getStyle('A4:A' . $summaryLastRow)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $summary->getStyle('E4:G' . $summaryLastRow)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        foreach (['A' => 6, 'B' => 27, 'C' => 25, 'D' => 18, 'E' => 13, 'F' => 13, 'G' => 15] as $column => $width) {
            $summary->getColumnDimension($column)->setWidth($width);
        }
        $summary->getRowDimension(1)->setRowHeight(15);
        $summary->getRowDimension(2)->setRowHeight(15);
        $summary->getRowDimension(3)->setRowHeight(15);
        // Aktifkan filter dan sortir Excel untuk seluruh kolom rekap.
        // Header tabel berada di baris 3, data pegawai dimulai dari baris 4.
        $summary->setAutoFilter('A3:G' . $summaryLastRow);
        $summary->freezePane('A4');

        $detail = $book->createSheet();
        $detail->setTitle('DATA');
        $detail->setCellValue('A1', 'Pegawai');
        $detail->setCellValue('D1', 'Data scanlog');
        $detail->setCellValue('J1', 'MAX JAM MASUK');
        $detail->fromArray(['PIN', 'Nama', 'Jabatan', 'Tanggal', 'Scan 1', 'Status', 'Scan 2', 'Scan 3'], null, 'A2');
        $detail->setCellValue('J2', 'Mengikuti aturan jadwal NEURO');
        $detailRow = 3;
        foreach ($data['rows'] as $item) {
            $scans = $item['scans'] ?? [];
            $detail->fromArray([
                $item['fingerprint_pin'] ?? ($pinByEmployee[(int) $item['employee_id']] ?? ''),
                $item['full_name'],
                $item['position'] ?? '',
                date('d-m-Y', strtotime($item['date'])),
                $scans[0] ?? '',
                // Status merupakan hasil aturan jadwal/kalendar NEURO, bukan ambang tunggal Excel.
                $item['status'] ?? ((int) ($item['late_minutes'] ?? 0) > 0 ? 'Terlambat' : 'Tepat Waktu'),
                $scans[1] ?? '',
                $scans[2] ?? '',
                (int) $item['employee_id'],
            ], null, 'A' . $detailRow);
            $detailRow++;
        }
        $detailLastRow = max(2, $detailRow - 1);
        $detail->getColumnDimension('I')->setVisible(false);
        $detail->getDefaultRowDimension()->setRowHeight(12);
        $detail->getStyle('A1:H' . $detailLastRow)->getFont()->setName('Arial')->setSize(8);
        $detail->getStyle('A1:H2')->getFont()->setBold(true);
        $detail->getStyle('A1:H' . $detailLastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $detail->getStyle('A1:H2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $detail->getStyle('E3:E' . $detailLastRow)->getNumberFormat()->setFormatCode('hh:mm:ss');
        $detail->getStyle('G3:H' . $detailLastRow)->getNumberFormat()->setFormatCode('hh:mm:ss');
        $detail->getStyle('J1')->getFont()->setName('Calibri')->setSize(11);
        $detail->getStyle('J1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFFF00');
        $detail->getStyle('J1:J2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        foreach (['A' => 9.18, 'B' => 19.18, 'C' => 15, 'D' => 11.91, 'E' => 9.18, 'F' => 12, 'G' => 9.18, 'H' => 9.18, 'I' => 3, 'J' => 24] as $column => $width) {
            $detail->getColumnDimension($column)->setWidth($width);
        }
        $detail->freezePane('A3');
        $book->setActiveSheetIndex(0);

        $filename = 'rekap-pegawai-' . $startDate . '-sampai-' . $endDate . ($unit ? '-' . strtolower($unit) : '-semua-unit') . '.xlsx';
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
