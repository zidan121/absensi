<?php

namespace App\Controllers;

use App\Services\SditBinDecoder;

class Sdit extends BaseController
{
    private const MACHINE_SERIAL = 'FS2010195000514';

    public function index()
    {
        return $this->page('Menu SDIT', 'Beranda');
    }

    public function imports()
    {
        return view('sdit/imports', ['title' => 'Impor Data SDIT']);
    }

    public function previewImport()
    {
        $file = $this->request->getFile('bin_file');
        if (! $file || ! $file->isValid() || strtolower($file->getExtension()) !== 'bin') {
            return redirect()->back()->with('error', 'Pilih file BIN SDIT yang valid.');
        }

        try {
            $raw = file_get_contents($file->getTempName());
            if ($raw === false) {
                throw new \RuntimeException('File BIN tidak dapat dibaca.');
            }

            $result = (new SditBinDecoder())->parse($raw);
            $directory = WRITEPATH . 'uploads/sdit';
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new \RuntimeException('Folder penyimpanan sementara SDIT gagal dibuat.');
            }
            $stagedName = bin2hex(random_bytes(16)) . '.bin';
            if (file_put_contents($directory . DIRECTORY_SEPARATOR . $stagedName, $raw, LOCK_EX) === false) {
                throw new \RuntimeException('File BIN gagal disimpan untuk proses impor.');
            }

            session()->set('sdit_bin_preview', [
                'original_name' => $file->getClientName(),
                'sha256' => hash('sha256', $raw),
                'size' => strlen($raw),
                'staged_name' => $stagedName,
                'summary' => [
                    'total' => $result['total'],
                    'valid' => $result['valid'],
                    'invalid' => $result['invalid'],
                    'unique_pins' => $result['unique_pins'],
                    'duplicates' => $result['duplicates'],
                ],
                'records' => $result['records'],
                'invalid_records' => $result['invalid_records'],
                'duplicate_records' => $result['duplicate_records'],
            ]);

            return view('sdit/preview', [
                'title' => 'Pratinjau BIN SDIT',
                'preview' => $result,
                'originalName' => $file->getClientName(),
                'fileSize' => strlen($raw),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'SDIT BIN preview failed: ' . $e->getMessage());
            return redirect()->to('/sdit/impor')->with('error', 'File BIN gagal dibaca: ' . $e->getMessage());
        }
    }

    public function showPreview()
    {
        $saved = session()->get('sdit_bin_preview');
        if (! $saved) {
            return redirect()->to('/sdit/impor')->with('error', 'Pratinjau sudah tidak tersedia. Silakan unggah ulang file BIN.');
        }
        return view('sdit/preview', [
            'title' => 'Pratinjau BIN SDIT',
            'preview' => [
                'total' => $saved['summary']['total'],
                'valid' => $saved['summary']['valid'],
                'invalid' => $saved['summary']['invalid'],
                'unique_pins' => $saved['summary']['unique_pins'],
                'duplicates' => $saved['summary']['duplicates'],
                'records' => $saved['records'],
                'invalid_records' => $saved['invalid_records'],
                'duplicate_records' => $saved['duplicate_records'],
            ],
            'originalName' => $saved['original_name'],
            'fileSize' => $saved['size'],
        ]);
    }

