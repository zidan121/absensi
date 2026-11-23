<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div><h2 class="mb-1">Edit Data Pegawai</h2><p class="text-secondary mb-0">Perbarui identitas, unit, jabatan, dan status pegawai.</p></div>
    <a class="btn btn-outline-secondary" href="/pegawai<?= $returnUnit !== 'ALL' ? '?unit=' . urlencode($returnUnit) : '' ?>">Kembali</a>
</div>
<div class="card p-4">
    <form method="post" action="/pegawai/<?= (int) $employee['id'] ?>/update">
        <?= csrf_field() ?>
        <input type="hidden" name="return_unit" value="<?= esc($returnUnit) ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Kode pegawai</label>
                <input class="form-control" name="employee_code" maxlength="40" required value="<?= esc(old('employee_code', $employee['employee_code'])) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">PIN fingerprint</label>
                <input class="form-control" value="<?= esc($employee['fingerprint_pin'] ?? '-') ?>" readonly>
                <div class="form-text">PIN sumber fingerprint tidak dapat diubah dari halaman ini.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nama lengkap</label>
                <input class="form-control" name="full_name" maxlength="150" required value="<?= esc(old('full_name', $employee['full_name'])) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Unit</label>
                <select class="form-select" name="unit_code" required>
                    <?php foreach ($units as $code => $label): ?>
                        <option value="<?= esc($code) ?>" <?= old('unit_code', $employee['unit_code'] ?? '') === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Jabatan</label>
                <input class="form-control" name="position" value="<?= esc(old('position', $employee['position'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="is_active">
                    <option value="1" <?= (string) old('is_active', $employee['is_active']) === '1' ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= (string) old('is_active', $employee['is_active']) === '0' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tanggal mulai kerja</label>
                <input type="date" class="form-control" name="employment_start" value="<?= esc(old('employment_start', $employee['employment_start'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Tanggal berhenti</label>
                <input type="date" class="form-control" name="employment_end" value="<?= esc(old('employment_end', $employee['employment_end'] ?? '')) ?>">
            </div>
        </div>
        <hr class="my-4">
        <button class="btn btn-primary" type="submit">Simpan perubahan</button>
        <a class="btn btn-light" href="/pegawai<?= $returnUnit !== 'ALL' ? '?unit=' . urlencode($returnUnit) : '' ?>">Batal</a>
    </form>
</div>
<?= $this->endSection() ?>
