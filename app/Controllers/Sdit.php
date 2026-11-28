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
        $existingBatch = $db->table('sdit_import_batches')
            ->where('file_sha256', $saved['sha256'])->where('source_format', $format)
            ->orderBy('id', 'DESC')->get(1)->getRowArray();
        $refreshEmployeeMaster = $kind === 'employees' && $existingBatch !== null;
        if ($existingBatch !== null && !$refreshEmployeeMaster) {
            return redirect()->to('/sdit/impor')->with('error', 'File backup log ini sudah pernah diimpor.');
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
            if ($refreshEmployeeMaster) {
                // Backup master yang sama boleh diproses ulang untuk memperbarui alias/nama
                // tampilan dari sumber Fingerspot tanpa membuat batch impor ganda.
                $batch = (int) $existingBatch['id'];
            } else {
                $db->table('sdit_import_batches')->insert([
                    'source_format'=>$format, 'original_filename'=>mb_substr($saved['original_name'],0,255),
                    'file_sha256'=>$saved['sha256'], 'total_rows'=>$parsed['total'],
                    'valid_rows'=>$validCount, 'invalid_rows'=>$invalidCount,
                    'duplicate_rows'=>0, 'status'=>'processing', 'created_at'=>$now
                ]);
                $batch = (int) $db->insertID();
            }
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
                        'full_name'=>$employee['full_name'], 'fingerprint_name'=>$employee['fingerprint_name'] ?? $employee['full_name'], 'position'=>null,
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
            if (!$refreshEmployeeMaster) {
                $db->table('sdit_import_batches')->where('id',$batch)->update([
                    'valid_rows'=>$validCount-$duplicates, 'duplicate_rows'=>$duplicates, 'status'=>'completed'
                ]);
            }
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

    public function employees()
    {
        $db = db_connect();
        $q = trim((string) $this->request->getGet('q'));
        $status = (string) $this->request->getGet('status');
        $builder = $db->table('sdit_employees')->orderBy('CAST(employee_code AS UNSIGNED)', 'ASC')->orderBy('employee_code', 'ASC');
        if ($q !== '') {
            $builder->groupStart()->like('full_name', $q)->orLike('employee_code', $q)->orLike('pin', $q)->orLike('fingerprint_name', $q)->groupEnd();
        }
        if ($status === 'active') $builder->where('is_active', 1);
        elseif ($status === 'inactive') $builder->where('is_active', 0);
        $rows = $builder->get()->getResultArray();
        return view('sdit/employees', [
            'title' => 'Master Pegawai SDIT', 'employees' => $rows, 'q' => $q, 'status' => $status,
            'activeCount' => $db->table('sdit_employees')->where('is_active', 1)->countAllResults(),
            'inactiveCount' => $db->table('sdit_employees')->where('is_active', 0)->countAllResults(),
        ]);
    }

    public function employeeCreate()
    {
        return view('sdit/employee_form', ['title' => 'Tambah Pegawai SDIT', 'employee' => null]);
    }

    public function employeeStore()
    {
        $data = $this->employeePayload();
        if ($data instanceof \CodeIgniter\HTTP\RedirectResponse) return $data;
        $db = db_connect();
        if ($db->table('sdit_employees')->where('pin', $data['pin'])->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'PIN tersebut sudah digunakan pegawai lain.');
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = $data['created_at'];
        $db->table('sdit_employees')->insert($data);
        return redirect()->to('/sdit/pegawai')->with('success', 'Pegawai SDIT berhasil ditambahkan.');
    }

    public function employeeEdit(int $id)
    {
        $employee = db_connect()->table('sdit_employees')->where('id', $id)->get()->getRowArray();
        if (!$employee) return redirect()->to('/sdit/pegawai')->with('error', 'Data pegawai tidak ditemukan.');
        return view('sdit/employee_form', ['title' => 'Edit Pegawai SDIT', 'employee' => $employee]);
    }

    public function employeeUpdate(int $id)
    {
        $data = $this->employeePayload();
        if ($data instanceof \CodeIgniter\HTTP\RedirectResponse) return $data;
        $db = db_connect();
        $employee = $db->table('sdit_employees')->where('id', $id)->get()->getRowArray();
        if (!$employee) return redirect()->to('/sdit/pegawai')->with('error', 'Data pegawai tidak ditemukan.');
        if ($db->table('sdit_employees')->where('pin', $data['pin'])->where('id !=', $id)->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'PIN tersebut sudah digunakan pegawai lain.');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        $db->table('sdit_employees')->where('id', $id)->update($data);
        return redirect()->to('/sdit/pegawai')->with('success', 'Data pegawai SDIT berhasil diperbarui.');
    }

    public function employeeStatus(int $id)
    {
        $db = db_connect();
        $employee = $db->table('sdit_employees')->where('id', $id)->get()->getRowArray();
        if (!$employee) return redirect()->to('/sdit/pegawai')->with('error', 'Data pegawai tidak ditemukan.');
        $db->table('sdit_employees')->where('id', $id)->update([
            'is_active' => (int) !$employee['is_active'], 'updated_at' => date('Y-m-d H:i:s')
        ]);
        return redirect()->to('/sdit/pegawai')->with('success', 'Status pegawai berhasil diubah. Riwayat scan tetap tersimpan.');
    }

    private function employeePayload()
    {
        $pin = trim((string) $this->request->getPost('pin'));
        $name = trim((string) $this->request->getPost('full_name'));
        $code = trim((string) $this->request->getPost('employee_code'));
        if ($pin === '' || $name === '') {
            return redirect()->back()->withInput()->with('error', 'PIN dan nama pegawai wajib diisi.');
        }
        return [
            'pin' => $pin, 'employee_code' => $code !== '' ? $code : $pin,
            'full_name' => $name,
            'fingerprint_name' => trim((string) $this->request->getPost('fingerprint_name')) ?: $name,
            'position' => trim((string) $this->request->getPost('position')) ?: null,
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'employment_start' => $this->request->getPost('employment_start') ?: null,
            'employment_end' => $this->request->getPost('employment_end') ?: null,
            'is_active' => $this->request->getPost('is_active') === '0' ? 0 : 1,
        ];
    }

    public function scans()
    {
        $db = db_connect();
        $startDate = trim((string) $this->request->getGet('start_date'));
        $endDate = trim((string) $this->request->getGet('end_date'));
        $pin = trim((string) $this->request->getGet('pin'));
        $q = trim((string) $this->request->getGet('q'));
        if ($startDate !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startDate)) $startDate = '';
        if ($endDate !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endDate)) $endDate = '';
        if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) [$startDate, $endDate] = [$endDate, $startDate];

        $builder = $db->table('sdit_attendance_logs')->orderBy('scanned_at', 'DESC')->orderBy('pin', 'ASC');
        if ($startDate !== '') $builder->where('scanned_at >=', $startDate . ' 00:00:00');
        if ($endDate !== '') $builder->where('scanned_at <', date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00');
        if ($pin !== '') $builder->where('pin', $pin);
        $logs = $builder->limit(2000)->get()->getResultArray();

        // Resolve employee identities in PHP to avoid collation conflicts between legacy PIN columns.
        $employeeRows = $db->table('sdit_employees')->get()->getResultArray();
        $employeeMap = [];
        foreach ($employeeRows as $employee) $employeeMap[(string) $employee['pin']] = $employee;
        $rows = [];
        foreach ($logs as $log) {
            $employee = $employeeMap[(string) $log['pin']] ?? [];
            $log['full_name'] = $employee['full_name'] ?? null;
            $log['employee_code'] = $employee['employee_code'] ?? null;
            $log['position'] = $employee['position'] ?? null;
            $log['fingerprint_name'] = $employee['fingerprint_name'] ?? null;
            if ($q !== '') {
                $haystack = implode(' ', [(string)$log['pin'], (string)($log['full_name'] ?? ''), (string)($log['employee_code'] ?? '')]);
                if (stripos($haystack, $q) === false) continue;
            }
            $rows[] = $log;
        }
        return view('sdit/scans', [
            'title' => 'Detail Scan SDIT', 'rows' => $rows,
            'startDate' => $startDate, 'endDate' => $endDate, 'pin' => $pin, 'q' => $q,
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
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = date('Y-m-d');
        if ($endDate < $startDate) [$startDate, $endDate] = [$endDate, $startDate];

        $db = db_connect();
        $employees = [];
        $summary = ['attendance_days'=>0,'late_days'=>0,'one_scan_days'=>0,'afternoon_entry_days'=>0,'holiday_scan_days'=>0,'unmatched_identities'=>0,'employees_with_scans'=>0,'total_scans'=>0];
        $unmatched = [];
        if ($hasSearched) {
            $endExclusive = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';
            // Aggregate in MySQL: one row per PIN/date instead of loading every raw scan.
            $dailyRows = $db->query(
                "SELECT pin, DATE(scanned_at) AS scan_date,
                        GROUP_CONCAT(TIME(scanned_at) ORDER BY scanned_at ASC SEPARATOR ',') AS scan_times,
                        COUNT(*) AS raw_scan_count
                 FROM sdit_attendance_logs
                 WHERE scanned_at >= ? AND scanned_at < ?
                 GROUP BY pin, DATE(scanned_at)
                 ORDER BY pin ASC, scan_date ASC",
                [$startDate . ' 00:00:00', $endExclusive]
            )->getResultArray();

            $pins = array_values(array_unique(array_column($dailyRows, 'pin')));
            $masters = $pins ? $db->table('sdit_employees')->whereIn('pin', $pins)->get()->getResultArray() : [];
            $masterMap = [];
            foreach ($masters as $master) $masterMap[(string)$master['pin']] = $master;

            $calendarRows = $db->table('sdit_work_calendar')
                ->where('work_date >=', $startDate)->where('work_date <=', $endDate)->get()->getResultArray();
            $calendarMap = [];
            foreach ($calendarRows as $row) $calendarMap[$row['work_date']] = $row;

            $weeklyRows = $db->table('sdit_work_schedules')->where('schedule_name','Jadwal SDIT')->get()->getResultArray();
            $weeklyMap = [];
            foreach ($weeklyRows as $row) $weeklyMap[(int)$row['day_of_week']] = $row;

            $individualRows = $pins
                ? $db->table('sdit_employee_schedules')->whereIn('pin', $pins)->get()->getResultArray()
                : [];
            $individualMap = [];
            foreach ($individualRows as $row) $individualMap[(string)$row['pin']][(int)$row['day_of_week']][] = $row;

            foreach ($dailyRows as $daily) {
                $pin = (string)$daily['pin'];
                $date = (string)$daily['scan_date'];
                $scans = array_values(array_filter(explode(',', (string)$daily['scan_times'])));
                $scans = array_values(array_unique($scans));
                sort($scans);
                if (!$scans) continue;
                $scanCount = count($scans);
                $weekday = (int)(new \DateTimeImmutable($date))->format('N');
                $calendar = $calendarMap[$date] ?? null;
                $employee = $masterMap[$pin] ?? null;

                $individual = null;
                foreach ($individualMap[$pin][$weekday] ?? [] as $candidate) {
                    $from = $candidate['effective_from'] ?: '0001-01-01';
                    $until = $candidate['effective_until'] ?: '9999-12-31';
                    if ($from <= $date && $date <= $until && ($individual === null || ($candidate['effective_from'] ?? '') > ($individual['effective_from'] ?? ''))) {
                        $individual = $candidate;
                    }
                }
                $schedule = $individual ?? ($weeklyMap[$weekday] ?? null);
                $isHoliday = ($calendar['day_type'] ?? '') === 'holiday';
                $isWorkday = $calendar !== null ? !$isHoliday : (bool)($schedule['is_workday'] ?? ($weekday <= 5));
                $startTime = $schedule['start_time'] ?? null;
                $lateAfter = $schedule['late_after_minutes'] ?? null;
                $late = false;
                $cutoff = '';
                if ($isHoliday) $status = 'Hari Libur (ada scan)';
                elseif (!$isWorkday) $status = 'Libur Mingguan (ada scan)';
                elseif (!empty($calendar['allow_afternoon_entry'])) $status = 'Izin Masuk Siang';
                elseif ($startTime !== null && $lateAfter !== null) {
                    $cutoff = date('H:i:s', strtotime($startTime . ' +' . (int)$lateAfter . ' minutes'));
                    $late = $scans[0] > $cutoff;
                    $status = $late ? 'Terlambat' : 'Tepat Waktu';
                } else $status = 'Jadwal Belum Diatur';

                if (!$employee) {
                    if (!isset($unmatched[$pin])) $unmatched[$pin] = ['pin'=>$pin,'scan_count'=>0,'first_scan'=>$date.' '.$scans[0],'last_scan'=>$date.' '.$scans[$scanCount-1]];
                    $unmatched[$pin]['scan_count'] += $scanCount;
                    $unmatched[$pin]['first_scan'] = min($unmatched[$pin]['first_scan'], $date.' '.$scans[0]);
                    $unmatched[$pin]['last_scan'] = max($unmatched[$pin]['last_scan'], $date.' '.$scans[$scanCount-1]);
                }
                $key = $employee ? $pin : 'UNKNOWN-'.$pin;
                if (!isset($employees[$key])) $employees[$key] = [
                    'pin'=>$pin,'employee_code'=>$employee['employee_code'] ?? $pin,
                    'full_name'=>$employee['full_name'] ?? 'Belum dipetakan',
                    'position'=>$employee['position'] ?? '', 'days'=>0,'total_scans'=>0,'details'=>[]
                ];
                $employees[$key]['days']++;
                $employees[$key]['total_scans'] += $scanCount;
                $employees[$key]['details'][] = [
                    'work_date'=>$date,'first_scan'=>$scans[0],
                    'last_scan'=>$scanCount>1 ? $scans[$scanCount-1] : '',
                    'scan_count'=>$scanCount,'scans'=>$scans,'status'=>$status,'late'=>$late,
                    'is_counted'=>!$isHoliday && $isWorkday,
                    'late_after'=>$cutoff,
                    'calendar'=>$calendar['description'] ?? '',
                ];

                if ($isHoliday || !$isWorkday) $summary['holiday_scan_days']++;
                else {
                    $summary['attendance_days']++;
                    if ($late) $summary['late_days']++;
                    if ($scanCount === 1) $summary['one_scan_days']++;
                    if (!empty($calendar['allow_afternoon_entry'])) $summary['afternoon_entry_days']++;
                }
                $summary['total_scans'] += (int)$daily['raw_scan_count'];
            }
            uasort($employees, static fn($x,$y) => strnatcasecmp((string)$x['employee_code'],(string)$y['employee_code']));
            $summary['employees_with_scans'] = count(array_filter($employees, static fn($e) => $e['full_name'] !== 'Belum dipetakan'));
            $summary['unmatched_identities'] = count($unmatched);
        }

        $maxScans = 1;
        foreach ($employees as $employee) foreach ($employee['details'] as $detail) $maxScans = max($maxScans, count($detail['scans']));
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
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$endDate) || $endDate<$startDate) {
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
        $startDate = (string) $this->request->getGet('start_date');
        $endDate = (string) $this->request->getGet('end_date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $endDate < $startDate) {
            return redirect()->to('/sdit/rekap')->with('error', 'Rentang tanggal rekap SDIT tidak valid.');
        }

        $db = db_connect();
        $employees = $db->table('sdit_employees')->where('is_active', 1)->orderBy('employee_code', 'ASC')->get()->getResultArray();
        $endExclusive = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';
        // Ringkas scan di database per PIN dan tanggal agar ekspor tidak menampung
        // puluhan ribu baris scan mentah sekaligus di memori PHP.
        $dailyLogs = $db->query(
            "SELECT pin, DATE(scanned_at) AS scan_date,
                    GROUP_CONCAT(TIME(scanned_at) ORDER BY scanned_at ASC SEPARATOR ',') AS scan_times,
                    COUNT(*) AS scan_count
             FROM sdit_attendance_logs
             WHERE scanned_at >= ? AND scanned_at < ?
             GROUP BY pin, DATE(scanned_at)
             ORDER BY pin ASC, scan_date ASC",
            [$startDate . ' 00:00:00', $endExclusive]
        )->getResultArray();
        $logsByPin = [];
        foreach ($dailyLogs as $daily) {
            $times = array_values(array_filter(explode(',', (string) ($daily['scan_times'] ?? ''))));
            $logsByPin[(string) $daily['pin']][] = [
                'date' => (string) $daily['scan_date'],
                'scans' => $times,
                'scan_count' => (int) $daily['scan_count'],
            ];
        }

        $calendarRows = $db->table('sdit_work_calendar')
            ->where('work_date >=', $startDate)->where('work_date <=', $endDate)->get()->getResultArray();
        $calendarMap = [];
        foreach ($calendarRows as $item) $calendarMap[$item['work_date']] = $item;
        $weeklyRows = $db->table('sdit_work_schedules')->where('schedule_name', 'Jadwal SDIT')->get()->getResultArray();
        $weeklyMap = [];
        foreach ($weeklyRows as $item) $weeklyMap[(int) $item['day_of_week']] = $item;
        $individualRows = $db->table('sdit_employee_schedules')->get()->getResultArray();
        $individualMap = [];
        foreach ($individualRows as $item) $individualMap[(string) $item['pin']][(int) $item['day_of_week']][] = $item;

        $resolveStatus = static function (string $pin, string $date, array $scans) use ($calendarMap, $weeklyMap, $individualMap): array {
            $calendar = $calendarMap[$date] ?? null;
            $weekday = (int) (new \DateTimeImmutable($date))->format('N');
            $rule = null;
            foreach ($individualMap[$pin][$weekday] ?? [] as $candidate) {
                $from = $candidate['effective_from'] ?: '0001-01-01';
                $until = $candidate['effective_until'] ?: '9999-12-31';
                if ($from <= $date && $date <= $until && ($rule === null || ($candidate['effective_from'] ?? '') > ($rule['effective_from'] ?? ''))) {
                    $rule = $candidate;
                }
            }
            $rule = $rule ?? ($weeklyMap[$weekday] ?? null);
            $holiday = ($calendar['day_type'] ?? '') === 'holiday';
            $workday = $calendar !== null ? !$holiday : (bool) ($rule['is_workday'] ?? ($weekday <= 5));
            if ($holiday) return ['Hari Libur (ada scan)', false, false];
            if (!$workday) return ['Libur Mingguan (ada scan)', false, false];
            if (!empty($calendar['allow_afternoon_entry'])) return ['Izin Masuk Siang', true, false];
            if (!$rule || empty($rule['start_time'])) return ['Jadwal Belum Diatur', true, false];
            $cutoff = date('H:i:s', strtotime($rule['start_time'] . ' +' . (int) ($rule['late_after_minutes'] ?? 0) . ' minutes'));
            $late = substr($scans[0], 0, 8) > $cutoff;
            return [$late ? 'Terlambat' : 'Tepat Waktu', true, $late];
        };

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $summary = $book->getActiveSheet();
        $summary->setTitle('REKAB');
        $summary->mergeCells('A1:G1');
        $summary->mergeCells('A2:G2');
        $summary->setCellValue('A1', 'REKAB ABSEN SDIT');
        $summary->setCellValue('A2', date('d-m-Y', strtotime($startDate)) . ' s.d. ' . date('d-m-Y', strtotime($endDate)));
        $summary->fromArray(['NO', 'NAMA', 'NAMA di Fringer', 'JABATAN', 'KEDATANGAN', 'TERLAMBAT', 'TEPAT WAKTU'], null, 'A3');

        $employeeMap = [];
        foreach ($employees as $employee) $employeeMap[(string) $employee['pin']] = $employee;
        $detailRowCount = count($dailyLogs);
        $summaryRow = 4;
        $number = 1;
        foreach ($employees as $employee) {
            $summary->fromArray([
                $number++,
                $employee['full_name'],
                $employee['fingerprint_name'] ?: $employee['full_name'],
                $employee['position'] ?? '',
            ], null, 'A' . $summaryRow);
            $pin = (string) $employee['pin'];
            $presentCount = 0;
            $lateCount = 0;
            foreach ($logsByPin[$pin] ?? [] as $daily) {
                [$dailyStatus, $isCounted, $isLate] = $resolveStatus($pin, $daily['date'], $daily['scans']);
                if ($isCounted) {
                    $presentCount++;
                    if ($isLate) $lateCount++;
                }
            }
            $summary->setCellValue('E' . $summaryRow, $presentCount);
            $summary->setCellValue('F' . $summaryRow, $lateCount);
            $summary->setCellValue('G' . $summaryRow, '=E' . $summaryRow . '-F' . $summaryRow);
            $summary->setCellValue('H' . $summaryRow, (string) $employee['pin']);
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
        $summary->setAutoFilter('A3:G' . $summaryLastRow);
        $summary->freezePane('A4');

        $detail = $book->createSheet();
        $detail->setTitle('DATA');
        $detail->setCellValue('A1', 'Pegawai');
        $detail->setCellValue('D1', 'Data scanlog');
        $detail->setCellValue('J1', 'MAX JAM MASUK');
        $detail->fromArray(['PIN', 'Nama', 'Jabatan', 'Tanggal', 'Scan 1', 'Status', 'Scan 2', 'Scan 3'], null, 'A2');
        $detail->setCellValue('J2', 'Sesuai jadwal SDIT');

        $detailRow = 3;
        foreach ($logsByPin as $pin => $dailyRows) {
            foreach ($dailyRows as $daily) {
                $date = $daily['date'];
                $scans = $daily['scans'];
                [$status] = $resolveStatus((string) $pin, $date, $scans);
                $excelTimes = array_map(static function ($time) {
                    $parts = array_map('intval', explode(':', (string) $time));
                    return (($parts[0] ?? 0) * 3600 + ($parts[1] ?? 0) * 60 + ($parts[2] ?? 0)) / 86400;
                }, $scans);
                $employee = $employeeMap[(string) $pin] ?? [];
                $detail->fromArray([
                    (string) $pin,
                    $employee['full_name'] ?? 'Belum dipetakan',
                    $employee['position'] ?? '',
                    date('d-m-Y', strtotime($date)),
                    $excelTimes[0] ?? '',
                    $status,
                    $excelTimes[1] ?? '',
                    $excelTimes[2] ?? '',
                    (string) $pin,
                ], null, 'A' . $detailRow);
                $detailRow++;
            }
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
        $detail->getStyle('E3:E' . $detailLastRow)->getNumberFormat()->setFormatCode('hh:mm');
        $detail->getStyle('G3:H' . $detailLastRow)->getNumberFormat()->setFormatCode('hh:mm');
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
        $detail->setAutoFilter('A2:H' . $detailLastRow);
        $book->setActiveSheetIndex(0);

        $filename = 'rekap-pegawai-SDIT-' . $startDate . '-sampai-' . $endDate . '.xlsx';
        $response = service('response');
        $response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->setHeader('Cache-Control', 'max-age=0');
        ob_start();
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
        $output = ob_get_clean();
        $book->disconnectWorksheets();
        return $response->setBody($output);
    }

    public function schedules()
    {
        $rows = db_connect()->table('sdit_work_schedules')->where('schedule_name','Jadwal SDIT')->orderBy('day_of_week','ASC')->get()->getResultArray();
        $map = [];
        foreach ($rows as $row) $map[(int)$row['day_of_week']] = $row;

        // Default SDIT: Senin-Jumat bekerja, Sabtu-Minggu libur.
        // Pengaturan yang sudah tersimpan tetap menjadi prioritas.
        $defaults = [];
        foreach ([1,2,3,4,5,6,7] as $day) {
            $defaults[$day] = [
                'is_workday' => $day <= 5 ? 1 : 0,
                'start_time' => '07:30:00',
                'end_time' => '16:00:00',
                'late_after_minutes' => 0,
            ];
        }
        foreach ($map as $day => $row) $defaults[$day] = $row;

        return view('sdit/schedules', ['title'=>'Jadwal Kerja SDIT','days'=>[
            1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'
        ],'schedules'=>$defaults]);
    }

    public function saveSchedules()
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $days = $this->request->getPost('days');
        if (!is_array($days)) {
            return redirect()->to('/sdit/jadwal')->with('error', 'Data jadwal tidak ditemukan. Silakan muat ulang halaman.');
        }

        $labels = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];
        $validated = [];
        foreach ($labels as $day => $label) {
            $item = $days[$day] ?? [];
            $start = (string)($item['start_time'] ?? '');
            $end = (string)($item['end_time'] ?? '');
            $late = filter_var($item['late_after_minutes'] ?? null, FILTER_VALIDATE_INT);
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)
                || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)
                || $start >= $end || $late === false || $late === null || $late < 0 || $late > 240) {
                return redirect()->to('/sdit/jadwal')->with('error', 'Periksa jam masuk, jam pulang, dan toleransi hari '.$label.'. Jam pulang harus setelah jam masuk dan toleransi 0–240 menit.');
            }
            $validated[$day] = [
                'schedule_name'=>'Jadwal SDIT',
                'day_of_week'=>$day,
                'start_time'=>$start.':00',
                'end_time'=>$end.':00',
                'late_after_minutes'=>$late,
                'is_workday'=>!empty($item['is_workday']) ? 1 : 0,
                'updated_at'=>$now,
            ];
        }

        $db->transBegin();
        try {
            foreach ($validated as $day => $payload) {
                $exists = $db->table('sdit_work_schedules')
                    ->where('schedule_name','Jadwal SDIT')->where('day_of_week',$day)
                    ->get()->getRowArray();
                if ($exists) {
                    $db->table('sdit_work_schedules')->where('id',$exists['id'])->update($payload);
                } else {
                    $payload['created_at'] = $now;
                    $db->table('sdit_work_schedules')->insert($payload);
                }
            }
            if ($db->transStatus() === false) throw new \RuntimeException('Gagal menyimpan jadwal SDIT.');
            $db->transCommit();
            return redirect()->to('/sdit/jadwal')->with('success','Jadwal mingguan SDIT berhasil disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'SDIT weekly schedule save failed: '.$e->getMessage());
            return redirect()->to('/sdit/jadwal')->with('error',$e->getMessage());
        }
    }

    public function employeeSchedules()
    {
        $db = db_connect();
        $employees = $db->table('sdit_employees')->where('is_active', 1)->orderBy('employee_code', 'ASC')->get()->getResultArray();
        $employeeMap = [];
        foreach ($employees as $employee) {
            $employeeMap[(string) $employee['pin']] = $employee;
        }

        // Avoid SQL JOIN on PIN because legacy tables may use different collations.
        $scheduleRows = $db->table('sdit_employee_schedules')
            ->orderBy('effective_from', 'DESC')
            ->orderBy('day_of_week', 'ASC')
            ->get()->getResultArray();
        $rows = [];
        foreach ($scheduleRows as $schedule) {
            $employee = $employeeMap[(string) $schedule['pin']] ?? [];
            $schedule['full_name'] = $employee['full_name'] ?? null;
            $schedule['employee_code'] = $employee['employee_code'] ?? null;
            $rows[] = $schedule;
        }
        usort($rows, static function ($a, $b) {
            $codeCompare = strnatcasecmp((string) ($a['employee_code'] ?? $a['pin']), (string) ($b['employee_code'] ?? $b['pin']));
            return $codeCompare !== 0 ? $codeCompare : ((int) $a['day_of_week'] <=> (int) $b['day_of_week']);
        });

        return view('sdit/employee_schedules', [
            'title' => 'Jadwal Khusus Pegawai SDIT',
            'employees' => $employees,
            'rows' => $rows,
            'days' => [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'],
        ]);
    }

    public function saveEmployeeSchedule()
    {
        $pin = trim((string)$this->request->getPost('pin'));
        $day = (int)$this->request->getPost('day_of_week');
        $start = (string)$this->request->getPost('start_time');
        $end = (string)$this->request->getPost('end_time');
        $from = trim((string)$this->request->getPost('effective_from'));
        $until = trim((string)$this->request->getPost('effective_until'));
        $db = db_connect();
        $employee = $db->table('sdit_employees')->where('pin', $pin)->get()->getRowArray();
        if (!$employee || $day < 1 || $day > 7
            || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)
            || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)
            || ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from))
            || ($until !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until))
            || ($from !== '' && $until !== '' && $until < $from)) {
            return redirect()->to('/sdit/jadwal-pegawai')->with('error', 'Data jadwal khusus tidak valid.');
        }
        $newFrom = $from !== '' ? $from : '0001-01-01';
        $newUntil = $until !== '' ? $until : '9999-12-31';
        $existingRules = $db->table('sdit_employee_schedules')
            ->where('pin', $pin)->where('day_of_week', $day)->get()->getResultArray();
        foreach ($existingRules as $existing) {
            $oldFrom = $existing['effective_from'] ?: '0001-01-01';
            $oldUntil = $existing['effective_until'] ?: '9999-12-31';
            if ($newFrom <= $oldUntil && $oldFrom <= $newUntil) {
                return redirect()->to('/sdit/jadwal-pegawai')->with('error', 'Periode jadwal bertabrakan dengan jadwal pegawai yang sudah ada untuk hari tersebut. Hapus atau ubah jadwal lama terlebih dahulu.');
            }
        }
        $payload = [
            'pin'=>$pin,'day_of_week'=>$day,'start_time'=>$start.':00','end_time'=>$end.':00',
            'late_after_minutes'=>max(0,(int)$this->request->getPost('late_after_minutes')),
            'effective_from'=>$from !== '' ? $from : null,
            'effective_until'=>$until !== '' ? $until : null,
            'is_workday'=>$this->request->getPost('is_workday') ? 1 : 0,
            'updated_at'=>date('Y-m-d H:i:s'),
        ];
        $payload['created_at']=date('Y-m-d H:i:s');
        $db->table('sdit_employee_schedules')->insert($payload);
        return redirect()->to('/sdit/jadwal-pegawai')->with('success', 'Jadwal khusus pegawai SDIT berhasil ditambahkan.');
    }

    public function deleteEmployeeSchedule(int $id)
    {
        db_connect()->table('sdit_employee_schedules')->where('id', $id)->delete();
        return redirect()->to('/sdit/jadwal-pegawai')->with('success', 'Jadwal khusus pegawai SDIT dihapus.');
    }

    public function calendar()
    {
        $month = (string)$this->request->getGet('bulan');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month)) $month = date('Y-m');
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
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !in_array($type,['holiday','workday'],true)) {
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
        $db = db_connect();
        $startDate = trim((string)$this->request->getGet('start_date'));
        $endDate = trim((string)$this->request->getGet('end_date'));
        $pin = trim((string)$this->request->getGet('pin'));
        if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = '';
        if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = '';
        if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) [$startDate, $endDate] = [$endDate, $startDate];
        $builder = $db->table('sdit_invalid_scans')->orderBy('id', 'DESC');
        if ($startDate !== '') $builder->where('original_scanned_at >=', $startDate . ' 00:00:00');
        if ($endDate !== '') $builder->where('original_scanned_at <', date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00');
        if ($pin !== '') $builder->where('pin', $pin);
        $rows = $builder->limit(1000)->get()->getResultArray();
        return view('sdit/time_check', [
            'title' => 'Pemeriksaan Waktu Mesin SDIT',
            'rows' => $rows, 'startDate' => $startDate, 'endDate' => $endDate,
            'pin' => $pin, 'totalInvalid' => $db->table('sdit_invalid_scans')->countAllResults(),
        ]);
    }

    public function pinMapping()
    {
        $db = db_connect();
        $employees = $db->table('sdit_employees')->orderBy('CAST(pin AS UNSIGNED)', 'ASC')->get()->getResultArray();
        $known = [];
        foreach ($employees as $employee) $known[(string)$employee['pin']] = true;
        $scanPins = $db->table('sdit_attendance_logs')
            ->select('pin, COUNT(*) AS scan_count, MIN(scanned_at) AS first_scan, MAX(scanned_at) AS last_scan', false)
            ->groupBy('pin')->orderBy('CAST(pin AS UNSIGNED)', 'ASC')->get()->getResultArray();
        $unmapped = [];
        foreach ($scanPins as $item) if (!isset($known[(string)$item['pin']])) $unmapped[] = $item;
        return view('sdit/pin_mapping', [
            'title' => 'Pemetaan PIN SDIT', 'rows' => $employees, 'unmapped' => $unmapped,
        ]);
    }

    private function page(string $title, string $active)
    {
        return view('sdit/index', ['title' => $title, 'activeModule' => $active]);
    }
}
