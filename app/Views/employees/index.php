<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="mb-1">Data Pegawai</h2><p class="text-secondary mb-0">Pilih unit untuk melihat daftar pegawai masing-masing.</p></div>
    <a class="btn btn-primary" href="/pegawai/baru">Tambah pegawai</a>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3 col-xl-2">
        <a class="card p-3 text-decoration-none h-100 <?= $selectedUnit === 'ALL' ? 'border border-primary' : '' ?>" href="/pegawai">
            <div class="small text-secondary">Semua unit</div><div class="fs-3 fw-bold text-dark"><?= (int) $counts['ALL'] ?></div>
        </a>
    </div>
    <?php foreach ($units as $code => $label): ?>
    <div class="col-6 col-md-3 col-xl-2">
        <a class="card p-3 text-decoration-none h-100 <?= $selectedUnit === $code ? 'border border-primary' : '' ?>" href="/pegawai?unit=<?= esc($code) ?>">
            <div class="small text-secondary"><?= esc($label) ?></div><div class="fs-3 fw-bold text-dark"><?= (int) ($counts[$code] ?? 0) ?></div>
        </a>
    </div>
    <?php endforeach ?>
    <div class="col-6 col-md-3 col-xl-2">
        <a class="card p-3 text-decoration-none h-100 <?= $selectedUnit === 'UNASSIGNED' ? 'border border-warning' : '' ?>" href="/pegawai?unit=UNASSIGNED">
            <div class="small text-secondary">Belum ditentukan</div><div class="fs-3 fw-bold text-dark"><?= (int) $counts['UNASSIGNED'] ?></div>
        </a>
    </div>
</div>

<div class="card p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h5 class="mb-0"><?= $selectedUnit === 'ALL' ? 'Seluruh Pegawai' : ($selectedUnit === 'UNASSIGNED' ? 'Pegawai Belum Ditentukan Unitnya' : esc($units[$selectedUnit] ?? 'Data Pegawai')) ?></h5>
        <span class="badge text-bg-light"><?= count($employees) ?> pegawai</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Kode</th><th>Nama</th><th>Unit</th><th>Jabatan</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($employees as $employee): ?>
                <tr>
                    <td><?= esc($employee['employee_code']) ?></td>
                    <td><?= esc($employee['full_name']) ?></td>
                    <td><span class="badge text-bg-secondary"><?= esc($units[$employee['unit_code'] ?? ''] ?? 'Belum ditentukan') ?></span></td>
                    <td><?= esc($employee['position'] ?? '-') ?></td>
                    <td><?= ! empty($employee['is_active']) ? '<span class="text-success">Aktif</span>' : '<span class="text-secondary">Nonaktif</span>' ?></td>
                </tr>
            <?php endforeach ?>
            <?php if (empty($employees)): ?>
                <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada pegawai pada pilihan ini.</td></tr>
            <?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
