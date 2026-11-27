<?php

namespace App\Controllers;

/**
 * Ruang kerja khusus SDIT.
 *
 * Modul ini sengaja dipisahkan dari impor TXT Revo agar pengembangan
 * decoder BIN SDIT tidak mengubah alur SMPIT maupun RAIT/KBIT.
 */
class Sdit extends BaseController
{
    public function index()
    {
        return $this->page('Menu SDIT', 'Beranda');
    }

    public function imports()
    {
        return $this->page('Impor BIN SDIT', 'Impor BIN');
    }

    public function scans()
    {
        return $this->page('Detail Scan SDIT', 'Detail Scan');
    }

    public function recap()
    {
        return $this->page('Rekap Absensi SDIT', 'Rekap');
    }

    public function timeCheck()
    {
        return $this->page('Pemeriksaan Waktu Mesin SDIT', 'Pemeriksaan Waktu');
    }

    public function pinMapping()
    {
        return $this->page('Pemetaan PIN SDIT', 'Pemetaan PIN');
    }

    private function page(string $title, string $active)
    {
        return view('sdit/index', [
            'title' => $title,
            'activeModule' => $active,
        ]);
    }
}
