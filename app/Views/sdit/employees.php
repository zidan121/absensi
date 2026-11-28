<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php if (session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="mb-1">Master Pegawai SDIT</h2><p class="text-secondary mb-0">Kelola pegawai dan identitas fingerprint khusus SDIT.</p></div>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="/sdit/pegawai/urutan"><i class="bi bi-sort-numeric-down me-1"></i>Atur Urutan</a><a class="btn btn-primary" href="/sdit/pegawai/tambah"><i class="bi bi-plus-lg me-1"></i>Tambah Pegawai</a></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-3"><a class="card p-3 text-decoration-none text-reset h-100" href="/sdit/pegawai"><div class="small text-secondary">Semua pegawai</div><div class="fs-3 fw-bold"><?= count($employees) ?></div></a></div>
    <div class="col-md-3"><a class="card p-3 text-decoration-none text-reset h-100" href="/sdit/pegawai?status=active"><div class="small text-secondary">Pegawai aktif</div><div class="fs-3 fw-bold text-success"><?= (int)$activeCount ?></div></a></div>
    <div class="col-md-3"><a class="card p-3 text-decoration-none text-reset h-100" href="/sdit/pegawai?status=inactive"><div class="small text-secondary">Pegawai nonaktif</div><div class="fs-3 fw-bold text-secondary"><?= (int)$inactiveCount ?></div></a></div>
</div>
<div class="card p-3">
    <form method="get" action="/sdit/pegawai" class="row g-2 mb-3">
        <div class="col-md-6"><input class="form-control" name="q" value="<?= esc($q) ?>" placeholder="Cari nama, kode pegawai, atau PIN"></div>
        <div class="col-md-3"><select class="form-select" name="status"><option value="">Semua status</option><option value="active" <?= $status==='active'?'selected':'' ?>>Aktif</option><option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Nonaktif</option></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Cari</button><a class="btn btn-outline-secondary" href="/sdit/pegawai">Reset</a></div>
    </form>
    <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0">Daftar Pegawai</h5><span class="badge text-bg-light"><?= count($employees) ?> data</span></div>
    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr><th>No.</th><th>Kode</th><th>PIN</th><th>Nama Pegawai</th><th>Nama di Fingerprint</th><th>Jabatan</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach($employees as $i=>$e): ?><tr>
            <td><?= $i+1 ?></td><td><?= esc($e['employee_code'] ?: '-') ?></td><td><span class="badge text-bg-light"><?= esc($e['pin']) ?></span></td>
            <td class="fw-semibold"><?= esc($e['full_name']) ?></td><td><?= esc($e['fingerprint_name'] ?: $e['full_name']) ?></td><td><?= esc($e['position'] ?: '-') ?></td>
            <td><?= $e['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
            <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="/sdit/pegawai/<?= (int)$e['id'] ?>/edit">Edit</a>
            <form method="post" action="/sdit/pegawai/<?= (int)$e['id'] ?>/status" class="d-inline" onsubmit="return confirm('Ubah status pegawai ini? Riwayat scan tidak dihapus.');"><?= csrf_field() ?><button class="btn btn-sm <?= $e['is_active']?'btn-outline-danger':'btn-outline-success' ?>"><?= $e['is_active']?'Nonaktifkan':'Aktifkan' ?></button></form></td>
        </tr><?php endforeach ?>
        <?php if(!$employees): ?><tr><td colspan="8" class="text-center text-secondary py-4">Data pegawai tidak ditemukan.</td></tr><?php endif ?>
        </tbody>
    </table></div>
</div>
<?= $this->endSection() ?>
