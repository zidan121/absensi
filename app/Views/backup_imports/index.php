<?= $this->extend('layout') ?><?= $this->section('content') ?>
<h2>Impor Backup Fingerspot</h2>
<p class="text-secondary">Master pegawai dan riwayat scan diproses terpisah. File SQL hanya dibaca sebagai sumber data; tidak dieksekusi ke database aplikasi.</p>
<div class="row g-4">
<div class="col-lg-6"><div class="card p-4 h-100"><h4>1. Master Pegawai</h4><p class="text-secondary">Mengambil PIN, NIP, nama, status, pembagian, dan tanggal kerja dari tabel <code>pegawai</code>.</p>
<form method="post" action="/impor-backup/master/preview" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label">Sumber backup</label><select class="form-select" name="unit_code" required><option value="SMPIT">SMPIT</option><option value="RAIT_KBIT">RAIT & KBIT (gabungan)</option></select></div>
<div class="mb-3"><label class="form-label">File backup SQL</label><input class="form-control" type="file" name="backup_file" accept=".sql" required></div><button class="btn btn-primary">Baca master pegawai</button></form></div></div>
<div class="col-lg-6"><div class="card p-4 h-100"><h4>2. Riwayat Scan</h4><p class="text-secondary">Mengambil seluruh scan dari tabel <code>att_log</code>, termasuk tanggal lama. Duplikat akan dilewati.</p>
<form method="post" action="/impor-backup/logs/preview" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label">Mesin asal data</label><select class="form-select" name="device_id" required><option value="">Pilih mesin</option><?php foreach ($devices as $device): ?><option value="<?= esc($device['id']) ?>"><?= esc($device['device_name']) ?> — <?= esc($device['model_name']) ?> (<?= esc($device['unit_scope']) ?>)</option><?php endforeach ?></select></div>
<div class="mb-3"><label class="form-label">File backup SQL</label><input class="form-control" type="file" name="backup_file" accept=".sql" required></div><button class="btn btn-outline-primary">Baca riwayat scan</button></form></div></div>
</div>
<div class="alert alert-warning mt-4">Penting: jangan memilih mesin yang salah. PIN dari dua unit bisa sama, sehingga riwayat scan harus ditautkan ke perangkat asal yang benar. Backup asli tetap simpan sebagai arsip.</div>
<?= $this->endSection() ?>