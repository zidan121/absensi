<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php if(session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if(session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<div class="page-heading"><h1>Jadwal Kerja SDIT</h1><p class="text-secondary">Jadwal ini menjadi aturan mingguan default SDIT. Jadwal khusus pegawai dan kalender tanggal tertentu tetap dapat mengesampingkannya.</p></div>
<form method="post" action="/sdit/jadwal/simpan" class="card p-3">
<?= csrf_field() ?>
<div class="table-responsive"><table class="table align-middle">
<thead><tr><th>Hari</th><th>Hari kerja</th><th>Jam masuk</th><th>Jam pulang</th><th>Toleransi (menit)</th></tr></thead><tbody>
<?php foreach($days as $number=>$label): $s=$schedules[$number]??['is_workday'=>($number<=5?1:0),'start_time'=>'07:30:00','end_time'=>'16:00:00','late_after_minutes'=>0]; ?>
<tr><td class="fw-semibold"><?= esc($label) ?></td>
<td><input class="form-check-input" type="checkbox" name="days[<?= $number ?>][is_workday]" value="1" <?= $s['is_workday']?'checked':'' ?>></td>
<td><input class="form-control" type="time" name="days[<?= $number ?>][start_time]" value="<?= esc(substr($s['start_time'],0,5)) ?>"></td>
<td><input class="form-control" type="time" name="days[<?= $number ?>][end_time]" value="<?= esc(substr($s['end_time'],0,5)) ?>"></td>
<td><input class="form-control" type="number" min="0" max="240" required name="days[<?= $number ?>][late_after_minutes]" value="<?= (int)$s['late_after_minutes'] ?>"></td></tr>
<?php endforeach ?>
</tbody></table></div>
<div class="alert alert-info mt-3 mb-3 small">Default awal: Senin–Jumat hari kerja, Sabtu–Minggu libur. Silakan ubah centang hari kerja sesuai kebijakan SDIT. Jadwal ini tidak mengubah jadwal unit lain.</div>
<div class="d-flex gap-2"><button class="btn btn-primary">Simpan Jadwal SDIT</button><a href="/sdit" class="btn btn-outline-secondary">Kembali</a></div>
</form>
<div class="text-secondary small mt-3">Prioritas pengaturan: jadwal khusus pegawai → kalender tanggal tertentu → jadwal mingguan SDIT.</div>
<?= $this->endSection() ?>