<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php if(session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if(session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<div class="page-heading"><h1>Kalender Kerja SDIT</h1><p class="text-secondary">Atur hari libur khusus, hari kerja pengganti, dan izin masuk siang.</p></div>
<div class="card p-3 mb-3">
<h5>Tambah / Ubah tanggal</h5>
<form method="post" action="/sdit/kalender/simpan" class="row g-2 align-items-end">
<?= csrf_field() ?>
<div class="col-md-2"><label class="form-label">Tanggal</label><input class="form-control" type="date" name="work_date" required></div>
<div class="col-md-2"><label class="form-label">Jenis hari</label><select class="form-select" name="day_type"><option value="holiday">Hari libur</option><option value="workday">Hari kerja</option></select></div>
<div class="col-md-4"><label class="form-label">Keterangan</label><input class="form-control" name="description" maxlength="255" placeholder="Contoh: Libur nasional / kegiatan sekolah"></div>
<div class="col-md-2"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="allow_afternoon_entry" value="1" id="afternoon"><label class="form-check-label" for="afternoon">Izin masuk siang</label></div></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Simpan</button></div>
</form></div>
<form method="get" class="d-flex gap-2 align-items-end mb-3"><div><label class="form-label">Bulan</label><input class="form-control" type="month" name="bulan" value="<?= esc($month) ?>"></div><button class="btn btn-outline-primary">Tampilkan</button></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th>Izin masuk siang</th><th></th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><?= esc(date('d-m-Y',strtotime($row['work_date']))) ?></td><td><?= $row['day_type']==='holiday'?'Hari Libur':'Hari Kerja' ?></td><td><?= esc($row['description']??'-') ?></td><td><?= $row['allow_afternoon_entry']?'Ya':'Tidak' ?></td><td><form method="post" action="/sdit/kalender/hapus/<?= (int)$row['id'] ?>" onsubmit="return confirm('Hapus pengaturan tanggal ini?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr><?php endforeach ?>
<?php if(!$rows): ?><tr><td colspan="5" class="text-center text-secondary py-4">Belum ada pengaturan kalender pada bulan ini.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>