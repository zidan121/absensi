<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">Pengaturan Absensi</h2>
        <p class="text-secondary mb-0">Atur batas keterlambatan per unit dengan tanggal mulai berlaku.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/rekap">Kembali ke Rekap</a>
</div>

<div class="alert alert-info">
    Aturan yang sudah berlaku tidak ditimpa. Saat aturan baru disimpan, aturan sebelumnya otomatis berakhir sehari sebelum tanggal mulai aturan baru. Jadwal khusus pegawai tetap menjadi prioritas tertinggi.
</div>

<div class="card p-3 mb-4">
    <h5 class="mb-3">Tambah / Ubah Batas Keterlambatan</h5>
    <form method="post" action="/pengaturan-absensi/simpan-unit">
        <?= csrf_field() ?>
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Unit</label>
                <select class="form-select" name="unit_code" required>
                    <?php foreach ($units as $code): ?>
                        <option value="<?= esc($code) ?>"><?= esc($code === 'YAYASAN' ? 'Yayasan' : $code) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Batas tidak terlambat</label>
                <input class="form-control" type="time" name="late_after" step="1" value="07:45:59" required>
                <small class="text-secondary">Contoh: 07:45:59</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mulai berlaku tanggal</label>
                <input class="form-control" type="date" name="effective_from" value="<?= esc(date('Y-m-d', strtotime('tomorrow'))) ?>" required>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit">Simpan Aturan</button>
            </div>
        </div>
    </form>
</div>

<div class="card p-3 mb-4">
    <h5 class="mb-3">Aturan Terbaru per Unit</h5>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Unit</th><th>Batas tidak terlambat</th><th>Mulai berlaku</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($units as $code): ?>
                <?php $rule = $latest[$code] ?? null; ?>
                <tr>
                    <td><?= esc($code === 'YAYASAN' ? 'Yayasan' : $code) ?></td>
                    <td><?= $rule ? esc($rule['late_after']) : '-' ?></td>
                    <td><?= $rule ? esc($rule['effective_from']) : '-' ?></td>
                    <td><?= $rule && empty($rule['effective_to']) ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Tidak aktif</span>' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3">
    <h5 class="mb-3">Riwayat Aturan</h5>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead><tr><th>Unit</th><th>Batas</th><th>Berlaku mulai</th><th>Berlaku sampai</th></tr></thead>
            <tbody>
            <?php foreach ($rules as $rule): ?>
                <tr>
                    <td><?= esc($rule['unit_code']) ?></td>
                    <td><?= esc($rule['late_after']) ?></td>
                    <td><?= esc($rule['effective_from']) ?></td>
                    <td><?= esc($rule['effective_to'] ?: 'Tidak dibatasi') ?></td>
                </tr>
            <?php endforeach ?>
            <?php if (!$rules): ?><tr><td colspan="4" class="text-center text-secondary">Belum ada aturan.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
