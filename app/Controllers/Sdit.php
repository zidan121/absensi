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
        if (! $file) {
            return redirect()->to('/sdit/impor')->with('error', 'File tidak diterima server. Periksa nama field unggahan bin_file.');
        }
        if ($file->getError() !== UPLOAD_ERR_OK || ! $file->isValid()) {
            return redirect()->to('/sdit/impor')->with('error', 'Unggahan BIN gagal: ' . $file->getErrorString() . ' (kode ' . $file->getError() . '). Periksa batas upload_max_filesize dan post_max_size di PHP.');
        }
        $clientExtension = strtolower($file->getClientExtension());
        if ($clientExtension !== 'bin') {
            return redirect()->to('/sdit/impor')->with('error', 'Format file harus .bin. Ekstensi yang diterima server: .' . $clientExtension);
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
        @set_time_limit(180);
        $startDate = (string) $this->request->getGet('start_date');
        $endDate = (string) $this->request->getGet('end_date');
        $hasSearched = $this->request->getGet('show') === '1';
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startDate)) $startDate = date('Y-m-01');
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endDate)) $endDate = date('Y-m-d');
        if ($endDate < $startDate) { [$startDate, $endDate] = [$endDate, $startDate]; }

        $db = db_connect();
        $employees = [];
        $summary = ['attendance_days'=>0, 'late_days'=>0, 'one_scan_days'=>0, 'unmatched_identities'=>0, 'employees_with_scans'=>0, 'total_scans'=>0];
        $unmatched = [];
        if ($hasSearched) {
            $endExclusive = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';
            $logs = $db->table('sdit_attendance_logs')
                ->select('pin, scanned_at')
                ->where('scanned_at >=', $startDate . ' 00:00:00')
                ->where('scanned_at <', $endExclusive)
                ->orderBy('scanned_at', 'ASC')->get()->getResultArray();
            $pins = array_values(array_unique(array_column($logs, 'pin')));
            $masters = $pins ? $db->table('sdit_employees')->whereIn('pin', $pins)->get()->getResultArray() : [];
            $masterMap = [];
            foreach ($masters as $master) $masterMap[(string)$master['pin']] = $master;

            $groups = [];
            foreach ($logs as $log) {
                $pin = (string)$log['pin'];
                $date = substr($log['scanned_at'], 0, 10);
                $groups[$pin.'|'.$date]['pin'] = $pin;
                $groups[$pin.'|'.$date]['date'] = $date;
                $groups[$pin.'|'.$date]['scans'][] = $log['scanned_at'];
            }

            foreach ($groups as $group) {
                $pin = $group['pin'];
                $employee = $masterMap[$pin] ?? null;
                $scans = array_values(array_unique($group['scans'], SORT_STRING));
                sort($scans);
                $scanTimes = array_map(static fn($v) => substr($v,11,8), $scans);
                $date = $group['date'];
                $weekday = (int)(new \DateTimeImmutable($date))->format('N');

                $calendar = $db->table('sdit_work_calendar')->where('work_date',$date)->get()->getRowArray();
                $individual = $employee ? $db->table('sdit_employee_schedules')
                    ->where('pin',$pin)->where('day_of_week',$weekday)
                    ->groupStart()->where('effective_from <=',$date)->orWhere('effective_from',null)->groupEnd()
                    ->groupStart()->where('effective_until >=',$date)->orWhere('effective_until',null)->groupEnd()
                    ->orderBy('effective_from','DESC')->get(1)->getRowArray() : null;
                $weekly = $db->table('sdit_work_schedules')->where('schedule_name','Jadwal SDIT')
                    ->where('day_of_week',$weekday)->get(1)->getRowArray();
                $schedule = $individual ?: $weekly;
                $isHoliday = ($calendar['day_type'] ?? '') === 'holiday';
                $isWorkday = $calendar
                    ? !$isHoliday
                    : (bool)($schedule['is_workday'] ?? true);
                $lateAfter = $schedule['late_after_minutes'] ?? null;
                $startTime = $schedule['start_time'] ?? null;
                $late = false;
                $cutoff = '';
                $status = 'Jadwal Belum Diatur';
                if ($isHoliday) $status = 'Hari Libur (ada scan)';
                elseif (!$isWorkday) $status = 'Libur Mingguan (ada scan)';
                elseif (!empty($calendar['allow_afternoon_entry'])) $status = 'Izin Masuk Siang';
                elseif ($startTime !== null && $lateAfter !== null) {
                    $cutoff = date('H:i:s', strtotime($startTime . ' +' . (int)$lateAfter . ' minutes'));
                    $late = $scanTimes[0] > $cutoff;
                    $status = $late ? 'Terlambat' : 'Tepat Waktu';
                }

                if (!$employee) {
                    if (!isset($unmatched[$pin])) $unmatched[$pin] = ['pin'=>$pin,'scan_count'=>0,'first_scan'=>$scans[0],'last_scan'=>$scans[count($scans)-1]];
                    $unmatched[$pin]['scan_count'] += count($scans);
                    $unmatched[$pin]['first_scan'] = min($unmatched[$pin]['first_scan'],$scans[0]);
                    $unmatched[$pin]['last_scan'] = max($unmatched[$pin]['last_scan'],$scans[count($scans)-1]);
                }
                $key = $employee ? $pin : 'UNKNOWN-'.$pin;
                if (!isset($employees[$key])) $employees[$key] = [
                    'pin'=>$pin,'employee_code'=>$employee['employee_code'] ?? $pin,
                    'full_name'=>$employee['full_name'] ?? 'Belum dipetakan',
                    'position'=>$employee['position'] ?? '', 'days'=>0,'total_scans'=>0,'details'=>[]
                ];
                $employees[$key]['days']++;
                $employees[$key]['total_scans'] += count($scans);
                $employees[$key]['details'][] = [
                    'work_date'=>$date,'first_scan'=>$scanTimes[0],
                    'last_scan'=>count($scanTimes)>1 ? $scanTimes[count($scanTimes)-1] : '',
                    'scan_count'=>count($scanTimes),'scans'=>$scanTimes,
                    'status'=>$status,'late'=>$late,'is_counted'=>!$isHoliday && $isWorkday,
                    'late_after'=>$startTime !== null && $lateAfter !== null ? $cutoff : '',
                    'calendar'=>$calendar['description'] ?? '',
                ];
                if (!$isHoliday && $isWorkday) {
                    $summary['attendance_days']++;
                    if ($late) $summary['late_days']++;
                    if (count($scans)===1) $summary['one_scan_days']++;
                }
                $summary['total_scans'] += count($scans);
            }
            uasort($employees, static fn($a,$b) => strnatcasecmp($a['employee_code'],$b['employee_code']));
            $summary['employees_with_scans'] = count(array_filter($employees,static fn($e)=>$e['full_name']!=='Belum dipetakan'));
            $summary['unmatched_identities'] = count($unmatched);
        }
        $maxScans = 1;
        foreach ($employees as $employee) foreach ($employee['details'] as $detail) $maxScans=max($maxScans,count($detail['scans']));
        return view('sdit/recap', [
            'title'=>'Rekap Absensi SDIT','startDate'=>$startDate,'endDate'=>$endDate,
            'hasSearched'=>$hasSearched,'employees'=>array_values($employees),'summary'=>$summary,
            'unmatched'=>array_values($unmatched),'maxScans'=>$maxScans,
        ]);
    }

    public function exportRecap()
    {
        @set_time_limit(300);
        $startDate=(string)$this->request->getGet('start_date');
        $endDate=(string)$this->request->getGet('end_date');
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$startDate) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$endDate) || $endDate<$startDate) {
            return redirect()->to('/sdit/rekap')->with('error','Rentang tanggal rekap SDIT tidak valid.');
        }
        $endExclusive=date('Y-m-d',strtotime($endDate.' +1 day')).' 00:00:00';
        $db=db_connect();
        $logs=$db->table('sdit_attendance_logs')->select('pin,scanned_at')
            ->where('scanned_at >=',$startDate.' 00:00:00')->where('scanned_at <',$endExclusive)
            ->orderBy('pin','ASC')->orderBy('scanned_at','ASC')->get()->getResultArray();
        $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet=$book->getActiveSheet(); $sheet->setTitle('Detail SDIT');
        $sheet->fromArray(['PIN','Nama','Jabatan','Tanggal','Scan 1','Status','Scan 2','Scan 3','Jumlah Scan'],null,'A1');
        $pins=array_values(array_unique(array_column($logs,'pin')));
        $masters=$pins?$db->table('sdit_employees')->whereIn('pin',$pins)->get()->getResultArray():[];
        $map=[]; foreach($masters as $m)$map[(string)$m['pin']]=$m;
        $groups=[]; foreach($logs as $log){$key=$log['pin'].'|'.substr($log['scanned_at'],0,10);$groups[$key][]=$log['scanned_at'];}
        $row=2;
        foreach($groups as $key=>$times){
            [$pin,$date]=explode('|',$key,2); $times=array_values(array_unique($times)); sort($times);
            $employee=$map[$pin]??[];
            $sheet->fromArray([$pin,$employee['full_name']??'Belum dipetakan',$employee['position']??'',date('d-m-Y',strtotime($date)),
                substr($times[0],11,5),'Periksa di rekap',isset($times[1])?substr($times[1],11,5):'',isset($times[2])?substr($times[2],11,5):'',count($times)],null,'A'.$row++);
        }
        $sheet->getStyle('A1:I1')->getFont()->setBold(true); foreach(range('A','I') as $col)$sheet->getColumnDimension($col)->setAutoSize(true);
        $sheet->freezePane('A2'); $sheet->setAutoFilter('A1:I'.max(1,$row-1));
        $writer=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);
        ob_start(); $writer->save('php://output'); $output=ob_get_clean(); $book->disconnectWorksheets();
        return $this->response->setHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition','attachment; filename="Rekap-Detail-SDIT-'.$startDate.'-'.$endDate.'.xlsx"')
            ->setBody($output);
    }

    public function exportEmployeeSummary()
    {
        @set_time_limit(300);
        $startDate=(string)$this->request->getGet('start_date');
        $endDate=(string)$this->request->getGet('end_date');
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$startDate) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$endDate) || $endDate<$startDate) {
            return redirect()->to('/sdit/rekap')->with('error','Rentang tanggal rekap SDIT tidak valid.');
        }
        $db=db_connect();
        $employees=$db->table('sdit_employees')->where('is_active',1)->orderBy('employee_code','ASC')->get()->getResultArray();
        $endExclusive=date('Y-m-d',strtotime($endDate.' +1 day')).' 00:00:00';
        $logs=$db->table('sdit_attendance_logs')->select('pin,scanned_at')->where('scanned_at >=',$startDate.' 00:00:00')->where('scanned_at <',$endExclusive)->orderBy('scanned_at','ASC')->get()->getResultArray();
        $logsByPin=[]; foreach($logs as $log)$logsByPin[(string)$log['pin']][]=$log['scanned_at'];
        $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $summary=$book->getActiveSheet(); $summary->setTitle('REKAB');
        $summary->mergeCells('A1:G1'); $summary->mergeCells('A2:G2');
        $summary->setCellValue('A1','REKAB ABSEN SDIT'); $summary->setCellValue('A2',date('d-m-Y',strtotime($startDate)).' s.d. '.date('d-m-Y',strtotime($endDate)));
        $summary->fromArray(['NO','NAMA','NAMA di Fingerprint','JABATAN','KEDATANGAN','TERLAMBAT','TEPAT WAKTU'],null,'A3');
        $row=4; $no=1;
        foreach($employees as $employee){
            $pin=(string)$employee['pin']; $dates=[];
            foreach($logsByPin[$pin]??[] as $scan)$dates[substr($scan,0,10)][]= $scan;
            $present=0;$late=0;
            foreach($dates as $date=>$scans){sort($scans);$weekday=(int)(new \DateTimeImmutable($date))->format('N');
                $rule=$db->table('sdit_employee_schedules')->where('pin',$pin)->where('day_of_week',$weekday)->groupStart()->where('effective_from <=',$date)->orWhere('effective_from',null)->groupEnd()->groupStart()->where('effective_until >=',$date)->orWhere('effective_until',null)->groupEnd()->orderBy('effective_from','DESC')->get(1)->getRowArray();
                $rule=$rule?:$db->table('sdit_work_schedules')->where('schedule_name','Jadwal SDIT')->where('day_of_week',$weekday)->get(1)->getRowArray();
                $calendar=$db->table('sdit_work_calendar')->where('work_date',$date)->get(1)->getRowArray();
                $isWorkday=$calendar ? ($calendar['day_type']!=='holiday') : (bool)($rule['is_workday']??true);
                if(!$isWorkday) continue;
                $present++;
                if($rule && $rule['start_time']){$cutoff=date('H:i:s',strtotime($rule['start_time'].' +'.(int)$rule['late_after_minutes'].' minutes'));if(substr($scans[0],11,8)>$cutoff)$late++;}
            }
            $summary->fromArray([$no++,$employee['full_name'],$employee['full_name'],$employee['position']??'',$present,$late,$present-$late],null,'A'.$row++);
        }
        $summary->getStyle('A3:G3')->getFont()->setBold(true); foreach(range('A','G') as $col)$summary->getColumnDimension($col)->setAutoSize(true);
        $dataSheet=$book->createSheet();$dataSheet->setTitle('DATA');
        $dataSheet->fromArray(['PIN','Nama','Jabatan','Tanggal','Scan 1','Scan 2','Scan 3','Jumlah Scan'],null,'A1');
        $row=2;
        foreach($logsByPin as $pin=>$scans){$dates=[];foreach($scans as $scan)$dates[substr($scan,0,10)][]=$scan;foreach($dates as $date=>$dayScans){sort($dayScans);$employeeName='Belum dipetakan';$position='';foreach($employees as $e)if((string)$e['pin']===$pin){$employeeName=$e['full_name'];$position=$e['position']??'';break;}$dataSheet->fromArray([$pin,$employeeName,$position,date('d-m-Y',strtotime($date)),substr($dayScans[0],11,5),isset($dayScans[1])?substr(end($dayScans),11,5):'',isset($dayScans[2])?substr($dayScans[2],11,5):'',count($dayScans)],null,'A'.$row++);}}
        $dataSheet->getStyle('A1:H1')->getFont()->setBold(true);foreach(range('A','H') as $col)$dataSheet->getColumnDimension($col)->setAutoSize(true);$dataSheet->freezePane('A2');$dataSheet->setAutoFilter('A1:H'.max(1,$row-1));
        $writer=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);ob_start();$writer->save('php://output');$output=ob_get_clean();$book->disconnectWorksheets();
        return $this->response->setHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition','attachment; filename="REKAB-SDIT-'.$startDate.'-'.$endDate.'.xlsx"')->setBody($output);
    }

    public function schedules()
    {
        $rows = db_connect()->table('sdit_work_schedules')->where('schedule_name','Jadwal SDIT')->orderBy('day_of_week','ASC')->get()->getResultArray();
        $map = [];
        foreach ($rows as $row) $map[(int)$row['day_of_week']] = $row;
        return view('sdit/schedules', ['title'=>'Jadwal Kerja SDIT','days'=>[
            1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'
        ],'schedules'=>$map]);
    }

    public function saveSchedules()
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $days = $this->request->getPost('days') ?? [];
        $db->transBegin();
        try {
            for ($day=1; $day<=7; $day++) {
                $item = $days[$day] ?? [];
                $workday = !empty($item['is_workday']) ? 1 : 0;
                $start = $item['start_time'] ?? '07:30';
                $end = $item['end_time'] ?? '16:00';
                if (!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/',$start) || !preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/',$end)) {
                    throw new \InvalidArgumentException('Format jam jadwal tidak valid pada hari '.$day.'.');
                }
                $payload = [
                    'schedule_name'=>'Jadwal SDIT','day_of_week'=>$day,
                    'start_time'=>$start.':00','end_time'=>$end.':00',
                    'late_after_minutes'=>max(0,(int)($item['late_after_minutes'] ?? 0)),
                    'is_workday'=>$workday,'updated_at'=>$now
                ];
                $exists = $db->table('sdit_work_schedules')->where('schedule_name','Jadwal SDIT')->where('day_of_week',$day)->get()->getRowArray();
                if ($exists) $db->table('sdit_work_schedules')->where('id',$exists['id'])->update($payload);
                else { $payload['created_at']=$now; $db->table('sdit_work_schedules')->insert($payload); }
            }
            if ($db->transStatus() === false) throw new \RuntimeException('Gagal menyimpan jadwal SDIT.');
            $db->transCommit();
            return redirect()->to('/sdit/jadwal')->with('success','Jadwal mingguan SDIT berhasil disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->to('/sdit/jadwal')->with('error',$e->getMessage());
        }
    }

    public function calendar()
    {
        $month = (string)$this->request->getGet('bulan');
        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/',$month)) $month = date('Y-m');
        $rows = db_connect()->table('sdit_work_calendar')
            ->where('work_date >=',$month.'-01')
            ->where('work_date <',date('Y-m-d',strtotime($month.'-01 +1 month')))
            ->orderBy('work_date','ASC')->get()->getResultArray();
        return view('sdit/calendar',['title'=>'Kalender Kerja SDIT','month'=>$month,'rows'=>$rows]);
    }

    public function saveCalendar()
    {
        $date = (string)$this->request->getPost('work_date');
        $type = (string)$this->request->getPost('day_type');
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date) || !in_array($type,['holiday','workday'],true)) {
            return redirect()->to('/sdit/kalender')->with('error','Tanggal atau jenis hari tidak valid.');
        }
        $db = db_connect();
        $payload = [
            'work_date'=>$date,'day_type'=>$type,
            'description'=>trim((string)$this->request->getPost('description')) ?: null,
            'allow_afternoon_entry'=>$this->request->getPost('allow_afternoon_entry') ? 1 : 0,
            'updated_at'=>date('Y-m-d H:i:s')
        ];
        $exists=$db->table('sdit_work_calendar')->where('work_date',$date)->get()->getRowArray();
        if($exists) $db->table('sdit_work_calendar')->where('id',$exists['id'])->update($payload);
        else { $payload['created_at']=date('Y-m-d H:i:s'); $db->table('sdit_work_calendar')->insert($payload); }
        return redirect()->to('/sdit/kalender?bulan='.substr($date,0,7))->with('success','Kalender kerja SDIT berhasil disimpan.');
    }

    public function deleteCalendar(int $id)
    {
        db_connect()->table('sdit_work_calendar')->where('id',$id)->delete();
        return redirect()->back()->with('success','Pengecualian kalender SDIT dihapus.');
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
