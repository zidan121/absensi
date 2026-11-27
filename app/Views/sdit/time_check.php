<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading"><h1>Pemeriksaan Waktu Mesin SDIT</h1><p class="text-secondary">Catatan waktu tidak valid disimpan apa adanya dan tidak dikoreksi otomatis.</p></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>No.</th><th>PIN</th><th>Waktu Asli</th><th>Keterangan</th><th>Baris Sumber</th><th>Impor</th></tr></thead><tbody>
<?php foreach ($rows as $i => $row): ?><tr><td><?= $i + 1 ?></td><td class="fw-bold"><?= esc($row['pin']) ?></td><td><?= esc($row['original_scanned_at']) ?></td><td><span class="badge text-bg-warning"><?= esc($row['reason']) ?></span></td><td><?= esc($row['source_row'] ?? '-') ?></td><td><?= esc($row['import_batch_id'] ?? '-') ?></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada catatan waktu tidak valid.</td></tr><?php endif ?>
</tbody></table></div><div class="card-footer small text-secondary">Menampilkan maksimal 500 catatan terbaru.</div></div>
<?= $this->endSection() ?>