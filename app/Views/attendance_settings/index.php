<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">Pengaturan Absensi</h2>
        <p class="text-secondary mb-0">Atur batas keterlambatan per unit dan koreksi aturan lama jika ada kesalahan.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/rekap">Kembali ke Rekap</a>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="alert alert-info">
    Untuk koreksi aturan yang sudah ada, gunakan tombol Edit pada Riwayat Aturan. Kamu boleh mengubah tanggal lampau. Sistem mencegah periode aturan pada unit yang sama saling bertabrakan dan mencatat setiap perubahan.
</div>

<div class="card p-3 mb-4">
    <h5 class="mb-3">Tambah Aturan Baru</h5>
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
            </div>
            <div class="col-md-3">
                <label class="form-label">Mulai berlaku tanggal</label>
                <input class="form-control" type="date" name="effective_from" value="<?= esc(date('Y-m-d', strtotime('tomorrow'))) ?>" required>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit">Tambah Aturan</button>
            </div>
        </div>
        <small class="text-secondary">Untuk memperbaiki aturan bulan/tahun sebelumnya, edit aturan yang sudah ada di tabel riwayat di bawah.</small>
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
                    <td><?= $rule ? esc(date('d-m-Y', strtotime($rule['effective_from']))) : '-' ?></td>
                    <td><?= $rule && empty($rule['effective_to']) ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Riwayat</span>' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3 mb-4">
    <h5 class="mb-3">Riwayat Aturan — Edit atau Hapus jika Salah</h5>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead><tr><th>Unit</th><th>Batas</th><th>Berlaku mulai</th><th>Berlaku sampai</th><th style="min-width:190px">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($rules as $rule): ?>
                <tr>
                    <td><?= esc($rule['unit_code']) ?></td>
                    <td><?= esc($rule['late_after']) ?></td>
                    <td><?= esc(date('d-m-Y', strtotime($rule['effective_from']))) ?></td>
                    <td><?= esc($rule['effective_to'] ? date('d-m-Y', strtotime($rule['effective_to'])) : 'Tidak dibatasi') ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-rule-<?= (int) $rule['id'] ?>">Edit</button>
                        <?php if (count(array_filter($rules, static fn ($r) => $r['unit_code'] === $rule['unit_code'])) > 1): ?>
                        <form class="d-inline" method="post" action="/pengaturan-absensi/hapus/<?= (int) $rule['id'] ?>" onsubmit="return confirm('Hapus aturan ini? Tindakan ini akan memengaruhi perhitungan rekap pada periode terkait. Perubahan tetap dicatat di audit.');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                        </form>
                        <?php endif ?>
                    </td>
                </tr>
                <tr class="collapse" id="edit-rule-<?= (int) $rule['id'] ?>">
                    <td colspan="5">
                        <form class="border rounded p-3 bg-light" method="post" action="/pengaturan-absensi/perbarui/<?= (int) $rule['id'] ?>">
                            <?= csrf_field() ?>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Batas tidak terlambat</label>
                                    <input class="form-control" type="time" name="late_after" step="1" value="<?= esc($rule['late_after']) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Berlaku mulai</label>
                                    <input class="form-control" type="date" name="effective_from" value="<?= esc($rule['effective_from']) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Berlaku sampai (kosong = seterusnya)</label>
                                    <input class="form-control" type="date" name="effective_to" value="<?= esc($rule['effective_to'] ?? '') ?>" min="<?= esc($rule['effective_from']) ?>">
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-primary w-100" type="submit">Simpan Koreksi</button>
                                </div>
                            </div>
                            <small class="text-secondary">Periode tidak boleh bertabrakan dengan aturan <?= esc($rule['unit_code']) ?> lainnya.</small>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if (!$rules): ?><tr><td colspan="5" class="text-center text-secondary">Belum ada aturan.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3">
    <h5 class="mb-3">Audit Perubahan Terakhir</h5>
    <p class="small text-secondary">Maksimal 50 perubahan terbaru. Catatan ini menyimpan data sebelum dan sesudah perubahan.</p>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead><tr><th>Waktu</th><th>Unit</th><th>Aksi</th><th>Sebelum</th><th>Sesudah</th><th>Pengguna</th></tr></thead>
            <tbody>
            <?php foreach ($audits as $audit): ?>
                <?php $before = $audit['before_data'] ? json_decode($audit['before_data'], true) : null; ?>
                <?php $after = $audit['after_data'] ? json_decode($audit['after_data'], true) : null; ?>
                <tr>
                    <td><?= esc($audit['changed_at']) ?></td>
                    <td><?= esc($audit['unit_code']) ?></td>
                    <td><?= esc(ucfirst($audit['action'])) ?></td>
                    <td><?= $before ? esc(($before['late_after'] ?? '-') . ' | ' . ($before['effective_from'] ?? '-') . ' s.d. ' . ($before['effective_to'] ?? 'seterusnya')) : '-' ?></td>
                    <td><?= $after ? esc(($after['late_after'] ?? '-') . ' | ' . ($after['effective_from'] ?? '-') . ' s.d. ' . ($after['effective_to'] ?? 'seterusnya')) : '-' ?></td>
                    <td><?= esc($audit['changed_by'] ?? '-') ?></td>
                </tr>
            <?php endforeach ?>
            <?php if (!$audits): ?><tr><td colspan="6" class="text-center text-secondary">Belum ada perubahan.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