    public function processBinImport()
    {
        $saved = session()->get('sdit_bin_preview');
        if (! $saved || empty($saved['staged_name'])) {
            return redirect()->to('/sdit/impor')->with('error', 'Pratinjau BIN tidak ditemukan. Unggah ulang file.');
        }

        $db = db_connect();
        $directory = WRITEPATH . 'uploads/sdit';
        $path = $directory . DIRECTORY_SEPARATOR . basename($saved['staged_name']);
        if (! is_file($path)) {
            return redirect()->to('/sdit/impor')->with('error', 'File sementara BIN tidak ditemukan. Silakan unggah ulang.');
        }

        $existing = $db->table('sdit_import_batches')->where('file_sha256', $saved['sha256'])->where('source_format', 'BIN')->get()->getRowArray();
        if ($existing) {
            return redirect()->to('/sdit/impor')->with('error', 'File ini sudah pernah diimpor ke database SDIT.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return redirect()->to('/sdit/impor')->with('error', 'File BIN gagal dibaca ulang.');
        }

        try {
            $parsed = (new SditBinDecoder())->parse($raw);
            $now = date('Y-m-d H:i:s');
            $db->transBegin();

            $db->table('sdit_import_batches')->insert([
                'source_format' => 'BIN',
                'original_filename' => mb_substr($saved['original_name'], 0, 255),
                'file_sha256' => $saved['sha256'],
                'total_rows' => $parsed['total'],
                'valid_rows' => $parsed['valid'],
                'invalid_rows' => $parsed['invalid'],
                'duplicate_rows' => $parsed['duplicates'],
                'status' => 'processing',
                'created_at' => $now,
            ]);
            $batchId = (int) $db->insertID();

            $seen = [];
            $validRows = [];
            foreach ($parsed['records'] as $record) {
                $key = $record['pin'] . '|' . $record['scanned_at'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $validRows[] = [
                    'import_batch_id' => $batchId,
                    'machine_serial' => self::MACHINE_SERIAL,
                    'pin' => $record['pin'],
                    'scanned_at' => $record['scanned_at'],
                    'verify_mode' => 1,
                    'inout_mode' => 0,
                    'source_format' => 'BIN',
                    'source_row' => $record['record_no'],
                    'created_at' => $now,
                ];
            }

            foreach (array_chunk($validRows, 500) as $chunk) {
                $db->table('sdit_attendance_logs')->insertBatch($chunk);
            }

            $invalidRows = [];
            foreach ($parsed['invalid_records'] as $record) {
                $invalidRows[] = [
                    'import_batch_id' => $batchId,
                    'machine_serial' => self::MACHINE_SERIAL,
                    'pin' => $record['pin'],
                    'original_scanned_at' => $record['original_scanned_at'] ?? 'Tanggal/jam tidak terbaca',
                    'reason' => 'Waktu Mesin Tidak Valid',
                    'source_format' => 'BIN',
                    'source_row' => $record['record_no'],
                    'created_at' => $now,
                ];
            }
            foreach (array_chunk($invalidRows, 500) as $chunk) {
                $db->table('sdit_invalid_scans')->insertBatch($chunk);
            }

            $db->table('sdit_import_batches')->where('id', $batchId)->update(['status' => 'completed']);
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database menolak sebagian data impor.');
            }
            $db->transCommit();

            @unlink($path);
            session()->remove('sdit_bin_preview');
            return redirect()->to('/sdit/scan')->with('success', 'Impor BIN SDIT berhasil. ' . count($validRows) . ' scan valid dan ' . count($invalidRows) . ' scan waktu tidak valid disimpan. Duplikat di dalam file dilewati.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'SDIT BIN import failed: ' . $e->getMessage());
            return redirect()->to('/sdit/impor/preview')->with('error', 'Impor gagal dan transaksi dibatalkan: ' . $e->getMessage());
        }
    }

    public function previewSqlEmployees()
    {
        return $this->previewSql('employees');
    }

    public function previewSqlLogs()
    {
        return $this->previewSql('logs');
    }

    private function previewSql(string $kind)
    {
        $file = $this->request->getFile('sql_file');
        if (!$file || !$file->isValid() || strtolower($file->getExtension()) !== 'sql') {
            return redirect()->back()->with('error', 'Pilih file backup SQL yang valid.');
        }
        try {
            $raw = file_get_contents($file->getTempName());
            if ($raw === false) throw new \RuntimeException('File SQL tidak dapat dibaca.');
            $parser = new \App\Services\SditSqlBackupParser();
            $parsed = $kind === 'employees' ? $parser->parseEmployees($raw) : $parser->parseLogs($raw);
            $dir = WRITEPATH . 'uploads/sdit';
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new \RuntimeException('Folder sementara SDIT gagal dibuat.');
            $staged = bin2hex(random_bytes(16)) . '.sql';
            if (file_put_contents($dir . DIRECTORY_SEPARATOR . $staged, $raw, LOCK_EX) === false) throw new \RuntimeException('File gagal disimpan sementara.');
            $key = $kind === 'employees' ? 'sdit_sql_preview' : 'sdit_sql_logs_preview';
            session()->set($key, [
                'original_name'=>$file->getClientName(), 'sha256'=>hash('sha256',$raw),
                'size'=>strlen($raw), 'staged_name'=>$staged, 'parsed'=>$parsed
            ]);
            return view($kind === 'employees' ? 'sdit/sql_preview' : 'sdit/sql_logs_preview', [
                'title'=>$kind === 'employees' ? 'Pratinjau Master Pegawai SDIT' : 'Pratinjau Riwayat Scan SDIT',
                'preview'=>$parsed, 'originalName'=>$file->getClientName(), 'fileSize'=>strlen($raw)
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'SDIT SQL preview failed: '.$e->getMessage());
            return redirect()->to('/sdit/impor')->with('error', 'Backup SQL gagal dibaca: '.$e->getMessage());
        }
    }

    public function processSqlEmployees()
    {
        return $this->processSql('employees');
    }

    public function processSqlLogs()
    {
        return $this->processSql('logs');
    }

    private function processSql(string $kind)
    {
        $key = $kind === 'employees' ? 'sdit_sql_preview' : 'sdit_sql_logs_preview';
        $saved = session()->get($key);
        if (!$saved || empty($saved['staged_name'])) return redirect()->to('/sdit/impor')->with('error', 'Pratinjau SQL tidak ditemukan. Unggah ulang file.');
        $path = WRITEPATH . 'uploads/sdit/' . basename($saved['staged_name']);
        if (!is_file($path)) return redirect()->to('/sdit/impor')->with('error', 'File sementara tidak ditemukan. Unggah ulang.');
        $db = db_connect();
        $format = $kind === 'employees' ? 'SQL_EMP' : 'SQL_LOG';
        if ($db->table('sdit_import_batches')->where('file_sha256',$saved['sha256'])->where('source_format',$format)->countAllResults() > 0) {
            return redirect()->to('/sdit/impor')->with('error', 'File backup ini sudah pernah diimpor.');
        }
        try {
            $raw = file_get_contents($path);
            if ($raw === false) throw new \RuntimeException('File SQL gagal dibaca ulang.');
            $parser = new \App\Services\SditSqlBackupParser();
            $parsed = $kind === 'employees' ? $parser->parseEmployees($raw) : $parser->parseLogs($raw);
            $db->transBegin();
            $now = date('Y-m-d H:i:s');
            $validCount = $kind === 'employees' ? count($parsed['records']) : count($parsed['records']);
            $invalidCount = $kind === 'employees' ? $parsed['invalid'] : count($parsed['invalid_records']);
            $db->table('sdit_import_batches')->insert([
                'source_format'=>$format, 'original_filename'=>mb_substr($saved['original_name'],0,255),
                'file_sha256'=>$saved['sha256'], 'total_rows'=>$parsed['total'],
                'valid_rows'=>$validCount, 'invalid_rows'=>$invalidCount,
                'duplicate_rows'=>0, 'status'=>'processing', 'created_at'=>$now
            ]);
            $batch = (int)$db->insertID();
            $duplicates = 0;
            if ($kind === 'employees') {
                $seen = [];
                foreach ($parsed['records'] as $employee) {
                    $pin = trim((string)$employee['fingerprint_pin']);
                    if (isset($seen[$pin])) { $duplicates++; continue; }
                    $seen[$pin] = true;
                    $row = [
                        'source_employee_id'=>$employee['source_employee_id'] ?: null,
                        'pin'=>$pin, 'employee_code'=>$employee['employee_code'] ?: $pin,
                        'full_name'=>$employee['full_name'], 'position'=>null,
                        'phone'=>$employee['phone'], 'division_1'=>$employee['source_division_1'] ?: null,
                        'division_2'=>$employee['source_division_2'] ?: null, 'division_3'=>$employee['source_division_3'] ?: null,
                        'employment_start'=>$employee['employment_start'], 'employment_end'=>$employee['employment_end'],
                        'is_active'=>$employee['is_active'], 'created_at'=>$now, 'updated_at'=>$now
                    ];
                    $exists = $db->table('sdit_employees')->where('pin',$pin)->countAllResults() > 0;
                    if ($exists) $db->table('sdit_employees')->where('pin',$pin)->update($row);
                    else $db->table('sdit_employees')->insert($row);
                }
            } else {
                $seen = [];
                foreach ($parsed['records'] as $record) {
                    $identity = $record['machine_serial'].'|'.$record['pin'].'|'.$record['scanned_at'];
                    if (isset($seen[$identity])) { $duplicates++; continue; }
                    $seen[$identity] = true;
                    $exists = $db->table('sdit_attendance_logs')->where('machine_serial',$record['machine_serial'])->where('pin',$record['pin'])->where('scanned_at',$record['scanned_at'])->countAllResults() > 0;
                    if ($exists) { $duplicates++; continue; }
                    $db->table('sdit_attendance_logs')->insert([
                        'import_batch_id'=>$batch, 'machine_serial'=>$record['machine_serial'],
                        'pin'=>$record['pin'], 'scanned_at'=>$record['scanned_at'],
                        'verify_mode'=>$record['verify_mode'], 'inout_mode'=>$record['inout_mode'],
                        'source_format'=>'SQL', 'source_row'=>$record['source_row'], 'created_at'=>$now
                    ]);
                }
                foreach ($parsed['invalid_records'] as $record) {
                    $db->table('sdit_invalid_scans')->insert([
                        'import_batch_id'=>$batch, 'machine_serial'=>self::MACHINE_SERIAL,
                        'pin'=>$record['pin'], 'original_scanned_at'=>$record['original_scanned_at'],
                        'reason'=>'Waktu SQL Tidak Valid', 'source_format'=>'SQL',
                        'source_row'=>$record['source_row'], 'created_at'=>$now
                    ]);
                }
            }
            $db->table('sdit_import_batches')->where('id',$batch)->update([
                'valid_rows'=>$validCount-$duplicates, 'duplicate_rows'=>$duplicates, 'status'=>'completed'
            ]);
            if ($db->transStatus() === false) throw new \RuntimeException('Database menolak sebagian data impor.');
            $db->transCommit();
            @unlink($path);
            session()->remove($key);
            return redirect()->to($kind === 'employees' ? '/sdit/pemetaan-pin' : '/sdit/scan')
                ->with('success', $kind === 'employees' ? 'Master pegawai SDIT berhasil diimpor.' : 'Riwayat scan SDIT berhasil diimpor.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error','SDIT SQL import failed: '.$e->getMessage());
            return redirect()->to('/sdit/impor')->with('error','Impor SQL gagal: '.$e->getMessage());
        }
    }

    public function scans()
    {
        $db = db_connect();
        $builder = $db->table('sdit_attendance_logs l')
            ->select('l.*, e.full_name, e.employee_code')
            ->join('sdit_employees e', 'e.pin = l.pin', 'left')
            ->orderBy('l.scanned_at', 'DESC')
            ->orderBy('l.pin', 'ASC');
        $date = $this->request->getGet('tanggal');
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $builder->where('DATE(l.scanned_at)', $date);
        }
        $pin = trim((string) $this->request->getGet('pin'));
        if ($pin !== '') {
            $builder->where('l.pin', $pin);
        }
        return view('sdit/scans', [
            'title' => 'Detail Scan SDIT',
            'rows' => $builder->limit(1000)->get()->getResultArray(),
            'tanggal' => $date,
            'pin' => $pin,
            'total' => $db->table('sdit_attendance_logs')->countAllResults(),
            'invalidTotal' => $db->table('sdit_invalid_scans')->countAllResults(),
        ]);
    }

    public function recap()
    {
        $month = (string) $this->request->getGet('bulan');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        $start = $month . '-01 00:00:00';
        $end = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));
        $db = db_connect();
        $daily = $db->table('sdit_attendance_logs l')
            ->select("l.pin, DATE(l.scanned_at) AS work_date, MIN(TIME(l.scanned_at)) AS first_scan, MAX(TIME(l.scanned_at)) AS last_scan, COUNT(*) AS scan_count", false)
            ->where('l.scanned_at >=', $start)
            ->where('l.scanned_at <', $end)
            ->groupBy('l.pin, DATE(l.scanned_at)')
            ->orderBy('l.pin', 'ASC')
            ->orderBy('work_date', 'ASC')
            ->get()->getResultArray();

        $employees = [];
        foreach ($daily as $row) {
            $pin = (string) $row['pin'];
            if (!isset($employees[$pin])) {
                $employees[$pin] = [
                    'pin'=>$pin, 'employee_code'=>$pin, 'full_name'=>'Belum dipetakan',
                    'days'=>0, 'total_scans'=>0, 'details'=>[]
                ];
            }
            $employees[$pin]['days']++;
            $employees[$pin]['total_scans'] += (int) $row['scan_count'];
            $employees[$pin]['details'][] = $row;
        }
        if ($employees) {
            $pins = array_keys($employees);
            $masters = $db->table('sdit_employees')->whereIn('pin', $pins)->get()->getResultArray();
            foreach ($masters as $employee) {
                $pin = (string) $employee['pin'];
                if (isset($employees[$pin])) {
                    $employees[$pin]['employee_code'] = $employee['employee_code'] ?: $pin;
                    $employees[$pin]['full_name'] = $employee['full_name'];
                }
            }
        }
        uasort($employees, static function ($a, $b) {
            return strnatcasecmp($a['employee_code'], $b['employee_code']);
        });
        return view('sdit/recap', [
            'title'=>'Rekap Absensi SDIT', 'month'=>$month,
            'employees'=>array_values($employees),
            'totalDays'=>count($daily),
            'totalScans'=>array_sum(array_column($daily, 'scan_count')),
        ]);
    }


