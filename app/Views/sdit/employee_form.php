<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $e = $employee ?? []; ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="mb-1"><?= esc($title) ?></h2><p class="text-secondary mb-0">Data ini hanya tersimpan pada master pegawai SDIT.</p></div><a href="/sdit/pegawai" class="btn btn-outline-secondary">Kembali</a></div>
<form method="post" action="<?= $employee ? '/sdit/pegawai/'.(int)$employee['id'].'/update' : '/sdit/pegawai/simpan' ?>" class="card p-3">
<?= csrf_field() ?>
<div class="row g-3">
    <div class="col-md-4"><label class="form-label">PIN Fingerprint <span class="text-danger">*</span></label><input class="form-control" name="pin" required value="<?= esc(old('pin', $e['pin'] ?? '')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Kode Pegawai</label><input class="form-control" name="employee_code" value="<?= esc(old('employee_code', $e['employee_code'] ?? '')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Jabatan</label><input class="form-control" name="position" value="<?= esc(old('position', $e['position'] ?? '')) ?>" placeholder="Guru, Yayasan, Dapur, dll."></div>
    <div class="col-md-6"><label class="form-label">Nama Pegawai <span class="text-danger">*</span></label><input class="form-control" name="full_name" required value="<?= esc(old('full_name', $e['full_name'] ?? '')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Nama di Fingerprint / REKAB</label><input class="form-control" name="fingerprint_name" value="<?= esc(old('fingerprint_name', $e['fingerprint_name'] ?? '')) ?>" placeholder="Kosongkan untuk memakai nama pegawai"></div>
    <div class="col-md-4"><label class="form-label">Nomor HP</label><input class="form-control" name="phone" value="<?= esc(old('phone', $e['phone'] ?? '')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Mulai Bekerja</label><input class="form-control" type="date" name="employment_start" value="<?= esc(old('employment_start', $e['employment_start'] ?? '')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Tanggal Berhenti</label><input class="form-control" type="date" name="employment_end" value="<?= esc(old('employment_end', $e['employment_end'] ?? '')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="is_active"><option value="1" <?= old('is_active', (string)($e['is_active'] ?? '1'))==='1'?'selected':'' ?>>Aktif</option><option value="0" <?= old('is_active', (string)($e['is_active'] ?? '1'))==='0'?'selected':'' ?>>Nonaktif</option></select></div>
</div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Simpan Pegawai</button><a href="/sdit/pegawai" class="btn btn-outline-secondary">Batal</a></div>
</form>
<?= $this->endSection() ?>
