<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading"><h1>Detail Scan SDIT</h1><p class="text-secondary">Riwayat scan dari mesin SDIT saja. Data unit lain tidak ditampilkan.</p></div>
<?php if (session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Total scan valid SDIT</div><div class="fs-3 fw-bold"><?= number_format($total) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Waktu tidak valid</div><div class="fs-3 fw-bold"><?= number_format($invalidTotal) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-secondary">Hasil filter</div><div class="fs-3 fw-bold"><?= number_format(count($rows)) ?></div></div></div></div>
</div>
<div class="card mb-3"><div class="card-body">
<form method="get" action="/sdit/scan" class="row g-2 align-items-end">
    <div class="col-sm-3"><label class="form-label">Dari tanggal</label><input type="date" name="start_date" class="form-control" value="<?= esc($startDate ?? '') ?>"></div>
    <div class="col-sm-3"><label class="form-label">Sampai tanggal</label><input type="date" name="end_date" class="form-control" value="<?= esc($endDate ?? '') ?>"></div>
    <div class="col-sm-2"><label class="form-label">PIN</label><input name="pin" class="form-control" value="<?= esc($pin ?? '') ?>" placeholder="Semua PIN"></div>
    <div class="col-sm-3"><label class="form-label">Nama / Kode</label><input name="q" class="form-control" value="<?= esc($q ?? '') ?>" placeholder="Cari pegawai"></div>
    <div class="col-sm-1 d-grid"><button class="btn btn-primary">Filter</button></div>
    <div class="col-12"><a class="btn btn-sm btn-outline-secondary" href="/sdit/scan">Reset filter</a></div>
</form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead><tr><th>No.</th><th>Kode</th><th>PIN</th><th>Nama Pegawai</th><th>Jabatan</th><th>Tanggal</th><th>Jam</th><th>Sumber</th></tr></thead>
<tbody>
<?php foreach ($rows as $i => $row): ?><tr>
<td><?= $i + 1 ?></td><td><?= esc($row['employee_code'] ?? '-') ?></td><td class="fw-bold"><?= esc($row['pin']) ?></td>
<td><?= esc($row['full_name'] ?? 'PIN belum dipetakan') ?></td><td><?= esc($row['position'] ?? '-') ?></td>
<td><?= esc(date('d-m-Y', strtotime($row['scanned_at']))) ?></td><td class="fw-semibold"><?= esc(date('H:i:s', strtotime($row['scanned_at']))) ?></td><td><?= esc($row['source_format']) ?></td>
</tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="8" class="text-center text-secondary py-4">Tidak ada scan SDIT yang cocok dengan filter.</td></tr><?php endif ?>
</tbody></table></div><div class="card-footer small text-secondary">Maksimal 2.000 baris terbaru ditampilkan. Filter nama/kode diterapkan setelah identitas pegawai dicocokkan dengan PIN.</div></div>
<?= $this->endSection() ?>
