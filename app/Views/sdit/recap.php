<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$exportQuery = http_build_query(['start_date'=>$startDate,'end_date'=>$endDate]);
?>
<div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div><h1>Rekap Absensi SDIT</h1><p class="text-secondary mb-0">Rekap dihitung dari scan yang benar-benar tercatat pada mesin SDIT.</p></div>
    <form method="get" class="d-flex flex-wrap align-items-center gap-2">
        <input type="hidden" name="show" value="1">
        <input type="date" name="start_date" class="form-control" value="<?= esc($startDate) ?>" required>
        <span class="text-secondary">s.d.</span>
        <input type="date" name="end_date" class="form-control" value="<?= esc($endDate) ?>" min="<?= esc($startDate) ?>" required>
        <button class="btn btn-primary">Tampilkan</button>
        <?php if ($hasSearched): ?>
            <a class="btn btn-success" href="/sdit/rekap/export?<?= esc($exportQuery) ?>">Excel Detail</a>
            <a class="btn btn-outline-success" href="/sdit/rekap/export-rekap-pegawai?<?= esc($exportQuery) ?>">Format REKAB</a>
        <?php endif ?>
    </form>
</div>
<?php if (!$hasSearched): ?>
<div class="alert alert-info mt-3">Pilih tanggal mulai dan tanggal selesai, lalu klik <strong>Tampilkan</strong> untuk memuat rekap SDIT.</div>
<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Pegawai dengan scan</div><div class="fs-3 fw-bold"><?= number_format($summary['employees_with_scans']) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Hari kehadiran tercatat</div><div class="fs-3 fw-bold"><?= number_format($summary['attendance_days']) ?></div><div class="small text-secondary">Terlambat: <?= number_format($summary['late_days']) ?> hari · Satu scan: <?= number_format($summary['one_scan_days']) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Total scan valid</div><div class="fs-3 fw-bold"><?= number_format($summary['total_scans']) ?></div></div></div></div>
</div>
<div class="alert alert-info">Aturan rekap: tanggal tanpa scan tidak ditampilkan dan tidak dihitung alpa. Scan pertama menjadi waktu datang, scan terakhir menjadi waktu pulang. Jika hanya satu scan, waktu pulang tetap kosong. Status terlambat dihitung dari jadwal SDIT; jika jadwal belum diatur, status ditampilkan sebagai <strong>Jadwal Belum Diatur</strong>. Scan pada hari libur tetap tersimpan sebagai riwayat.</div>
<?php if (!$employees): ?><div class="card"><div class="card-body text-center text-secondary py-5">Belum ada scan pada periode <?= esc(date('d-m-Y',strtotime($startDate))) ?> s.d. <?= esc(date('d-m-Y',strtotime($endDate))) ?>.</div></div><?php endif ?>
<?php foreach ($employees as $employee): ?>
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <div><strong><?= esc($employee['employee_code']) ?> · <?= esc($employee['full_name']) ?></strong><div class="small text-secondary">PIN <?= esc($employee['pin']) ?><?= $employee['position'] ? ' · '.esc($employee['position']) : '' ?></div></div>
        <div class="text-end"><strong><?= number_format($employee['days']) ?> hari</strong><div class="small text-secondary"><?= number_format($employee['total_scans']) ?> scan</div></div>
    </div>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th style="width:50px">No.</th><th>Tanggal</th><th>Scan pertama / Datang</th><th>Scan terakhir / Pulang</th><th>Jumlah scan</th><th>Status</th><?php for($i=3;$i<=$maxScans;$i++): ?><th>Scan <?= $i ?></th><?php endfor ?></tr></thead>
        <tbody>
        <?php foreach ($employee['details'] as $i=>$day): ?>
        <tr>
            <td><?= $i+1 ?></td><td><?= esc(date('d-m-Y',strtotime($day['work_date']))) ?></td>
            <td><?= esc(substr($day['first_scan'],0,5)) ?></td>
            <td><?= $day['last_scan'] ? esc(substr($day['last_scan'],0,5)) : '<span class="text-secondary">—</span>' ?></td>
            <td><?= number_format($day['scan_count']) ?></td>
            <td><?php if($day['status']==='Terlambat'): ?><span class="text-danger fw-semibold">Terlambat</span><?php elseif($day['status']==='Tepat Waktu'): ?><span class="text-success">Tepat Waktu</span><?php else: ?><?= esc($day['status']) ?><?php endif ?></td>
            <?php for($j=2;$j<$maxScans;$j++): ?><td><?= isset($day['scans'][$j]) ? esc(substr($day['scans'][$j],0,5)) : '—' ?></td><?php endfor ?>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
</div>
<?php endforeach ?>
<?php if ($unmatched): ?>
<div class="card mb-3">
    <div class="card-header"><strong class="text-danger">Detail PIN belum dipetakan</strong></div>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>PIN</th><th>Jumlah scan</th><th>Scan pertama</th><th>Scan terakhir</th></tr></thead>
        <tbody><?php foreach($unmatched as $item): ?><tr>
            <td><?= esc($item['pin']) ?></td><td><?= (int)$item['scan_count'] ?></td>
            <td><?= esc(date('d-m-Y H:i:s',strtotime($item['first_scan']))) ?></td>
            <td><?= esc(date('d-m-Y H:i:s',strtotime($item['last_scan']))) ?></td>
        </tr><?php endforeach ?></tbody>
    </table></div>
    <div class="card-body small text-secondary">Periksa pemetaan PIN sebelum menambahkan pegawai baru.</div>
</div>
<?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
