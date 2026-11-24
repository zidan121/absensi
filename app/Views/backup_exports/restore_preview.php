<?= $this->extend('layout') ?><?= $this->section('content') ?>
<div class="mb-4"><h2>Konfirmasi Pemulihan Database</h2><p class="text-secondary">Periksa file backup sebelum dipulihkan ke database percobaan.</p></div>
<div class="card p-4">
    <div class="alert alert-warning"><strong>Perhatian:</strong> Pemulihan akan membuat atau mengganti tabel di database <code>neuro_absensi_test</code>. Database utama <code>neuro_absensi</code> tidak akan diubah.</div>
    <table class="table">
        <tr><th>File</th><td><?= esc($filename) ?></td></tr>
        <tr><th>Ukuran</th><td><?= esc(number_format($size / 1048576, 2)) ?> MB</td></tr>
        <tr><th>Database tujuan</th><td><strong>neuro_absensi_test</strong></td></tr>
        <tr><th>Jumlah tabel terdeteksi</th><td><?= esc($tableCount) ?></td></tr>
        <tr><th>Jumlah perintah SQL</th><td><?= esc($statementCount) ?></td></tr>
    </table>
    <form method="post" action="/backup/pemulihan/proses" class="mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="restore_token" value="<?= esc($token) ?>">
        <label class="form-label">Ketik RESTORE untuk mengonfirmasi</label>
        <input class="form-control mb-3" name="confirm_restore" autocomplete="off" required pattern="RESTORE" placeholder="RESTORE">
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-warning" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>Pulihkan ke database percobaan</button>
            <a class="btn btn-outline-secondary" href="/backup">Batal</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>