<?php

namespace App\Controllers;

use App\Services\SditBinDecoder;

class Sdit extends BaseController
{
    public function index()
    {
        return $this->page('Menu SDIT', 'Beranda');
    }

    public function imports()
    {
        return view('sdit/imports', ['title' => 'Impor BIN SDIT']);
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
            session()->set('sdit_bin_preview', [
                'original_name' => $file->getClientName(),
                'sha256' => hash('sha256', $raw),
                'size' => strlen($raw),
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

    public function scans() { return $this->page('Detail Scan SDIT', 'Detail Scan'); }
    public function recap() { return $this->page('Rekap Absensi SDIT', 'Rekap'); }
    public function timeCheck() { return $this->page('Pemeriksaan Waktu Mesin SDIT', 'Pemeriksaan Waktu'); }
    public function pinMapping() { return $this->page('Pemetaan PIN SDIT', 'Pemetaan PIN'); }

    private function page(string $title, string $active)
    {
        return view('sdit/index', ['title' => $title, 'activeModule' => $active]);
    }
}
