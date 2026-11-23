<?php

namespace App\Controllers;

use App\Models\DeviceModel;
use App\Models\EmployeeModel;
use App\Services\FingerspotSqlBackupParser;

class BackupImports extends BaseController
{
    private function stage(array $records): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'backup-imports';
        if (! is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $token = bin2hex(random_bytes(16));
        file_put_contents($dir . DIRECTORY_SEPARATOR . $token . '.json', json_encode($records, JSON_THROW_ON_ERROR));
        return $token;
    }

    private function staged(string $token): array
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new \RuntimeException('Sesi pratinjau tidak valid.');
        }
        $path = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'backup-imports' . DIRECTORY_SEPARATOR . $token . '.json';
        if (! is_file($path)) {
            throw new \RuntimeException('File pratinjau tidak ditemukan atau sudah kedaluwarsa.');
        }
        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function index()
    {
        return view('backup_imports/index', [
            'title' => 'Impor Backup Fingerspot',
            'devices' => (new DeviceModel())->where('is_active', 1)->findAll(),
        ]);
    }

    public function masterPreview()
    {
        $file = $this->request->getFile('backup_file');
        $unit = (string) $this->request->getPost('unit_code');
        if (! $file || ! $file->isValid() || strtolower($file->getExtension()) !== 'sql') {
            return redirect()->back()->with('error', 'Pilih file backup SQL yang valid.');
        }
        if (! in_array($unit, ['SMPIT', 'RAIT_KBIT'], true)) {
            return redirect()->back()->with('error', 'Pilih sumber unit yang valid.');
        }
        if ($file->getSizeByUnit('mb') > 100) {
            return redirect()->back()->with('error', 'Ukuran backup melebihi batas 100 MB.');
        }

        try {
            $sql = file_get_contents($file->getTempName());
            $parsed = (new FingerspotSqlBackupParser())->parse($sql, 'employees');
            if (count($parsed['records']) === 0) {
                return redirect()->back()->with('error', 'Tidak ditemukan data pegawai pada tabel pegawai.');
            }
            $token = $this->stage($parsed['records']);
            return view('backup_imports/master_preview', [
                'title' => 'Pratinjau Master Pegawai',
                'preview' => $parsed,
                'unit' => $unit,
                'token' => $token,
                'filename' => $file->getClientName(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Backup tidak dapat dibaca: ' . $e->getMessage());
        }
    }

    public function masterProcess()
    {
        $token = (string) $this->request->getPost('token');
        $unit = (string) $this->request->getPost('unit_code');
        try {
            $records = $this->staged($token);
            if (! in_array($unit, ['SMPIT', 'RAIT_KBIT'], true)) {
                throw new \RuntimeException('Unit sumber tidak valid.');
            }
            $db = db_connect();
            $db->transBegin();
            $model = new EmployeeModel();
            $inserted = 0;
            $updated = 0;
            foreach ($records as $record) {
                $record['unit_code'] = $unit;
                $record['employee_code'] = $unit . ':' . $record['fingerprint_pin'];
                $existing = $db->table('employees')
                    ->where('unit_code', $unit)
                    ->where('fingerprint_pin', $record['fingerprint_pin'])
                    ->get()->getRowArray();
                if ($existing) {
                    $model->update($existing['id'], $record);
                    $updated++;
                } else {
                    $model->insert($record);
                    $inserted++;
                }
            }
            if ($db->transStatus() === false) {
                $db->transRollback();
                throw new \RuntimeException('Transaksi database gagal. Tidak ada perubahan yang disimpan.');
            }
            $db->transCommit();
            @unlink(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'backup-imports' . DIRECTORY_SEPARATOR . $token . '.json');
            return redirect()->to('/impor-backup')->with('success', "Master pegawai {$unit} selesai: {$inserted} baru, {$updated} diperbarui.");
        } catch (\Throwable $e) {
            if (isset($db)) $db->transRollback();
            return redirect()->to('/impor-backup')->with('error', 'Impor master gagal: ' . $e->getMessage());
        }
    }

    public function logPreview()
    {
        $file = $this->request->getFile('backup_file');
        $deviceId = (int) $this->request->getPost('device_id');
        if (! $file || ! $file->isValid() || strtolower($file->getExtension()) !== 'sql') {
            return redirect()->back()->with('error', 'Pilih file backup SQL yang valid.');
        }
        $device = (new DeviceModel())->find($deviceId);
        if (! $device) return redirect()->back()->with('error', 'Pilih mesin sumber yang valid.');
        if ($file->getSizeByUnit('mb') > 100) return redirect()->back()->with('error', 'Ukuran backup melebihi batas 100 MB.');

        try {
            $sql = file_get_contents($file->getTempName());
            $parsed = (new FingerspotSqlBackupParser())->parse($sql, 'logs');
            if (count($parsed['records']) === 0) {
                return redirect()->back()->with('error', 'Tidak ditemukan data scan pada tabel att_log.');
            }
            $token = $this->stage($parsed['records']);
            return view('backup_imports/log_preview', [
                'title' => 'Pratinjau Riwayat Scan',
                'preview' => $parsed,
                'device' => $device,
                'token' => $token,
                'filename' => $file->getClientName(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Backup tidak dapat dibaca: ' . $e->getMessage());
        }
    }

    public function logProcess()
    {
        // Large SQL backups must not be processed as one long row-by-row transaction.
        @set_time_limit(0);

        $token = (string) $this->request->getPost('token');
        $deviceId = (int) $this->request->getPost('device_id');
        $device = (new DeviceModel())->find($deviceId);
        if (! $device) {
            return redirect()->to('/impor-backup')->with('error', 'Mesin sumber tidak valid.');
        }

        try {
            $records = $this->staged($token);
            $db = db_connect();
            $inserted = 0;
            $total = count($records);
            $chunkSize = 500;
            $createdAt = date('Y-m-d H:i:s');

            // INSERT IGNORE uses the unique key (device_id, pin, scanned_at)
            // to make retries safe if a request stops after some committed batches.
            foreach (array_chunk($records, $chunkSize) as $chunk) {
                $valueSql = [];
                $binds = [];

                foreach ($chunk as $record) {
                    $valueSql[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    array_push(
                        $binds,
                        $deviceId,
                        null,
                        $record['machine_no'],
                        $record['pin'],
                        null,
                        $record['verify_mode'],
                        $record['inout_mode'],
                        $record['scanned_at'],
                        null,
                        $createdAt
                    );
                }

                $sql = 'INSERT IGNORE INTO attendance_logs '
                    . '(device_id, import_batch_id, machine_no, pin, employee_name_source, verify_mode, inout_mode, scanned_at, source_row, created_at) '
                    . 'VALUES ' . implode(',', $valueSql);

                $db->transBegin();
                $db->query($sql, $binds);

                if ($db->transStatus() === false) {
                    $db->transRollback();
                    throw new \\RuntimeException('Satu batch impor gagal. Batch sebelumnya tetap tersimpan dan aman untuk dilanjutkan.');
                }

                $inserted += $db->affectedRows();
                $db->transCommit();
            }

            $duplicates = $total - $inserted;
            @unlink(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'backup-imports' . DIRECTORY_SEPARATOR . $token . '.json');

            return redirect()->to('/impor-backup')->with(
                'success',
                "Riwayat scan selesai: {$inserted} baru, {$duplicates} duplikat dilewati dari {$total} baris."
            );
        } catch (\\Throwable $e) {
            if (isset($db) && $db->transStatus() !== false) {
                $db->transRollback();
            }

            return redirect()->to('/impor-backup')->with(
                'error',
                'Impor riwayat scan gagal: ' . $e->getMessage() . ' Jika proses sempat menyimpan batch, jalankan ulang preview dan impor; baris yang sudah masuk akan dilewati otomatis.'
            );
        }
    }
}
