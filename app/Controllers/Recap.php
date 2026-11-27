<?php

namespace App\Controllers;

use App\Services\AttendanceRecapService;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Recap extends BaseController
{
    private const ALLOWED_UNITS = ['KBIT', 'RAIT', 'SDIT', 'SMPIT', 'YAYASAN', 'LAZIS'];

    public function index()
    {
        // Monthly and multi-month reports may contain many fingerprint scans.
        @set_time_limit(180);
        [$startDate, $endDate] = $this->getDateRange();
        $units = $this->getUnits();
        $hasSearched = $this->request->getGet('show') === '1';
        $data = $hasSearched
            ? (new AttendanceRecapService())->build($startDate, $endDate, $units ?: null)
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
            'units' => $units,
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

        $units = $this->getUnits();
        $data = (new AttendanceRecapService())->build($startDate, $endDate, $units ?: null);

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
        $filename = 'rekap-absensi-' . $startDate . '-sampai-' . $endDate . ($units ? '-' . strtolower(implode('-', $units)) : '-semua-unit') . '.xlsx';
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

        $units = $this->getUnits();
        $data = (new AttendanceRecapService())->build($startDate, $endDate, $units ?: null);
        $db = db_connect();

        // Daftar pegawai mengikuti unit yang dipilih, termasuk pegawai tanpa scan.
        $employeeQuery = $db->table('employees')
            ->select('id, employee_code, full_name, position, unit_code')
            ->where('is_active', 1)
            ->orderBy('full_name', 'ASC');
        if ($units !== []) {
            $employeeQuery->whereIn('unit_code', $units);
        }
        $employees = $employeeQuery->get()->getResultArray();

        // Sheet DATA pada format REKAB hanya memuat scan pegawai aktif.
        $activeEmployeeIds = array_fill_keys(
            array_map(static fn ($employee) => (int) $employee['id'], $employees),
            true
        );
        $data['rows'] = array_values(array_filter(
            $data['rows'],
            static fn ($item) => isset($activeEmployeeIds[(int) $item['employee_id']])
        ));

        // Susunan manual pegawai per unit mengungguli urutan jabatan.
        // Unit yang belum memiliki susunan manual tetap memakai urutan jabatan sebelumnya.
        $positionOrders = $db->table('recap_position_orders')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('position_name', 'ASC')
            ->get()->getResultArray();
        $positionRank = [];
        foreach ($positionOrders as $index => $positionOrder) {
            $positionRank[mb_strtolower(trim($positionOrder['position_name']))] = $index;
        }
        $manualOrders = $db->table('employee_recap_orders')
            ->orderBy('unit_code', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->get()->getResultArray();
        $employeeOrder = [];
        $employeeDisplayNames = [];
        foreach ($manualOrders as $order) {
            $employeeOrder[$order['unit_code']][(int) $order['employee_id']] = (int) $order['sort_order'];
            $employeeDisplayNames[$order['unit_code']][(int) $order['employee_id']] = $order['display_name'];
        }
        $isKbitLazisCombined = in_array('KBIT', $units, true) && in_array('LAZIS', $units, true);
        $combinedOrder = $employeeOrder['KBIT_LAZIS'] ?? [];
        $combinedDisplayNames = [];
        foreach ($manualOrders as $order) {
            if ($order['unit_code'] === 'KBIT_LAZIS') {
                $combinedDisplayNames[(int) $order['employee_id']] = $order['display_name'];
            }
        }
        $defaultRecapNames = [
            'ari wuryaningsih' => 'Ari Wuryaningsih, SE',
            'yuli karina' => 'Yuli Karina, S.Pd',
            'winarti' => 'Winarti, S.Pd',
            'sri munarsih' => 'Sri Munarsih, S.Sos',
            'nobela' => 'Nobela Windatul Umah, S.Pd',
            'nikmatul janah' => 'Nikmatul Jannah',
            'nikmatul jannah' => 'Nikmatul Jannah',
            'yuli tri w se' => 'Yuli Tri W SE',
            'tri m spd' => 'Tri Mulyaningsih, SPd',
            'yuni w a' => 'Yuni Wulansari, AMa',
            'fitri dwi a' => 'Fitri Dwi Aryani, S.Pd',
            'sulastri' => 'Sulastri, S.Pd',
            'setiati spd' => 'Setiyati, SPdl',
            'musripah spd' => 'Musripah, S.Pd',
            'sri wijiasih spd' => 'Sri Wiji Asih, S.Pd',
            'anista dw' => 'Anista Diyah F, S.Psi',
            'karlina' => 'Karlina, S.Pd',
            'indah s' => 'Indah Setyaningrum, SPd',
            'kiki ayu m' => 'Kiki Ayu M',
            'faridhotun ulfa' => 'Faridhotun Ulfa',
            'darwanto' => 'Darwanto',
            'ariana' => 'Ariana Irawati, S.Pd',
            'deska sofiana' => 'Deska Sofiana',
            'fitri anggraeni' => 'Fitri Anggraeni, SPd',
            'ruchayati r sd' => 'Ruchayati R Sd',
            'ahmad tukul' => 'Ahmad Tukul SPd',
            'ari wahono' => 'Ari Wahono SPd',
            'endang r' => 'Endang R SPd',
            'eko s shum' => 'Eko S Shum',
            'tri widagdo' => 'Tri Widagdo Spd',
            'nita l' => 'Nita L Spd',
            'fathienahas' => 'FathienahAS SPd',
            'fathienah as' => 'FathienahAS SPd',
            'zainul arifin' => 'Zainul Arifin',
            'mujiono' => 'Mujiono',
            "imam safi'i" => "Imam Safi'i",
            'imam safi' => "Imam Safi'i",
            'nunung megawati' => 'Nunung Megawati',
            'm solikin' => 'M solikin',
            'nur indah nila khoiriyah' => 'Nur Indah Nila Khoiriyah',
            'laila yurin nabila' => 'Laila Yurin Nabila',
        ];
        $defaultRaitOrder = [
            'tri m spd' => 1, 'yuni w a' => 2, 'fitri dwi a' => 3,
            'sulastri' => 4, 'setiati spd' => 5, 'musripah spd' => 6,
            'sri wijiasih spd' => 7, 'anista dw' => 8, 'karlina' => 9,
            'indah s' => 10, 'kiki ayu m' => 11, 'faridhotun ulfa' => 12,
            'darwanto' => 13, 'ariana' => 14, 'deska sofiana' => 15,
        ];
        $isRaitOnly = $units === ['RAIT'];
        $isSmpOnly = $units === ['SMPIT'];
        $defaultSmpOrder = [
            'fitri anggraeni' => 1, 'ruchayati r sd' => 2, 'ahmad tukul' => 3,
            'ari wahono' => 4, 'endang r' => 5, 'eko s shum' => 6,
            'tri widagdo' => 7, 'nita l' => 8, 'fathienahas' => 9,
            'fathienah as' => 9, 'zainul arifin' => 10, 'mujiono' => 11,
            "imam safi'i" => 12, 'imam safi' => 12, 'nunung megawati' => 13,
            'm solikin' => 14, 'nur indah nila khoiriyah' => 15,
            'laila yurin nabila' => 16,
        ];
        $unitRank = [];
        foreach ($units as $index => $unitCode) {
            $unitRank[$unitCode] = $index;
        }
        usort($employees, static function ($a, $b) use ($positionRank, $employeeOrder, $unitRank, $isKbitLazisCombined, $combinedOrder, $isRaitOnly, $defaultRaitOrder, $isSmpOnly, $defaultSmpOrder) {
            $idA = (int) $a['id'];
            $idB = (int) $b['id'];
            if ($isKbitLazisCombined && $combinedOrder !== []) {
                $manualA = $combinedOrder[$idA] ?? null;
                $manualB = $combinedOrder[$idB] ?? null;
                if ($manualA !== null || $manualB !== null) {
                    if ($manualA === null) return 1;
                    if ($manualB === null) return -1;
                    if ($manualA !== $manualB) return $manualA <=> $manualB;
                }
            }
            if ($isRaitOnly) {
                $nameA = mb_strtolower(trim((string) $a['full_name']));
                $nameB = mb_strtolower(trim((string) $b['full_name']));
                $rankA = PHP_INT_MAX;
                $rankB = PHP_INT_MAX;
                foreach ($defaultRaitOrder as $needle => $rank) {
                    if (str_contains($nameA, $needle)) $rankA = $rank;
                    if (str_contains($nameB, $needle)) $rankB = $rank;
                }
                if ($rankA !== $rankB) return $rankA <=> $rankB;
            }
            if ($isSmpOnly) {
                $nameA = mb_strtolower(trim((string) $a['full_name']));
                $nameB = mb_strtolower(trim((string) $b['full_name']));
                $rankA = PHP_INT_MAX;
                $rankB = PHP_INT_MAX;
                foreach ($defaultSmpOrder as $needle => $rank) {
                    if (str_contains($nameA, $needle)) $rankA = $rank;
                    if (str_contains($nameB, $needle)) $rankB = $rank;
                }
                if ($rankA !== $rankB) return $rankA <=> $rankB;
            }
            $unitA = (string) $a['unit_code'];
            $unitB = (string) $b['unit_code'];
            $unitCompare = ($unitRank[$unitA] ?? PHP_INT_MAX) <=> ($unitRank[$unitB] ?? PHP_INT_MAX);
            if ($unitCompare !== 0) return $unitCompare;
            $manualA = $employeeOrder[$unitA][$idA] ?? null;
            $manualB = $employeeOrder[$unitB][$idB] ?? null;
            if ($manualA !== null || $manualB !== null) {
                if ($manualA === null) return 1;
                if ($manualB === null) return -1;
                if ($manualA !== $manualB) return $manualA <=> $manualB;
            }
            $positionA = trim((string) ($a['position'] ?? ''));
            $positionB = trim((string) ($b['position'] ?? ''));
            $rankA = $positionRank[mb_strtolower($positionA)] ?? PHP_INT_MAX;
            $rankB = $positionRank[mb_strtolower($positionB)] ?? PHP_INT_MAX;
            if ($rankA !== $rankB) return $rankA <=> $rankB;
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
        $summary->setCellValue('A1', 'REKAB ABSEN ' . ($units ? implode(' + ', $units) : 'SEMUA UNIT'));
        $summary->setCellValue('A2', date('d-m-Y', strtotime($startDate)) . ' s.d. ' . date('d-m-Y', strtotime($endDate)));
        $summary->fromArray(['NO', 'NAMA', 'NAMA di Fringer', 'JABATAN', 'KEDATANGAN', 'TERLAMBAT', 'TEPAT WAKTU'], null, 'A3');

        $summaryRow = 4;
        $number = 1;
        foreach ($employees as $employee) {
            $employeeId = (int) $employee['id'];
            $summary->fromArray([
                $number++,
                ($combinedDisplayNames[(int) $employee['id']]
                    ?? (($employeeDisplayNames[$employee['unit_code']][(int) $employee['id']] ?? null) !== null
                        && ($employeeDisplayNames[$employee['unit_code']][(int) $employee['id']] ?? null) !== $employee['full_name']
                            ? $employeeDisplayNames[$employee['unit_code']][(int) $employee['id']]
                            : ((($isKbitLazisCombined || $isRaitOnly || $isSmpOnly) ? ($defaultRecapNames[mb_strtolower(trim((string) $employee['full_name']))] ?? null) : null) ?? $employee['full_name']))),
                // Nama master NEURO tetap ditampilkan sebagai referensi di kolom NAMA di Fringer.
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

        $filename = 'rekap-pegawai-' . $startDate . '-sampai-' . $endDate . ($units ? '-' . strtolower(implode('-', $units)) : '-semua-unit') . '.xlsx';
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

    private function getUnits(): array
    {
        $input = $this->request->getGet('unit');
        $values = is_array($input) ? $input : [$input];
        $units = [];
        foreach ($values as $value) {
            $unit = strtoupper(trim((string) $value));
            if (in_array($unit, self::ALLOWED_UNITS, true) && ! in_array($unit, $units, true)) {
                $units[] = $unit;
            }
        }
        return $units;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
