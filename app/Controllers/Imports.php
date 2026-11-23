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

    public function process()
    {
        $preview = session()->get('import_preview');
        if (! $preview) {
            return redirect()->to('/impor')->with('error', 'Pratinjau impor sudah tidak tersedia. Silakan ulangi.');
        }

        $db = db_connect();
        $db->transStart();
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

        $logs = new AttendanceLogModel();
        $inserted = 0;
        foreach ($preview['records'] as $record) {
            $exists = $db->table('attendance_logs')
                ->where('device_id', $preview['device_id'])
                ->where('pin', $record['pin'])
                ->where('scanned_at', $record['scanned_at'])
                ->countAllResults() > 0;
            if ($exists) {
                continue;
            }
            $logs->insert($record + ['device_id' => $preview['device_id'], 'import_batch_id' => $batchId]);
            $inserted++;
        }
        $db->transComplete();
        session()->remove('import_preview');

        return redirect()->to('/impor')->with('success', "Impor selesai. {$inserted} scan baru disimpan; duplikat dilewati.");
    }
}