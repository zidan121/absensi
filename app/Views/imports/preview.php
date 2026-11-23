<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h2>Pratinjau Impor</h2>
<p class="text-secondary"><?= esc($originalName) ?> — <?= esc($device['device_name']) ?></p>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card p-3"><small>Scan valid</small><div class="h3"><?= count($preview['records']) ?></div></div></div>
    <div class="col-md-4"><div class="card p-3"><small>Baris invalid</small><div class="h3"><?= count($preview['invalid']) ?></div></div></div>
</div>
<?php $lastRecords = array_slice($preview['records'], -60); ?>
<div class="card p-3 mb-3">
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>PIN</th><th>Nama</th><th>Waktu scan</th><th>Mode</th><th>IN/OUT</th></tr></thead>
            <tbody>
                <?php foreach ($lastRecords as $record): ?>
                <tr>
                    <td><?= esc($record['pin']) ?></td>
                    <td><?= esc($record['employee_name_source']) ?></td>
                    <td><?= esc($record['scanned_at']) ?></td>
                    <td><?= esc($record['verify_mode']) ?></td>
                    <td><?= esc($record['inout_mode']) ?></td>
                </tr>
                <?php endforeach ?>
                <?php if (empty($lastRecords)): ?>
                <tr><td colspan="5" class="text-center text-secondary">Tidak ada scan valid untuk ditampilkan.</td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <small class="text-secondary">Menampilkan <?= count($lastRecords) ?> dari <?= count($preview['records']) ?> scan valid terakhir.</small>
</div>
<?php if (count($preview['invalid'])): ?>
<div class="alert alert-warning">Ada <?= count($preview['invalid']) ?> baris invalid. Baris tersebut tidak disimpan sebagai scan sah.</div>
<?php endif ?>
<form method="post" action="/impor/proses">
    <?= csrf_field() ?>
    <button class="btn btn-success" <?= empty($preview['records']) ? 'disabled' : '' ?>>Simpan scan valid</button>
    <a class="btn btn-light" href="/impor">Batalkan</a>
</form>
<?= $this->endSection() ?>
