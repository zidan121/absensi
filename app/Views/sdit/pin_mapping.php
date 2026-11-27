<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading"><h1>Pemetaan PIN SDIT</h1><p class="text-secondary">Master pegawai dan PIN yang khusus untuk unit SDIT.</p></div>
<div class="alert alert-info">Master pegawai SDIT belum diimpor. Tahap berikutnya adalah impor tabel <code>pegawai</code> dari backup SQL ke tabel <code>sdit_employees</code>.</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>No.</th><th>PIN</th><th>Kode Pegawai</th><th>Nama</th><th>Jabatan</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $i => $row): ?><tr><td><?= $i + 1 ?></td><td class="fw-bold"><?= esc($row['pin']) ?></td><td><?= esc($row['employee_code'] ?? '-') ?></td><td><?= esc($row['full_name']) ?></td><td><?= esc($row['position'] ?? '-') ?></td><td><?= $row['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="6" class="text-center text-secondary py-4">Master pegawai SDIT belum tersedia.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>