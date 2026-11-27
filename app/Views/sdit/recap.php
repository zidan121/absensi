<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div><h1>Rekap Absensi SDIT</h1><p class="text-secondary mb-0">Rekap dihitung dari scan yang benar-benar tercatat pada mesin SDIT.</p></div>
    <form method="get" class="d-flex align-items-center gap-2">
        <input type="month" name="bulan" class="form-control" value="<?= esc($month) ?>">
        <button class="btn btn-primary">Tampilkan</button>
        <a class="btn btn-success" href="/sdit/rekap/export?bulan=<?= esc($month) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    </form>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Pegawai dengan scan</div><div class="fs-3 fw-bold"><?= number_format(count($employees)) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Hari kehadiran tercatat</div><div class="fs-3 fw-bold"><?= number_format($totalDays) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Total scan valid</div><div class="fs-3 fw-bold"><?= number_format($totalScans) ?></div></div></div></div>
</div>
<div class="alert alert-info">Aturan rekap: tanggal tanpa scan tidak ditampilkan dan tidak dihitung alpa. Scan pertama menjadi waktu datang, scan terakhir menjadi waktu pulang. Jika hanya satu scan, waktu pulang tetap kosong. Status keterlambatan belum dihitung sampai jadwal kerja khusus SDIT diaktifkan.</div>
<?php if (!$employees): ?><div class="card"><div class="card-body text-center text-secondary py-5">Belum ada scan valid pada bulan <?= esc($month) ?>.</div></div><?php endif ?>
<?php foreach ($employees as $employee): ?>
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <div><strong><?= esc($employee['employee_code']) ?> · <?= esc($employee['full_name']) ?></strong><div class="small text-secondary">PIN <?= esc($employee['pin']) ?></div></div>
        <div class="text-end"><strong><?= number_format($employee['days']) ?> hari</strong><div class="small text-secondary"><?= number_format($employee['total_scans']) ?> scan</div></div>
    </div>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th style="width:50px">No.</th><th>Tanggal</th><th>Scan pertama / Datang</th><th>Scan terakhir / Pulang</th><th>Jumlah scan</th></tr></thead>
        <tbody><?php foreach ($employee['details'] as $i=>$day): ?><tr>
            <td><?= $i+1 ?></td><td><?= esc(date('d-m-Y',strtotime($day['work_date']))) ?></td>
            <td><?= esc(substr($day['first_scan'],0,5)) ?></td>
            <td><?= (int)$day['scan_count'] > 1 ? esc(substr($day['last_scan'],0,5)) : '<span class="text-secondary">—</span>' ?></td>
            <td><?= number_format($day['scan_count']) ?></td>
        </tr><?php endforeach ?></tbody>
    </table></div>
</div>
<?php endforeach ?>
<?= $this->endSection() ?>