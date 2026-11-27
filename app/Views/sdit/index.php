<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<div class="page-heading d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <div class="text-uppercase fw-bold text-primary mb-2" style="font-size:10px;letter-spacing:.12em">Modul terpisah · SDIT</div>
        <h1 class="mb-2"><?= esc($title) ?></h1>
        <p class="text-secondary mb-0">Area khusus untuk pengolahan mesin fingerprint SDIT. Modul impor TXT SMPIT dan RAIT/KBIT tetap berjalan melalui menu masing-masing.</p>
    </div>
    <span class="badge text-bg-primary">SDIT</span>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-4">
        <a href="/sdit/impor" class="card h-100 text-decoration-none text-reset">
            <div class="card-body p-4">
                <div class="rounded-3 bg-primary-subtle text-primary d-inline-flex p-3 mb-3"><i class="bi bi-file-earmark-binary fs-4"></i></div>
                <h5>Impor BIN SDIT</h5>
                <p class="text-secondary mb-0">Ruang khusus untuk membaca dan mengimpor file log BIN dari mesin SDIT.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a href="/sdit/scan" class="card h-100 text-decoration-none text-reset">
            <div class="card-body p-4">
                <div class="rounded-3 bg-info-subtle text-info d-inline-flex p-3 mb-3"><i class="bi bi-list-check fs-4"></i></div>
                <h5>Detail Scan SDIT</h5>
                <p class="text-secondary mb-0">Pemeriksaan seluruh rekaman scan berdasarkan mesin dan PIN SDIT.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a href="/sdit/rekap" class="card h-100 text-decoration-none text-reset">
            <div class="card-body p-4">
                <div class="rounded-3 bg-success-subtle text-success d-inline-flex p-3 mb-3"><i class="bi bi-calendar2-check fs-4"></i></div>
                <h5>Rekap Absensi SDIT</h5>
                <p class="text-secondary mb-0">Rekap kehadiran khusus pegawai SDIT.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a href="/sdit/jadwal" class="card h-100 text-decoration-none text-reset">
            <div class="card-body p-4"><div class="rounded-3 bg-primary-subtle text-primary d-inline-flex p-3 mb-3"><i class="bi bi-calendar-week fs-4"></i></div><h5>Jadwal Kerja SDIT</h5><p class="text-secondary mb-0">Atur jam masuk, jam pulang, hari kerja, dan toleransi keterlambatan SDIT.</p></div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a href="/sdit/kalender" class="card h-100 text-decoration-none text-reset">
            <div class="card-body p-4"><div class="rounded-3 bg-warning-subtle text-warning d-inline-flex p-3 mb-3"><i class="bi bi-calendar2-event fs-4"></i></div><h5>Kalender Kerja SDIT</h5><p class="text-secondary mb-0">Atur hari libur, hari kerja pengganti, dan izin masuk siang.</p></div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a href="/sdit/pemeriksaan-waktu" class="card h-100 text-decoration-none text-reset">
            <div class="card-body p-4">
                <div class="rounded-3 bg-warning-subtle text-warning d-inline-flex p-3 mb-3"><i class="bi bi-clock-history fs-4"></i></div>
                <h5>Pemeriksaan Waktu Mesin</h5>
                <p class="text-secondary mb-0">Menandai tanggal atau jam mesin yang tidak valid tanpa mengubah waktu asli scan.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a href="/sdit/pemetaan-pin" class="card h-100 text-decoration-none text-reset">
            <div class="card-body p-4">
                <div class="rounded-3 bg-secondary-subtle text-secondary d-inline-flex p-3 mb-3"><i class="bi bi-person-vcard fs-4"></i></div>
                <h5>Pemetaan PIN SDIT</h5>
                <p class="text-secondary mb-0">Menghubungkan PIN pada mesin SDIT dengan data pegawai yang benar.</p>
            </div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-4">
        <h5 class="mb-3"><i class="bi bi-shield-check me-2 text-success"></i>Pemisahan data SDIT</h5>
        <ul class="text-secondary small mb-0 ps-3">
            <li>Menu dan controller SDIT memiliki jalur tersendiri.</li>
            <li>File BIN asli tidak akan diubah saat proses pembacaan.</li>
            <li>Rekaman dengan tanggal default mesin 1 Januari 2017 akan dipertahankan sebagai waktu asli dan ditandai tidak valid untuk rekap.</li>
            <li>Data TXT SMPIT serta RAIT/KBIT tidak diubah oleh modul ini.</li>
        </ul>
        <?php if (($activeModule ?? '') !== 'Beranda'): ?>
            <div class="alert alert-info mt-3 mb-0">
                <strong><?= esc($activeModule) ?>:</strong> fondasi halaman sudah dibuat. Proses data akan diaktifkan setelah decoder BIN dan pengujian file SDIT selesai, agar tidak ada data yang salah masuk.
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
