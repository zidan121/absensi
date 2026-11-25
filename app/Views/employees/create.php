<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h2>Tambah Pegawai</h2>
<div class="alert alert-info mt-3">
    <strong>PIN fingerprint dibuat otomatis.</strong>
    NEURO ABSENSI akan memberikan PIN numerik berikutnya yang belum digunakan di seluruh data pegawai.
    Untuk SMPIT, perangkat diarahkan ke mesin SMPIT; untuk RAIT/KBIT diarahkan ke mesin RA&KB.
    Anda tidak perlu membuat PIN yang sama lagi secara manual di NEURO.
</div>
<div class="card p-4">
    <form method="post" action="/pegawai/simpan">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Unit</label>
            <select class="form-select" name="unit_code" required>
                <option value="">Pilih unit</option>
                <?php foreach (['KBIT' => 'KBIT', 'RAIT' => 'RAIT', 'SDIT' => 'SDIT', 'SMPIT' => 'SMPIT', 'YAYASAN' => 'Yayasan'] as $code => $label): ?>
                    <option value="<?= esc($code) ?>" <?= old('unit_code') === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Kode pegawai</label><input class="form-control" name="employee_code" value="<?= old('employee_code') ?>" required></div>
        <div class="mb-3"><label class="form-label">Nama lengkap</label><input class="form-control" name="full_name" value="<?= old('full_name') ?>" required></div>
        <div class="mb-3"><label class="form-label">Jabatan</label><input class="form-control" name="position" value="<?= old('position') ?>"></div>
        <button class="btn btn-primary">Simpan pegawai & buat PIN</button> <a class="btn btn-light" href="/pegawai">Batal</a>
    </form>
</div>
<?= $this->endSection() ?>
