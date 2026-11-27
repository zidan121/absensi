<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading"><h1>Pemeriksaan Waktu Mesin SDIT</h1><p class="text-secondary">Catatan waktu tidak valid disimpan apa adanya dan tidak dikoreksi otomatis.</p></div>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Total catatan waktu tidak valid</div><div class="fs-3 fw-bold"><?= number_format($totalInvalid) ?></div></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Hasil filter</div><div class="fs-3 fw-bold"><?= number_format(count($rows)) ?></div></div></div></div></div>
<div class="card mb-3"><div class="card-body"><form method="get" class="row g-2 align-items-end">
<div class="col-sm-3"><label class="form-label">Dari tanggal</label><input type="date" name="start_date" class="form-control" value="<?= esc($startDate) ?>"></div>
<div class="col-sm-3"><label class="form-label">Sampai tanggal</label><input type="date" name="end_date" class="form-control" value="<?= esc($endDate) ?>"></div>
<div class="col-sm-2"><label class="form-label">PIN</label><input name="pin" class="form-control" value="<?= esc($pin) ?>" placeholder="Semua PIN"></div>
<div class="col-auto"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="/sdit/pemeriksaan-waktu">Reset</a></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>No.</th><th>PIN</th><th>Waktu Asli Mesin</th><th>Keterangan</th><th>Format</th><th>Baris Sumber</th><th>Impor</th></tr></thead><tbody>
<?php foreach ($rows as $i => $row): ?><tr><td><?= $i + 1 ?></td><td class="fw-bold"><?= esc($row['pin']) ?></td><td><?= esc($row['original_scanned_at']) ?></td><td><span class="badge text-bg-warning"><?= esc($row['reason']) ?></span></td><td><?= esc($row['source_format']) ?></td><td><?= esc($row['source_row'] ?? '-') ?></td><td><?= esc($row['import_batch_id'] ?? '-') ?></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada catatan waktu tidak valid untuk filter ini.</td></tr><?php endif ?>
</tbody></table></div><div class="card-footer small text-secondary">Menampilkan maksimal 1.000 catatan terbaru. Waktu asli tidak diubah.</div></div>
<?= $this->endSection() ?>