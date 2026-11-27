<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php if(session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if(session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<div class="page-heading"><h1>Jadwal Khusus Pegawai SDIT</h1><p class="text-secondary">Jadwal ini mengesampingkan jadwal mingguan SDIT untuk PIN dan hari yang dipilih, selama periode berlaku.</p></div>
<div class="card p-3 mb-3">
<h5>Tambah jadwal pegawai</h5>
<?php if(!$employees): ?><div class="alert alert-warning mb-0">Master pegawai SDIT belum tersedia. Impor data pegawai terlebih dahulu.</div><?php else: ?>
<form method="post" action="/sdit/jadwal-pegawai/simpan" class="row g-3">
<?= csrf_field() ?>
<div class="col-md-4"><label class="form-label">Pegawai</label><select class="form-select" name="pin" required><option value="">Pilih pegawai</option><?php foreach($employees as $e): ?><option value="<?= esc($e['pin']) ?>"><?= esc(($e['employee_code'] ?: $e['pin']).' · '.$e['full_name'].' (PIN '.$e['pin'].')') ?></option><?php endforeach ?></select></div>
<div class="col-md-2"><label class="form-label">Hari</label><select class="form-select" name="day_of_week"><?php foreach($days as $n=>$day): ?><option value="<?= $n ?>"><?= esc($day) ?></option><?php endforeach ?></select></div>
<div class="col-md-2"><label class="form-label">Jam masuk</label><input class="form-control" type="time" name="start_time" value="07:30" required></div>
<div class="col-md-2"><label class="form-label">Jam pulang</label><input class="form-control" type="time" name="end_time" value="16:00" required></div>
<div class="col-md-2"><label class="form-label">Toleransi (menit)</label><input class="form-control" type="number" name="late_after_minutes" value="0" min="0" max="240"></div>
<div class="col-md-3"><label class="form-label">Berlaku mulai</label><input class="form-control" type="date" name="effective_from"></div>
<div class="col-md-3"><label class="form-label">Berlaku sampai</label><input class="form-control" type="date" name="effective_until"></div>
<div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_workday" value="1" checked id="employeeWorkday"><label class="form-check-label" for="employeeWorkday">Hari kerja</label></div></div>
<div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100">Simpan Jadwal Pegawai</button></div>
</form><?php endif ?>
</div>
<div class="card"><div class="card-header"><strong>Daftar jadwal khusus</strong></div><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead><tr><th>Kode</th><th>Nama</th><th>PIN</th><th>Hari</th><th>Jam</th><th>Toleransi</th><th>Periode</th><th>Jenis</th><th></th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><?= esc($row['employee_code']??'-') ?></td><td><?= esc($row['full_name']??'Pegawai tidak ditemukan') ?></td><td><?= esc($row['pin']) ?></td><td><?= esc($days[(int)$row['day_of_week']]??'-') ?></td><td><?= esc(substr($row['start_time'],0,5).'–'.substr($row['end_time'],0,5)) ?></td><td><?= (int)$row['late_after_minutes'] ?> menit</td><td><?= esc($row['effective_from']?:'Tanpa batas awal') ?> – <?= esc($row['effective_until']?:'Tanpa batas akhir') ?></td><td><?= $row['is_workday']?'Hari kerja':'Libur' ?></td><td><form method="post" action="/sdit/jadwal-pegawai/hapus/<?= (int)$row['id'] ?>" onsubmit="return confirm('Hapus jadwal khusus ini?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr><?php endforeach ?>
<?php if(!$rows): ?><tr><td colspan="9" class="text-center text-secondary py-4">Belum ada jadwal khusus pegawai.</td></tr><?php endif ?>
</tbody></table></div></div>
<div class="alert alert-info mt-3 small">Jika tidak ada jadwal khusus yang cocok untuk PIN, hari, dan tanggal, sistem memakai jadwal mingguan SDIT.</div>
<?= $this->endSection() ?>