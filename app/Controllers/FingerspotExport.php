<?php

namespace App\Controllers;

use App\Services\FingerspotPinReview;
use App\Services\FingerspotSqlGenerator;

class FingerspotExport extends BaseController
{
    private const SOURCES = [
        'smp' => ['file'=>'smp.sql','device'=>'SMP-REVO16B','label'=>'SMPIT'],
        'rakb' => ['file'=>'rakb.sql','device'=>'RAKB-REVO185BNC','label'=>'RA&KB'],
    ];

    private function sourceContext(string $key): array
    {
        if (!isset(self::SOURCES[$key])) throw new \RuntimeException('Sumber Fingerspot tidak valid.');
        $source = self::SOURCES[$key];
        $archive = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'fingerspot' . DIRECTORY_SEPARATOR . $source['file'];
        if (!is_file($archive)) throw new \RuntimeException('Arsip SQL Fingerspot belum disimpan.');
        $db = db_connect();
        $device = $db->table('devices')->where('device_code', $source['device'])->get()->getRowArray();
        if (!$device) throw new \RuntimeException('Perangkat ' . $source['device'] . ' belum terdaftar.');
        $sql = file_get_contents($archive);
        if ($sql === false) throw new \RuntimeException('Arsip Fingerspot tidak dapat dibaca.');
        return [$source, $db, $device, $sql];
    }

    public function index()
    {
        return view('fingerspot_export/index', ['title'=>'Generator Backup Fingerspot']);
    }

    public function review()
    {
        $key = (string) $this->request->getPost('source');
        try {
            [$source, $db, $device, $sql] = $this->sourceContext($key);
            $review = (new FingerspotPinReview())->inspect($db, $device, $sql);
            return view('fingerspot_export/review', [
                'title'=>'Review PIN Pegawai',
                'sourceKey'=>$key,
                'sourceLabel'=>$source['label'],
                'review'=>$review,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Fingerspot PIN review failed: {message}', ['message'=>$e->getMessage()]);
            return redirect()->to('/backup/fingerspot')->with('error', 'Review PIN gagal: ' . $e->getMessage());
        }
    }

    public function generate()
    {
        @set_time_limit(0);
        $key = (string) $this->request->getPost('source');
        $selectedPins = $this->request->getPost('approved_pins');
        if (!is_array($selectedPins) || $selectedPins === []) {
            return redirect()->to('/backup/fingerspot')->with('error', 'Belum ada PIN yang disetujui. Jalankan Review PIN dan pilih pegawai terlebih dahulu.');
        }
        try {
            [$source, $db, $device, $sourceSql] = $this->sourceContext($key);
            $review = (new FingerspotPinReview())->inspect($db, $device, $sourceSql);
            $allowed = array_fill_keys($review['eligible_pins'], true);
            $approvedPins = array_values(array_filter(array_map('strval', $selectedPins), static fn($pin) => isset($allowed[$pin])));
            if ($approvedPins === []) throw new \RuntimeException('PIN yang dipilih tidak lolos pemeriksaan ulang. Ulangi Review PIN.');
            $result = (new FingerspotSqlGenerator())->generate($db, $device, $sourceSql, $source['label'], $approvedPins);
            $dir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'generated';
            if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) throw new \RuntimeException('Folder output tidak dapat dibuat.');
            $filename = 'NEURO-to-Fingerspot-' . $key . '-' . date('Ymd-His') . '.sql';
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            if (file_put_contents($path, $result['sql'], LOCK_EX) === false) throw new \RuntimeException('File SQL gagal ditulis.');
            session()->set('fingerspot_export_filename', $filename);
            session()->set('fingerspot_export_report', $result['report']);
            session()->set('fingerspot_export_summary', [
                'master'=>$result['master'], 'skipped_master'=>$result['skipped_master'],
                'logs'=>$result['logs'], 'skipped_logs'=>$result['skipped_logs'],
            ]);
            return redirect()->to('/backup/fingerspot/hasil');
        } catch (\Throwable $e) {
            log_message('error', 'Fingerspot export failed: {message}', ['message'=>$e->getMessage()]);
            return redirect()->to('/backup/fingerspot')->with('error', 'Generator gagal: ' . $e->getMessage());
        }
    }

    public function result()
    {
        $filename = (string) session()->get('fingerspot_export_filename');
        $report = session()->get('fingerspot_export_report');
        $summary = session()->get('fingerspot_export_summary');
        if ($filename === '' || !is_array($report) || !is_array($summary)) return redirect()->to('/backup/fingerspot')->with('error', 'Laporan tidak ditemukan. Jalankan generator kembali.');
        return view('fingerspot_export/result', ['title'=>'Hasil Generator Fingerspot', 'filename'=>$filename, 'report'=>$report, 'summary'=>$summary]);
    }

    public function download()
    {
        $filename = basename((string) session()->get('fingerspot_export_filename'));
        $path = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . $filename;
        if ($filename === '' || !is_file($path)) return redirect()->to('/backup/fingerspot')->with('error', 'File hasil generator tidak ditemukan.');
        return $this->response->download($path, null)->setFileName($filename);
    }
}
