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
        return $this->page('Rekap Absensi SDIT', 'Rekap');
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
