<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="mb-1">Rekap Absensi</h2><p class="text-secondary mb-0">Rekap berdasarkan identitas mesin fingerprint + PIN.</p></div>
</div>
<form class="card p-3 mb-3" method="get" action="/rekap">
    <div class="row g-2 align-items-end">
        <div class="col-sm-3"><label class="form-label">Tanggal Mulai</label><input class="form-control" type="date" name="start_date" value="<?= esc($startDate) ?>" required></div>
        <div class="col-sm-3"><label class="form-label">Tanggal Selesai</label><input class="form-control" type="date" name="end_date" value="<?= esc($endDate) ?>" min="<?= esc($startDate) ?>" required></div>
        <div class="col-sm-3"><label class="form-label">Unit</label><select class="form-select" name="unit"><option value="">Semua unit</option><?php foreach (['KBIT','RAIT','SDIT','SMPIT','YAYASAN'] as $code): ?><option value="<?= esc($code) ?>" <?= $unit === $code ? 'selected' : '' ?>><?= esc($code === 'YAYASAN' ? 'Yayasan' : $code) ?></option><?php endforeach ?></select></div>
        <div class="col-sm-3 d-flex gap-2"><button class="btn btn-primary">Tampilkan</button><a class="btn btn-success" href="/rekap/export?start_date=<?= esc($startDate) ?>&amp;end_date=<?= esc($endDate) ?>&amp;unit=<?= esc($unit) ?>">Ekspor Excel</a></div>
    </div>
</form>
<div class="alert alert-secondary py-2">Periode rekap: <strong><?= esc(date('d F Y', strtotime($startDate))) ?></strong> sampai <strong><?= esc(date('d F Y', strtotime($endDate))) ?></strong> (termasuk kedua tanggal).</div>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Hari kehadiran</div><div class="fs-3 fw-bold"><?= (int) $data['summary']['attendance_days'] ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Hari terlambat</div><div class="fs-3 fw-bold"><?= (int) $data['summary']['late_days'] ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Hanya satu scan</div><div class="fs-3 fw-bold"><?= (int) $data['summary']['one_scan_days'] ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Identitas belum cocok</div><div class="fs-3 fw-bold <?= $data['summary']['unmatched_identities'] ? 'text-danger' : 'text-success' ?>"><?= (int) $data['summary']['unmatched_identities'] ?></div></div></div>
</div>
<div class="alert alert-info small">Hari tanpa scan tidak ditampilkan dan tidak otomatis dihitung sebagai alpa/izin/sakit. Satu scan dianggap hadir dengan jam pulang kosong. Ambang keterlambatan memakai jadwal individu jika tersedia; jika belum, digunakan ambang sementara per unit.</div>
<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Detail kehadiran</h5><span class="badge text-bg-light"><?= count($data['rows']) ?> baris</span></div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle">
        <thead><tr><th>Tanggal</th><th>Nama</th><th>Unit</th><th>Masuk</th><th>Pulang</th><th>Scan</th><th>Ambang</th><th>Terlambat</th></tr></thead>
        <tbody><?php foreach ($data['rows'] as $r): ?><tr>
            <td><?= esc($r['date']) ?></td><td><?= esc($r['full_name']) ?><div class="small text-secondary"><?= esc($r['employee_code']) ?></div></td>
            <td><?= esc($r['unit_code']) ?></td><td><?= esc($r['arrival']) ?></td><td><?= esc($r['departure'] ?: '-') ?></td>
            <td><?= (int) $r['scan_count'] ?></td><td><?= esc($r['late_after']) ?></td>
            <td><?= (int) $r['late_minutes'] > 0 ? '<span class="text-danger fw-semibold">' . (int) $r['late_minutes'] . ' menit</span>' : '-' ?></td>
        </tr><?php endforeach ?>
        <?php if (!$data['rows']): ?><tr><td colspan="8" class="text-center text-secondary py-4">Belum ada scan yang cocok untuk periode dan unit ini.</td></tr><?php endif ?></tbody>
    </table></div>
</div>
<?php if ($data['unmatched']): ?>
<div class="alert alert-warning mt-3"><strong>Perlu pemeriksaan:</strong> Ada <?= count($data['unmatched']) ?> kombinasi mesin + PIN yang belum cocok dengan master pegawai. Pastikan master pegawai kedua mesin sudah diimpor ulang.</div>
<?php endif ?>
<?= $this->endSection() ?>
