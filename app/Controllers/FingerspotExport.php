<?php

namespace App\Controllers;

use App\Services\FingerspotSqlGenerator;

class FingerspotExport extends BaseController
{
    private const SOURCES = [
        'smp' => ['file'=>'smp.sql','device'=>'SMP-REVO16B','label'=>'SMPIT'],
        'rakb' => ['file'=>'rakb.sql','device'=>'RAKB-REVO185BNC','label'=>'RA&KB'],
    ];

    public function index()
    {
        return view('fingerspot_export/index', ['title'=>'Generator Backup Fingerspot']);
    }

    public function generate()
    {
        @set_time_limit(0);
        $key = (string) $this->request->getPost('source');
        if (!isset(self::SOURCES[$key])) return redirect()->back()->with('error','Sumber Fingerspot tidak valid.');
        $source = self::SOURCES[$key];
        $archive = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'fingerspot' . DIRECTORY_SEPARATOR . $source['file'];
        if (!is_file($archive)) return redirect()->back()->with('error','Arsip SQL Fingerspot belum disimpan.');

        try {
            $db = db_connect();
            $device = $db->table('devices')->where('device_code',$source['device'])->get()->getRowArray();
            if (!$device) throw new \RuntimeException('Perangkat ' . $source['device'] . ' belum terdaftar.');
            $sourceSql = file_get_contents($archive);
            if ($sourceSql === false) throw new \RuntimeException('Arsip Fingerspot tidak dapat dibaca.');

            $result = (new FingerspotSqlGenerator())->generate($db,$device,$sourceSql,$source['label']);
            $dir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'generated';
            if (!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new \RuntimeException('Folder output tidak dapat dibuat.');
            $filename = 'NEURO-to-Fingerspot-' . $key . '-' . date('Ymd-His') . '.sql';
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            if (file_put_contents($path,$result['sql'],LOCK_EX) === false) throw new \RuntimeException('File SQL gagal ditulis.');

            session()->set('fingerspot_export_filename',$filename);
            session()->set('fingerspot_export_report',$result['report']);
            session()->set('fingerspot_export_summary',[
                'master'=>$result['master'],'skipped_master'=>$result['skipped_master'],
                'logs'=>$result['logs'],'skipped_logs'=>$result['skipped_logs'],
            ]);
            return redirect()->to('/backup/fingerspot/hasil');
        } catch (\Throwable $e) {
            log_message('error','Fingerspot export failed: {message}',['message'=>$e->getMessage()]);
            return redirect()->back()->with('error','Generator gagal: ' . $e->getMessage());
        }
    }

    public function result()
    {
        $filename = (string) session()->get('fingerspot_export_filename');
        $report = session()->get('fingerspot_export_report');
        $summary = session()->get('fingerspot_export_summary');
        if ($filename === '' || !is_array($report) || !is_array($summary)) return redirect()->to('/backup/fingerspot')->with('error','Laporan tidak ditemukan. Jalankan generator kembali.');
        return view('fingerspot_export/result',['title'=>'Hasil Generator Fingerspot','filename'=>$filename,'report'=>$report,'summary'=>$summary]);
    }

    public function download()
    {
        $filename = basename((string) session()->get('fingerspot_export_filename'));
        $path = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . $filename;
        if ($filename === '' || !is_file($path)) return redirect()->to('/backup/fingerspot')->with('error','File hasil generator tidak ditemukan.');
        return $this->response->download($path,null)->setFileName($filename);
    }
}
