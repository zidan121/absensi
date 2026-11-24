<?= $this->extend('layout') ?><?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2>Hasil Generator Fingerspot</h2><p class="text-secondary mb-0"><?= esc($filename) ?></p></div><a class="btn btn-success" href="/backup/fingerspot/download"><i class="bi bi-download me-1"></i>Unduh SQL</a></div>
<div class="alert alert-warning">Impor ke database Fingerspot Personnel yang sudah ada, bukan database NEURO. Backup database Fingerspot tujuan dan uji dahulu pada salinan database.</div>
<div class="row g-3 mb-3">
<?php foreach (['master'=>'Perubahan master','skipped_master'=>'Pegawai dilewati','logs'=>'Scan disiapkan','skipped_logs'=>'Scan dilewati'] as $key=>$label): ?><div class="col-6 col-lg-3"><div class="card p-3"><div class="small text-secondary"><?= esc($label) ?></div><div class="fs-3 fw-semibold"><?= esc((string)$summary[$key]) ?></div></div></div><?php endforeach ?>
</div>
<div class="card p-3"><h5>Laporan pemetaan dan validasi</h5><div class="table-responsive"><table class="table table-sm table-striped align-middle"><thead><tr><th>PIN</th><th>Nama / Data</th><th>Catatan</th></tr></thead><tbody><?php foreach ($report as $row): ?><tr><td><?= esc($row['pin']) ?></td><td><?= esc($row['name']) ?></td><td><?= esc($row['reason']) ?></td></tr><?php endforeach ?></tbody></table></div></div>
<a class="btn btn-outline-secondary mt-3" href="/backup/fingerspot">Kembali ke generator</a>
<?= $this->endSection() ?>