<?= $this->extend('layout') ?><?= $this->section('content') ?>
<h2>Pratinjau &amp; Penyuntingan Master Pegawai</h2>
<p class="text-secondary"><?= esc($filename) ?> · Sumber backup: <?= esc($unit) ?></p>
<div class="alert alert-info">Periksa dan ubah unit, nama, status, serta tanggal kerja sebelum menyimpan. Untuk backup RAIT/KBIT: PIN <strong>18, 19, 20, 21, 22, 25, dan 31</strong> otomatis ditempatkan di <strong>KBIT</strong>. Semua PIN lainnya otomatis ditempatkan di <strong>RAIT</strong>. Silakan periksa kembali sebelum menyimpan.</div>
<div class="row g-3 mb-4">
<div class="col-md-4"><div class="card p-3"><div class="text-secondary">Baris ditemukan</div><strong class="fs-3"><?= count($preview['records']) + $preview['invalid'] ?></strong></div></div>
<div class="col-md-4"><div class="card p-3"><div class="text-secondary">Pegawai valid</div><strong class="fs-3 text-success"><?= count($preview['records']) ?></strong></div></div>
<div class="col-md-4"><div class="card p-3"><div class="text-secondary">Baris dilewati</div><strong class="fs-3 text-danger"><?= $preview['invalid'] ?></strong></div></div>
</div>
<form method="post" action="/impor-backup/master/proses">
<?= csrf_field() ?>
<input type="hidden" name="token" value="<?= esc($token) ?>">
<input type="hidden" name="unit_code" value="<?= esc($unit) ?>">
<div class="card p-3 mb-3">
<div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Edit data pegawai (<?= count($preview['records']) ?>)</h5><small class="text-secondary">PIN fingerprint tidak dapat diubah</small></div>
<div class="table-responsive"><table class="table table-sm table-hover align-middle">
<thead><tr><th>PIN</th><th>Nama pegawai</th><th>Unit penempatan</th><th>Status aktif</th><th>Mulai kerja</th><th>Tanggal berhenti</th><th>Divisi sumber</th></tr></thead><tbody>
<?php foreach ($preview['records'] as $row):
$pin = (string) $row['fingerprint_pin'];
$division1 = (int) ($row['source_division_1'] ?? 0);
$division2 = (int) ($row['source_division_2'] ?? 0);
$division3 = (int) ($row['source_division_3'] ?? 0);
$division1Names = [1 => 'Manager', 2 => 'Staff', 3 => 'Admin', 4 => 'Guru', 5 => 'Masak'];
$division2Names = [1 => 'HRD', 2 => 'Keuangan', 3 => 'Penjualan', 4 => 'IT', 5 => 'Guru', 6 => 'Dapur'];
$division3Names = [1 => 'Head Office', 2 => 'Surabaya', 3 => 'Jakarta', 4 => 'Denpasar', 5 => 'Jogja', 6 => 'Semarang', 7 => 'Malang', 8 => 'Purwodadi'];
$kbitPins = ['18', '19', '20', '21', '22', '25', '31'];
$defaultUnit = $unit === 'SMPIT' ? 'SMPIT' : (in_array($pin, $kbitPins, true) ? 'KBIT' : 'RAIT');
?>
<tr><td><strong><?= esc($pin) ?></strong></td>
<td><input class="form-control form-control-sm" required name="employee_data[<?= esc($pin) ?>][full_name]" value="<?= esc($row['full_name']) ?>"></td>
<td><select class="form-select form-select-sm" name="employee_data[<?= esc($pin) ?>][unit_code]">
<?php foreach (['SMPIT' => 'SMPIT', 'RAIT' => 'RAIT', 'KBIT' => 'KBIT', 'RAIT_KBIT' => 'RAIT + KBIT (gabungan)', 'YAYASAN' => 'Yayasan'] as $value => $label): ?>
<option value="<?= esc($value) ?>" <?= $defaultUnit === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
<?php endforeach ?></select></td>
<td><input type="checkbox" class="form-check-input" name="employee_data[<?= esc($pin) ?>][is_active]" value="1" <?= ! empty($row['is_active']) ? 'checked' : '' ?>></td>
<td><input type="date" class="form-control form-control-sm" name="employee_data[<?= esc($pin) ?>][employment_start]" value="<?= esc($row['employment_start'] ?? '') ?>"></td>
<td><input type="date" class="form-control form-control-sm" name="employee_data[<?= esc($pin) ?>][employment_end]" value="<?= esc($row['employment_end'] ?? '') ?>"></td>
<td><small><?= esc($division1Names[$division1] ?? 'ID '.$division1) ?> / <?= esc($division2Names[$division2] ?? 'ID '.$division2) ?> / <?= esc($division3Names[$division3] ?? 'ID '.$division3) ?></small></td></tr>
<?php endforeach ?></tbody></table></div></div>
<button class="btn btn-success" type="submit">Simpan master pegawai sesuai hasil edit</button>
<a class="btn btn-outline-secondary" href="/impor-backup">Batal</a>
</form>
<?= $this->endSection() ?>