    public function exportRecap()
    {
        $month = (string) $this->request->getGet('bulan');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
        $start = $month . '-01 00:00:00';
        $end = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));
        $rows = db_connect()->table('sdit_attendance_logs')
            ->select("pin, DATE(scanned_at) AS work_date, MIN(TIME(scanned_at)) AS first_scan, MAX(TIME(scanned_at)) AS last_scan, COUNT(*) AS scan_count", false)
            ->where('scanned_at >=', $start)->where('scanned_at <', $end)
            ->groupBy('pin, DATE(scanned_at)')->orderBy('pin','ASC')->orderBy('work_date','ASC')
            ->get()->getResultArray();
        $pins = array_values(array_unique(array_column($rows, 'pin')));
        $masters = $pins ? db_connect()->table('sdit_employees')->whereIn('pin',$pins)->get()->getResultArray() : [];
        $map = [];
        foreach ($masters as $employee) $map[(string)$employee['pin']] = $employee;
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap SDIT');
        $sheet->fromArray(['No','Kode Pegawai','Nama Pegawai','PIN','Tanggal','Datang','Pulang','Jumlah Scan'],null,'A1');
        $line = 2;
        foreach ($rows as $i=>$row) {
            $employee = $map[(string)$row['pin']] ?? [];
            $sheet->fromArray([
                $i+1, $employee['employee_code'] ?? $row['pin'],
                $employee['full_name'] ?? 'Belum dipetakan', $row['pin'],
                $row['work_date'], substr($row['first_scan'],0,5),
                (int)$row['scan_count'] > 1 ? substr($row['last_scan'],0,5) : '',
                (int)$row['scan_count']
            ],null,'A'.$line++);
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        foreach (range('A','H') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H'.max(1,$line-1));
        $dir = WRITEPATH . 'exports';
        if (!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) {
            throw new \RuntimeException('Folder ekspor SDIT tidak dapat dibuat.');
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'rekap-sdit-'.$month.'-'.date('YmdHis').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return $this->response->download($path,null)->setFileName('Rekap-Absensi-SDIT-'.$month.'.xlsx');
    }

    public function timeCheck()
    {
        $rows = db_connect()->table('sdit_invalid_scans')->orderBy('id', 'DESC')->limit(500)->get()->getResultArray();
        return view('sdit/time_check', ['title' => 'Pemeriksaan Waktu Mesin SDIT', 'rows' => $rows]);
    }

    public function pinMapping()
    {
        $rows = db_connect()->table('sdit_employees')->orderBy('CAST(pin AS UNSIGNED)', 'ASC')->get()->getResultArray();
        return view('sdit/pin_mapping', ['title' => 'Pemetaan PIN SDIT', 'rows' => $rows]);
    }

    private function page(string $title, string $active)
    {
        return view('sdit/index', ['title' => $title, 'activeModule' => $active]);
    }
}
