<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading">
    <div class="text-uppercase fw-bold text-primary mb-2" style="font-size:10px;letter-spacing:.12em">Modul SDIT · Neo W-201BNC</div>
    <h1 class="mb-2">Impor BIN SDIT</h1>
    <p class="text-secondary">Unggah file asli dari mesin SDIT untuk membaca log fingerprint dan memeriksa waktu sebelum data disimpan.</p>
</div>
<div class="card">
    <div class="card-body p-4">
        <form method="post" action="/sdit/impor/preview" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">File log BIN</label>
                <input type="file" class="form-control" name="bin_file" accept=".bin,application/octet-stream" required>
                <div class="form-text">Format yang didukung: file HisGLog berisi record 16 byte.</div>
            </div>
            <div class="alert alert-info">
                <i class="bi bi-shield-check me-2"></i>File asli tidak diubah. Waktu 1 Januari 2017 akan ditampilkan sebagai <strong>Waktu Mesin Tidak Valid</strong> dan tidak akan dikoreksi otomatis.
            </div>
            <button class="btn btn-primary"><i class="bi bi-search me-2"></i>Baca & Pratinjau BIN</button>
            <a href="/sdit" class="btn btn-outline-secondary ms-2">Kembali</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
