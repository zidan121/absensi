<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$exportQuery = http_build_query(['start_date'=>$startDate,'end_date'=>$endDate]);
$rows = [];
$maxScans = 1;
foreach ($employees as $employee) {
    foreach ($employee['details'] as $detail) {
        $maxScans = max($maxScans, count($detail['scans'] ?? []));
        $rows[] = [
            'fingerprint_pin'=>$employee['pin'],
            'full_name'=>$employee['full_name'],
            'position'=>$employee['position'] ?? '',
            'date'=>$detail['work_date'],
            'scans'=>$detail['scans'] ?? [],
            'status'=>$detail['status'] ?? 'Jadwal Belum Diatur',
            'calendar'=>$detail['calendar'] ?? '',
        ];
    }
}
?>
<?php
$statusClass = static function (string $status): string {
    if ($status === 'Terlambat') return 'text-bg-danger';
    if ($status === 'Tepat Waktu') return 'text-bg-success';
    if ($status === 'Izin Masuk Siang') return 'text-bg-info';
    if ($status === 'Jadwal Belum Diatur') return 'text-bg-warning';
    if (str_starts_with($status, 'Hari Libur')) return 'text-bg-secondary';
    if (str_starts_with($status, 'Libur Mingguan')) return 'text-bg-secondary';
    return 'text-bg-light';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="mb-1">Rekap Absensi</h2><p class="text-secondary mb-0">Rekap berdasarkan identitas mesin fingerprint + PIN khusus SDIT.</p></div>
</div>
<form class="card p-3 mb-3" method="get" action="/sdit/rekap">
    <input type="hidden" name="show" value="1">
    <div class="row g-2 align-items-end">
        <div class="col-sm-3"><label class="form-label">Tanggal Mulai</label><input class="form-control" type="date" name="start_date" value="<?= esc($startDate) ?>" required></div>
        <div class="col-sm-3"><label class="form-label">Tanggal Selesai</label><input class="form-control" type="date" name="end_date" value="<?= esc($endDate) ?>" min="<?= esc($startDate) ?>" required></div>
        <div class="col-sm-3">
            <label class="form-label">Unit</label>
            <div class="dropdown w-100">
                <button class="form-select text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span>SDIT</span></button>
                <div class="dropdown-menu p-3 w-100 shadow" style="min-width:240px">
                    <strong class="small">Unit yang direkap</strong>
                    <label class="form-check mt-2 mb-0"><input class="form-check-input" type="checkbox" checked disabled><span class="form-check-label">SDIT</span></label>
                </div>
            </div>
        </div>
        <div class="col-sm-3 d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">Tampilkan</button>
            <?php if ($hasSearched): ?>
                <a class="btn btn-success" href="/sdit/rekap/export?<?= esc($exportQuery) ?>">Ekspor Detail</a>
                <a class="btn btn-outline-success" href="/sdit/rekap/export-rekap-pegawai?<?= esc($exportQuery) ?>">Ekspor Format REKAB</a>
            <?php endif ?>
        </div>
    </div>
</form>
<?php if (!$hasSearched): ?>
<div class="alert alert-info mt-3"><strong>Rekap belum ditampilkan.</strong> Pilih tanggal, lalu klik tombol <strong>Tampilkan</strong> untuk memuat data absensi SDIT.</div>
<?php else: ?>
<div class="alert alert-secondary py-2">Periode rekap: <strong><?= esc(date('d-m-Y',strtotime($startDate))) ?></strong> sampai <strong><?= esc(date('d-m-Y',strtotime($endDate))) ?></strong> (termasuk kedua tanggal).</div>
<div class="row g-3 mb-3">
    <div class="col-md-2"><div class="card p-3 h-100"><div class="text-secondary small">Hari kehadiran</div><div class="fs-3 fw-bold"><?= (int)$summary['attendance_days'] ?></div></div></div>
    <div class="col-md-2"><div class="card p-3 h-100"><div class="text-secondary small">Hari terlambat</div><div class="fs-3 fw-bold"><?= (int)$summary['late_days'] ?></div></div></div>
    <div class="col-md-2"><div class="card p-3 h-100"><div class="text-secondary small">Izin masuk siang</div><div class="fs-3 fw-bold"><?= (int)$summary['afternoon_entry_days'] ?></div></div></div>
    <div class="col-md-2"><div class="card p-3 h-100"><div class="text-secondary small">Scan pada hari libur</div><div class="fs-3 fw-bold"><?= (int)$summary['holiday_scan_days'] ?></div></div></div>
    <div class="col-md-2"><div class="card p-3 h-100"><div class="text-secondary small">Hanya satu scan</div><div class="fs-3 fw-bold"><?= (int)$summary['one_scan_days'] ?></div></div></div>
    <div class="col-md-2"><div class="card p-3 h-100"><div class="text-secondary small">PIN belum cocok</div><div class="fs-3 fw-bold <?= $summary['unmatched_identities'] ? 'text-danger' : 'text-success' ?>"><?= (int)$summary['unmatched_identities'] ?></div></div></div>
</div>
<div class="alert alert-info small">
    <div class="fw-semibold mb-1">Panduan membaca status</div>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <span class="badge text-bg-success">Tepat Waktu</span>
        <span class="badge text-bg-danger">Terlambat</span>
        <span class="badge text-bg-info">Izin Masuk Siang</span>
        <span class="badge text-bg-secondary">Libur Mingguan / Hari Libur</span>
        <span class="badge text-bg-warning">Jadwal Belum Diatur</span>
    </div>
    Setiap baris mewakili satu pegawai pada satu tanggal. Scan ditampilkan berurutan dari yang paling awal. Timestamp duplikat pada waktu yang sama dihitung satu kali. Scan pada hari libur tetap ditampilkan sebagai informasi, tetapi tidak dihitung sebagai hari kehadiran. Kolom scan menyesuaikan jumlah scan terbanyak dalam hasil filter.
</div>
<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Detail kehadiran</h5><span class="badge text-bg-light"><?= count($rows) ?> baris</span></div>
    <div class="table-responsive" style="max-height:520px;overflow:auto"><table class="table table-sm table-bordered table-hover align-middle">
        <thead class="table-light"><tr><th>PIN</th><th>Nama</th><th>Jabatan</th><th>Tanggal</th>
            <?php for($i=1;$i<=$maxScans;$i++): ?><th>Scan <?= $i ?></th><?php if($i===1): ?><th>Status</th><?php endif ?><?php endfor ?>
        </tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?><tr>
            <td><?= esc($r['fingerprint_pin']) ?></td><td><?= esc($r['full_name']) ?></td><td><?= esc($r['position'] ?: '-') ?></td><td><?= esc(date('d-m-Y',strtotime($r['date']))) ?></td>
            <?php foreach($r['scans'] as $i=>$scan): ?><td><?= esc(substr($scan,0,5)) ?></td><?php if($i===0): ?><td><span class="badge <?= esc($statusClass($r['status'])) ?>"><?= esc($r['status']) ?></span><?php if($r['calendar'] !== ''): ?><div class="small text-secondary mt-1"><?= esc($r['calendar']) ?></div><?php endif ?></td><?php endif ?><?php endforeach ?>
            <?php if(count($r['scans'])<$maxScans): ?>
                <?php for($i=count($r['scans']);$i<$maxScans;$i++): ?><td>-</td><?php if($i===0): ?><td><span class="badge <?= esc($statusClass($r['status'])) ?>"><?= esc($r['status']) ?></span><?php if($r['calendar'] !== ''): ?><div class="small text-secondary mt-1"><?= esc($r['calendar']) ?></div><?php endif ?></td><?php endif ?><?php endfor ?>
            <?php endif ?>
        </tr><?php endforeach ?>
        <?php if(!$rows): ?><tr><td colspan="<?= 5+$maxScans ?>" class="text-center text-secondary py-4">Belum ada scan yang cocok untuk periode ini.</td></tr><?php endif ?>
        </tbody>
    </table></div>
</div>
<?php if($unmatched): ?>
<div class="card p-3 mt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h5 class="mb-1 text-danger">Detail identitas belum cocok</h5><p class="text-secondary small mb-0">PIN berikut memiliki scan pada periode terpilih, tetapi belum terpasang ke master pegawai SDIT.</p></div><span class="badge text-bg-danger"><?= count($unmatched) ?> identitas</span></div>
    <div class="table-responsive"><table class="table table-sm table-bordered table-hover align-middle"><thead class="table-light"><tr><th>Perangkat</th><th>Kode perangkat</th><th>PIN</th><th>Jumlah scan</th><th>Scan pertama</th><th>Scan terakhir</th></tr></thead>
    <tbody><?php foreach($unmatched as $identity): ?><tr><td>Mesin Fingerprint SDIT</td><td>FS2010195000514</td><td><strong><?= esc($identity['pin']) ?></strong></td><td><?= (int)$identity['scan_count'] ?></td><td><?= esc(date('d-m-Y H:i:s',strtotime($identity['first_scan']))) ?></td><td><?= esc(date('d-m-Y H:i:s',strtotime($identity['last_scan']))) ?></td></tr><?php endforeach ?></tbody></table></div>
    <div class="alert alert-warning small mt-3 mb-0">Periksa pemetaan PIN sebelum menambahkan pegawai baru.</div>
</div>
<?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
