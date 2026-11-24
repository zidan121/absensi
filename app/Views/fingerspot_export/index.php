<?= $this->extend('layout') ?><?= $this->section('content') ?>
<div class="mb-4"><h2>Generator Backup Fingerspot</h2><p class="text-secondary mb-0">Buat SQL merge dari data aktif NEURO ABSENSI dan arsip Fingerspot.</p></div>
<div class="alert alert-warning"><strong>Sebelum impor:</strong> backup database Fingerspot tujuan. File ini bukan pengganti seluruh database; hanya mengubah tabel pegawai dan menambahkan scan ke att_log.</div>
<div class="row g-3">
<div class="col-lg-6"><div class="card p-4 h-100"><h4>Backup SMPIT</h4><p class="text-secondary">Arsip sumber SMPIT dan data mesin Revo-16B.</p><form method="post" action="/backup/fingerspot/generate"><?= csrf_field() ?><input type="hidden" name="source" value="smp"><button class="btn btn-primary"><i class="bi bi-gear me-1"></i>Generate SQL SMPIT</button></form></div></div>
<div class="col-lg-6"><div class="card p-4 h-100"><h4>Backup RA&KB</h4><p class="text-secondary">Arsip sumber RA&KB dan data mesin Revo-185BNC.</p><form method="post" action="/backup/fingerspot/generate"><?= csrf_field() ?><input type="hidden" name="source" value="rakb"><button class="btn btn-primary"><i class="bi bi-gear me-1"></i>Generate SQL RA&KB</button></form></div></div>
</div>
<div class="alert alert-info mt-4 mb-0">Pegawai baru hanya dimasukkan jika PIN unik dan pemetaan pembagian1/2/3 lengkap atau dapat ditentukan secara tunggal dari unit yang sama. Jika belum, pegawai ditampilkan di laporan dan dilewati.</div>
<?= $this->endSection() ?>