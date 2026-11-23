<?php

namespace App\Controllers;

use App\Services\RevoTxtDecoder;
use App\Models\DeviceModel;
use App\Models\ImportBatchModel;
use App\Models\AttendanceLogModel;

class Imports extends BaseController
{
    public function index()
    {
        return view('imports/index', [
            'title' => 'Impor Log Fingerprint',
            'devices' => (new DeviceModel())->where('is_active', 1)->findAll(),
        ]);
    }

    public function preview()
    {
        $file = $this->request->getFile('log_file');
        $deviceId = (int) $this->request->getPost('device_id');

        if (! $file || ! $file->isValid() || strtolower($file->getExtension()) !== 'txt') {
            return redirect()->back()->with('error', 'Pilih file TXT yang valid.');
        }
        $device = (new DeviceModel())->find($deviceId);
        if (! $device) {
            return redirect()->back()->with('error', 'Pilih sumber mesin yang terdaftar.');
        }

        try {
            $raw = file_get_contents($file->getTempName());
            $decoder = new RevoTxtDecoder();
            $parsed = $decoder->parse($decoder->decode($raw));
            session()->set('import_preview', [
                'device_id' => $deviceId,
                'original_name' => $file->getClientName(),
                'sha256' => hash('sha256', $raw),
                'records' => $parsed['records'],
                'invalid' => $parsed['invalid'],
            ]);
            return view('imports/preview', [
                'title' => 'Pratinjau Impor',
                'device' => $device,
                'preview' => $parsed,
                'originalName' => $file->getClientName(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'File gagal dibaca: ' . $e->getMessage());
        }
    }

    public function showPreview()
    {
        $preview = session()->get('import_preview');
        if (! $preview) {
            return redirect()->to('/impor')->with('error', 'Pratinjau impor sudah tidak tersedia. Silakan unggah ulang file TXT.');
        }

        $device = (new DeviceModel())->find((int) $preview['device_id']);
        if (! $device) {
            return redirect()->to('/impor')->with('error', 'Data mesin sumber tidak ditemukan.');
        }

        return view('imports/preview', [
            'title' => 'Pratinjau Impor',
            'device' => $device,
            'preview' => [
                'records' => $preview['records'],
                'invalid' => $preview['invalid'],
            ],
            'originalName' => $preview['original_name'],
        ]);
    }

    public function process()
    {
        $preview = session()->get('import_preview');
        if (! $preview) {
            return redirect()->to('/impor')->with('error', 'Pratinjau impor sudah tidak tersedia. Silakan ulangi.');
        }

        // Large fingerprint files need more than PHP's common 60-second default.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $batchModel = new ImportBatchModel();
            $batchId = $batchModel->insert([
                'device_id' => $preview['device_id'],
                'original_filename' => $preview['original_name'],
                'file_sha256' => $preview['sha256'],
                'total_rows' => count($preview['records']) + count($preview['invalid']),
                'valid_rows' => count($preview['records']),
                'invalid_rows' => count($preview['invalid']),
                'imported_by' => session()->get('user_id'),
            ], true);

            if (! $batchId) {
                throw new \RuntimeException('Gagal membuat catatan batch impor.');
            }

            $deviceId = (int) $preview['device_id'];
            $records = $preview['records'];
            $inserted = 0;
            $chunkSize = 400;

            foreach (array_chunk($records, $chunkSize) as $chunk) {
                $pins = array_values(array_unique(array_column($chunk, 'pin')));
                $scanTimes = array_values(array_unique(array_column($chunk, 'scanned_at')));

                // Fetch possible duplicates in one query per chunk, then match exact PIN + timestamp pairs.
                $existingRows = $db->table('attendance_logs')
                    ->select('pin, scanned_at')
                    ->where('device_id', $deviceId)
                    ->whereIn('pin', $pins)
                    ->whereIn('scanned_at', $scanTimes)
                    ->get()
                    ->getResultArray();

                $existing = [];
                foreach ($existingRows as $row) {
                    $existing[$row['pin'] . '|' . $row['scanned_at']] = true;
                }

                $toInsert = [];
                foreach ($chunk as $record) {
                    $key = $record['pin'] . '|' . $record['scanned_at'];
                    if (isset($existing[$key])) {
                        continue;
                    }
                    $toInsert[] = $record + [
                        'device_id' => $deviceId,
                        'import_batch_id' => $batchId,
                    ];
                    // Avoid duplicate records inside the same uploaded file.
                    $existing[$key] = true;
                }

                if ($toInsert !== []) {
                    $db->table('attendance_logs')->insertBatch($toInsert, null, $chunkSize);
                    $inserted += count($toInsert);
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database menolak sebagian data impor.');
            }

            $db->transCommit();
            session()->remove('import_preview');

            return redirect()->to('/impor')->with(
                'success',
                "Impor selesai. {$inserted} scan baru disimpan; duplikat dilewati."
            );
        } catch (\\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Fingerprint import failed: ' . $e->getMessage());
            return redirect()->to('/impor')->with(
                'error',
                'Impor gagal dan transaksi dibatalkan: ' . $e->getMessage()
            );
        }
    }
}
