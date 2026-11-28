<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php if (session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">Atur Urutan Pegawai SDIT</h2>
        <p class="text-secondary mb-0">Ubah angka urutan sesuai susunan yang ingin ditampilkan pada Master Pegawai, Rekap, dan ekspor Excel.</p>
    </div>
    <a href="/sdit/pegawai" class="btn btn-outline-secondary">Kembali ke Master Pegawai</a>
</div>

<div class="alert alert-info">
    <strong>Petunjuk:</strong> Isi nomor 1 untuk pegawai pertama, 2 untuk pegawai kedua, dan seterusnya. Setiap nomor harus unik. Tekan Simpan Urutan setelah selesai.
</div>

<form method="post" action="/sdit/pegawai/urutan/simpan">
    <?= csrf_field() ?>
    <div class="card p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="mb-0">Daftar urutan</h5>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Urutan</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th style="width:90px">Urutan</th><th>Kode</th><th>PIN</th><th>Nama Pegawai</th><th>Nama Fingerprint</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($employees as $i => $employee): ?>
                    <tr>
                        <td>
                            <input class="form-control text-center" type="number" min="1" max="9999"
                                name="display_order[<?= (int)$employee['id'] ?>]"
                                value="<?= esc($employee['display_order'] ?? ($i + 1)) ?>" required>
                        </td>
                        <td><?= esc($employee['employee_code'] ?: '-') ?></td>
                        <td><?= esc($employee['pin']) ?></td>
                        <td class="fw-semibold"><?= esc($employee['full_name']) ?></td>
                        <td><?= esc($employee['fingerprint_name'] ?: '-') ?></td>
                        <td><?= !empty($employee['is_active']) ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$employees): ?><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada data pegawai.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Simpan Urutan</button>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
