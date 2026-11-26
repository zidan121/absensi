<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div><h2 class="mb-1">Pemetaan Perangkat Fingerprint</h2><p class="text-secondary mb-0"><?= esc($employee['employee_code']) ?> — <?= esc($employee['full_name']) ?></p></div>
    <a class="btn btn-outline-secondary" href="/pegawai/<?= (int) $employee['id'] ?>/edit">Kembali ke data pegawai</a>
</div>
<div class="alert alert-info">Satu pegawai dapat memiliki PIN berbeda pada beberapa mesin. PIN hanya harus unik di dalam perangkat yang sama. Riwayat perubahan disimpan; pemetaan lama tidak otomatis dihapus dari riwayat.</div>
<div class="card p-4 mb-3">
    <h5 class="mb-3">Tambah / perbarui pemetaan</h5>
    <form method="post" action="/pegawai/<?= (int) $employee['id'] ?>/perangkat/simpan">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Perangkat</label><select class="form-select" name="device_id" required><option value="">Pilih perangkat</option><?php foreach ($devices as $device): ?><option value="<?= (int) $device['id'] ?>" <?= (string) old('device_id') === (string) $device['id'] ? 'selected' : '' ?>><?= esc($device['device_name']) ?> (<?= esc($device['device_code']) ?>)<?= empty($device['is_active']) ? ' — nonaktif' : '' ?></option><?php endforeach ?></select></div>
            <div class="col-md-6"><label class="form-label">PIN pada perangkat</label><input class="form-control" name="fingerprint_pin" inputmode="numeric" pattern="[0-9]{1,32}" maxlength="32" value="<?= esc(old('fingerprint_pin')) ?>" required></div>
            <div class="col-md-6"><label class="form-label">ID pegawai pada Fingerspot (opsional)</label><input class="form-control" type="number" min="0" name="target_pegawai_id" value="<?= esc(old('target_pegawai_id')) ?>"><div class="form-text">Isi jika sudah mengetahui pegawai_id pada database Fingerspot.</div></div>
            <div class="col-md-6"><label class="form-label">Berlaku mulai</label><input class="form-control" type="date" name="effective_from" value="<?= esc(old('effective_from', date('Y-m-d'))) ?>"></div>
            <div class="col-12"><label class="form-label">Catatan perubahan (opsional)</label><input class="form-control" name="notes" maxlength="1000" value="<?= esc(old('notes')) ?>"></div>
        </div>
        <button class="btn btn-primary mt-3" type="submit">Simpan pemetaan</button>
    </form>
</div>
<div class="card p-3"><h5>Pemetaan aktif</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Perangkat</th><th>Kode</th><th>PIN</th><th>ID Fingerspot</th><th>Berlaku mulai</th></tr></thead><tbody>
<?php foreach ($mappings as $mapping): ?><tr><td><?= esc($mapping['device_name']) ?></td><td><?= esc($mapping['device_code']) ?></td><td><span class="badge text-bg-light"><?= esc($mapping['fingerprint_pin']) ?></span></td><td><?= esc($mapping['target_pegawai_id'] ?? '-') ?></td><td><?= esc($mapping['effective_from'] ?? '-') ?></td></tr><?php endforeach ?>
<?php if (!$mappings): ?><tr><td colspan="5" class="text-center text-secondary">Belum ada pemetaan perangkat.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
