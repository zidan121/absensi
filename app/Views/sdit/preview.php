<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div><div class="text-uppercase fw-bold text-primary mb-2" style="font-size:10px;letter-spacing:.12em">Modul SDIT · Hasil Pembacaan</div><h1 class="mb-2">Pratinjau BIN SDIT</h1><p class="text-secondary mb-0"><?= esc($originalName) ?> · <?= number_format($fileSize) ?> byte</p></div>
    <a href="/sdit/impor" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Unggah Ulang</a>
</div>
<div class="row g-3 mb-4">
    <?php foreach ([['Total Record',$preview['total'],'bi-database'],['Record Waktu Valid',$preview['valid'],'bi-check-circle'],['Waktu Tidak Valid',$preview['invalid'],'bi-exclamation-triangle'],['PIN Unik',$preview['unique_pins'],'bi-people'],['Duplikat',$preview['duplicates'],'bi-files']] as $stat): ?>
    <div class="col-6 col-md">
        <div class="card h-100"><div class="card-body p-3"><div class="text-secondary small mb-2"><i class="bi <?= esc($stat[2]) ?> me-1"></i><?= esc($stat[0]) ?></div><div class="fs-4 fw-bold text-primary"><?= number_format($stat[1]) ?></div></div></div>
    </div>
    <?php endforeach ?>
</div>
<div class="alert alert-warning">
    <strong>Perhatian:</strong> Record bertanggal 1 Januari 2017 tetap memakai waktu asli. Data tersebut hanya ditampilkan untuk pemeriksaan, tidak dimasukkan ke daftar waktu valid, dan tidak dikoreksi otomatis.
</div>
<div class="card mb-4">
    <div class="card-header py-3">Record dengan waktu valid <span class="badge text-bg-success ms-2"><?= number_format($preview['valid']) ?></span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>No.</th><th>PIN</th><th>Tanggal</th><th>Jam</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($preview['records'], 0, 100) as $row): ?>
                <tr><td><?= esc($row['record_no']) ?></td><td class="fw-bold"><?= esc($row['pin']) ?></td><td><?= esc(substr($row['scanned_at'],0,10)) ?></td><td><?= esc(substr($row['scanned_at'],11,8)) ?></td><td><span class="badge text-bg-success">Valid</span></td></tr>
            <?php endforeach ?>
            <?php if ($preview['records'] === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">Tidak ada record dengan waktu valid.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if (count($preview['records']) > 100): ?><div class="card-footer text-secondary small">Menampilkan 100 dari <?= number_format($preview['valid']) ?> record. Belum ada data yang disimpan.</div><?php endif ?>
</div>
<div class="card mb-4">
    <div class="card-header py-3">Waktu mesin tidak valid <span class="badge text-bg-warning ms-2"><?= number_format($preview['invalid']) ?></span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>No. Record</th><th>PIN</th><th>Waktu Asli</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($preview['invalid_records'] as $row): ?>
                <tr><td><?= esc($row['record_no']) ?></td><td class="fw-bold"><?= esc($row['pin']) ?></td><td><?= esc($row['original_scanned_at'] ?? 'Tanggal/jam tidak terbaca') ?></td><td><span class="badge text-bg-warning">Waktu Mesin Tidak Valid</span></td></tr>
            <?php endforeach ?>
            <?php if ($preview['invalid_records'] === []): ?><tr><td colspan="4" class="text-center text-secondary py-4">Tidak ada waktu tidak valid.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>Ini masih tahap pratinjau. Belum ada data yang masuk ke database. Periksa hasil pembacaan terlebih dahulu.</div>
<?= $this->endSection() ?>
