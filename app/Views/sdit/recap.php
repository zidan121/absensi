<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$exportQuery = http_build_query(['start_date' => $startDate, 'end_date' => $endDate]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="mb-1">Rekap Absensi</h2><p class="text-secondary mb-0">Rekap berdasarkan PIN fingerprint khusus SDIT.</p></div>
</div>
<form class="card p-3 mb-3" method="get" action="/sdit/rekap">
    <input type="hidden" name="show" value="1">
    <div class="row g-2 align-items-end">
        <div class="col-sm-3"><label class="form-label">Tanggal Mulai</label><input class="form-control" type="date" name="start_date" value="<?= esc($startDate) ?>" required></div>
        <div class="col-sm-3"><label class="form-label">Tanggal Selesai</label><input class="form-control" type="date" name="end_date" value="<?= esc($endDate) ?>" min="<?= esc($startDate) ?>" required></div>
        <div class="col-sm-3"><label class="form-label">Unit</label><input class="form-control" value="SDIT" readonly></div>
        <div class="col-sm-3 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">Tampilkan</button><?php if ($hasSearched): ?><a class="btn btn-success" href="/sdit/rekap/export?<?= esc($exportQuery) ?>">Ekspor Detail</a><?php endif ?></div>
    </div>
</form>
<?php if (! $hasSearched): ?>
<div class="alert alert-info mt-3">
    <strong>Rekap belum ditampilkan.</strong>
    Pilih tanggal, lalu klik tombol <strong>Tampilkan</strong> untuk memuat data absensi SDIT.
</div>
<?php else: ?>
<div class="alert alert-secondary py-2">Periode rekap: <strong><?= esc(date('d-m-Y', strtotime($startDate))) ?></strong> sampai <strong><?= esc(date('d-m-Y', strtotime($endDate))) ?></strong> (termasuk kedua tanggal).</div>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Hari kehadiran</div><div class="fs-3 fw-bold"><?= (int) $summary['attendance_days'] ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Hari terlambat</div><div class="fs-3 fw-bold"><?= (int) $summary['late_days'] ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">Hanya satu scan</div><div class="fs-3 fw-bold"><?= (int) $summary['one_scan_days'] ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-secondary small">PIN belum dipetakan</div><div class="fs-3 fw-bold <?= $summary['unmatched_identities'] ? 'text-danger' : 'text-success' ?>"><?= (int) $summary['unmatched_identities'] ?></div></div></div>
</div>
<div class="alert alert-info small">Setiap baris mewakili satu pegawai pada satu tanggal. Scan ditampilkan berurutan dari yang paling awal. Timestamp duplikat pada waktu yang sama dihitung satu kali. Kolom scan menyesuaikan jumlah scan terbanyak pada hasil filter.</div>
<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Detail kehadiran</h5><span class="badge text-bg-light"><?= count($rows) ?> baris</span></div>
    <div class="table-responsive" style="max-height:520px;overflow:auto"><table class="table table-sm table-bordered table-hover align-middle">
        <thead class="table-light"><tr><th>PIN</th><th>Nama</th><th>Jabatan</th><th>Tanggal</th>
            <?php for ($i=1; $i <= $maxScans; $i++): ?><th>Scan <?= $i ?></th><?php if ($i===1): ?><th>Status</th><?php endif ?><?php endfor ?>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?><tr>
            <td><?= esc($r['fingerprint_pin']) ?></td><td><?= esc($r['full_name']) ?></td><td><?= esc($r['position'] ?: '-') ?></td><td><?= esc(date('d-m-Y',strtotime($r['date']))) ?></td>
            <?php for ($i=0; $i<$maxScans; $i++): ?><td><?= isset($r['scans'][$i]) ? esc(substr($r['scans'][$i],0,5)) : '-' ?></td><?php if ($i===0): ?><td><?= esc($r['status']) ?></td><?php endif ?><?php endfor ?>
        </tr><?php endforeach ?>
        <?php if (!$rows): ?><tr><td colspan="<?= 5+$maxScans ?>" class="text-center text-secondary py-4">Belum ada scan yang cocok untuk periode ini.</td></tr><?php endif ?>
        </tbody>
    </table></div>
</div>
<?php endif ?>
<?= $this->endSection() ?>
