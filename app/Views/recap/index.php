<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$maxScans = 1;
foreach ($data['rows'] as $row) {
    $maxScans = max($maxScans, count($row['scans'] ?? []));
}
?>
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
<div class="alert alert-secondary py-2">Periode rekap: <strong><?= esc(date('d-m-Y', strtotime($startDate))) ?></strong> sampai <strong><?= esc(date('d-m-Y', strtotime($endDate))) ?></strong> (termasuk kedua tanggal).</div>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Hari kehadiran</div><div class="fs-3 fw-bold"><?= (int) $data['summary']['attendance_days'] ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Hari terlambat</div><div class="fs-3 fw-bold"><?= (int) $data['summary']['late_days'] ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Hanya satu scan</div><div class="fs-3 fw-bold"><?= (int) $data['summary']['one_scan_days'] ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Identitas belum cocok</div><div class="fs-3 fw-bold <?= $data['summary']['unmatched_identities'] ? 'text-danger' : 'text-success' ?>"><?= (int) $data['summary']['unmatched_identities'] ?></div></div></div>
</div>
<div class="alert alert-info small">Setiap baris mewakili satu pegawai pada satu tanggal. Scan ditampilkan berurutan dari yang paling awal. Timestamp duplikat pada waktu yang sama dihitung satu kali; jumlah kolom scan menyesuaikan scan terbanyak dalam hasil filter.</div>
<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Detail kehadiran</h5><span class="badge text-bg-light"><?= count($data['rows']) ?> baris</span></div>
    <div class="table-responsive"><table class="table table-sm table-bordered table-hover align-middle">
        <thead class="table-light"><tr>
            <th>PIN</th><th>Nama</th><th>Jabatan</th><th>Tanggal</th>
            <?php for ($i = 1; $i <= $maxScans; $i++): ?>
                <th>Scan <?= $i ?></th>
                <?php if ($i === 1): ?><th>Status</th><?php endif ?>
            <?php endfor ?>
        </tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?>
            <tr>
                <td><?= esc($r['fingerprint_pin'] ?? '') ?></td>
                <td><?= esc($r['full_name']) ?></td>
                <td><?= esc($r['position'] ?: '-') ?></td>
                <td><?= esc(date('d-m-Y', strtotime($r['date']))) ?></td>
                <?php $scans = $r['scans'] ?? []; ?>
                <?php for ($i = 0; $i < $maxScans; $i++): ?>
                    <td><?= isset($scans[$i]) ? esc(substr($scans[$i], 0, 5)) : '-' ?></td>
                    <?php if ($i === 0): ?>
                        <td><?= (int) $r['late_minutes'] > 0
                            ? '<span class="text-danger fw-semibold">Terlambat (' . (int) $r['late_minutes'] . ' menit)</span>'
                            : '<span class="text-success">Tepat Waktu</span>' ?></td>
                    <?php endif ?>
                <?php endfor ?>
            </tr>
        <?php endforeach ?>
        <?php if (!$data['rows']): ?><tr><td colspan="<?= 5 + $maxScans ?>" class="text-center text-secondary py-4">Belum ada scan yang cocok untuk periode dan unit ini.</td></tr><?php endif ?>
        </tbody>
    </table></div>
</div>
<?php if ($data['unmatched']): ?>
<div class="alert alert-warning mt-3"><strong>Perlu pemeriksaan:</strong> Ada <?= count($data['unmatched']) ?> kombinasi mesin + PIN yang belum cocok dengan master pegawai.</div>
<?php endif ?>
<?= $this->endSection() ?>
