<?= $this->extend('layout') ?><?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div><h2>Backup & Pemulihan</h2><p class="text-secondary mb-0">Tiga backup terpisah agar arsip Fingerspot dan database NEURO ABSENSI tidak tercampur.</p></div>
</div>
<div class="card p-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h4 class="mb-1">Generator SQL Fingerspot</h4><p class="text-secondary mb-0">Buat file SQL dari data NEURO ABSENSI untuk diperbarui ke database Fingerspot, beserta laporan pemetaan pegawai.</p></div>
        <a class="btn btn-primary" href="/backup/fingerspot"><i class="bi bi-arrow-left-right me-1"></i>Buka Generator Fingerspot</a>
    </div>
</div>
<div class="row g-3">
    <?php foreach (['smp' => 'Backup SMPIT', 'rakb' => 'Backup RA&KB'] as $key => $label): ?>
    <div class="col-lg-6">
        <div class="card p-4 h-100">
            <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-database-fill fs-4 text-primary"></i><h4 class="mb-0"><?= esc($label) ?></h4></div>
            <p class="text-secondary">Arsip SQL asli dari Fingerspot Personnel. File yang diunduh tetap dalam format sumber dan tidak digabung dengan database NEURO ABSENSI.</p>
            <?php $source = $sources[$key] ?>
            <?php if ($source['exists']): ?>
                <div class="alert alert-success py-2 mb-3"><i class="bi bi-check-circle me-1"></i>Arsip tersedia · <?= esc(number_format($source['size'] / 1048576, 2)) ?> MB · <?= esc($source['modified']) ?></div>
                <a class="btn btn-primary mb-3" href="/backup/sumber/<?= esc($key) ?>"><i class="bi bi-download me-1"></i>Unduh <?= esc($label) ?></a>
            <?php else: ?>
                <div class="alert alert-warning py-2 mb-3">Arsip sumber belum disimpan di komputer aplikasi.</div>
            <?php endif ?>
            <form method="post" action="/backup/simpan-sumber" enctype="multipart/form-data" class="border-top pt-3">
                <?= csrf_field() ?><input type="hidden" name="source_key" value="<?= esc($key) ?>">
                <label class="form-label">Simpan / perbarui file sumber SQL</label>
                <input class="form-control mb-2" type="file" name="backup_file" accept=".sql" required>
                <?php if ($source['exists']): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="replace_existing" value="1" id="replace-<?= esc($key) ?>"><label class="form-check-label small" for="replace-<?= esc($key) ?>">Saya yakin ingin mengganti arsip lama</label></div><?php endif ?>
                <button class="btn btn-outline-primary"><i class="bi bi-cloud-arrow-up me-1"></i>Simpan arsip sumber</button>
            </form>
        </div>
    </div>
    <?php endforeach ?>
    <div class="col-12">
        <div class="card p-4">
            <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-shield-lock-fill fs-4 text-success"></i><h4 class="mb-0">Backup NEURO ABSENSI</h4></div>
            <p class="text-secondary">Membuat dump penuh database aktif NEURO ABSENSI, termasuk struktur tabel, indeks, relasi, dan seluruh data yang tersimpan saat tombol ditekan.</p>
            <div class="alert alert-info py-2">File ini adalah backup database aplikasi. File tidak berisi arsip SMPIT atau RA&KB dari Fingerspot.</div>
            <div><a class="btn btn-success" href="/backup/neuro"><i class="bi bi-download me-1"></i>Unduh Backup NEURO ABSENSI (.sql)</a></div>
        </div>
    </div>

    <div class="col-12">
        <div class="card p-4">
            <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-arrow-counterclockwise fs-4 text-warning"></i><h4 class="mb-0">Pemulihan Database NEURO</h4></div>
            <p class="text-secondary">Unggah file backup NEURO untuk diperiksa, lalu pulihkan ke database percobaan. Database utama tidak disentuh.</p>
            <form method="post" action="/backup/pemulihan/preview" enctype="multipart/form-data" class="row g-3 align-items-end">
                <?= csrf_field() ?>
                <div class="col-lg-8"><label class="form-label">File backup NEURO (.sql)</label><input class="form-control" type="file" name="restore_file" accept=".sql" required></div>
                <div class="col-lg-4"><button class="btn btn-warning w-100"><i class="bi bi-search me-1"></i>Periksa file backup</button></div>
            </form>
            <div class="alert alert-warning mt-3 mb-0">Tujuan pemulihan uji selalu <strong>neuro_absensi_test</strong>. Fitur ini tidak menyediakan pemulihan langsung ke database aktif.</div>
        </div>
    </div>
</div>
<div class="alert alert-warning mt-4 mb-0"><strong>Catatan penting:</strong> Arsip SMPIT dan RA&KB tetap disimpan sebagai sumber asli. Untuk membuat SQL berdasarkan data NEURO ABSENSI, gunakan Generator SQL Fingerspot di atas. File hasil generator adalah skrip merge dan harus diuji pada salinan database Fingerspot setelah membuat backup.</div>
<?= $this->endSection() ?>
