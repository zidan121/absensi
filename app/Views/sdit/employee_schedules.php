<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$editRow = $editRow ?? null;
$formPin = $editRow['pin'] ?? '';
$formDay = (int)($editRow['day_of_week'] ?? 1);
$formStart = isset($editRow['start_time']) ? substr($editRow['start_time'],0,5) : '07:30';
$formEnd = isset($editRow['end_time']) ? substr($editRow['end_time'],0,5) : '16:00';
$formLate = (int)($editRow['late_after_minutes'] ?? 0);
$formFrom = $editRow['effective_from'] ?? '';
$formUntil = $editRow['effective_until'] ?? '';
$formWorkday = !isset($editRow['is_workday']) || (bool)$editRow['is_workday'];
?>
<?php if(session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if(session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>

<div class="page-heading">
    <h1>Jadwal Khusus Pegawai SDIT</h1>
    <p class="text-secondary">Jadwal ini mengesampingkan jadwal mingguan SDIT untuk PIN dan hari yang dipilih, selama periode berlaku.</p>
</div>

<div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><?= $editRow ? 'Ubah Jadwal Pegawai' : 'Tambah Jadwal Pegawai' ?></h5>
        <?php if($editRow): ?><a class="btn btn-sm btn-outline-secondary" href="/sdit/jadwal-pegawai">Batal Edit</a><?php endif ?>
    </div>
    <?php if(!$employees): ?>
        <div class="alert alert-warning mb-0">Master pegawai SDIT belum tersedia. Impor data pegawai terlebih dahulu.</div>
    <?php else: ?>
    <form method="post" action="/sdit/jadwal-pegawai/simpan" class="row g-3">
        <?= csrf_field() ?>
        <?php if($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif ?>
        <div class="col-md-4">
            <label class="form-label">Pegawai</label>
            <select class="form-select" name="pin" required>
                <option value="">Pilih pegawai</option>
                <?php foreach($employees as $e): ?>
                <option value="<?= esc($e['pin']) ?>" <?= (string)$formPin===(string)$e['pin']?'selected':'' ?>><?= esc(($e['employee_code'] ?: $e['pin']).' · '.$e['full_name'].' (PIN '.$e['pin'].')') ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Hari</label><select class="form-select" name="day_of_week"><?php foreach($days as $n=>$day): ?><option value="<?= $n ?>" <?= $formDay===$n?'selected':'' ?>><?= esc($day) ?></option><?php endforeach ?></select></div>
        <div class="col-md-2"><label class="form-label">Jam masuk</label><input class="form-control" type="time" name="start_time" value="<?= esc($formStart) ?>" required></div>
        <div class="col-md-2"><label class="form-label">Jam pulang</label><input class="form-control" type="time" name="end_time" value="<?= esc($formEnd) ?>" required></div>
        <div class="col-md-2"><label class="form-label">Toleransi (menit)</label><input class="form-control" type="number" name="late_after_minutes" value="<?= $formLate ?>" min="0" max="240" required></div>
        <div class="col-md-3"><label class="form-label">Berlaku mulai</label><input class="form-control" type="date" name="effective_from" value="<?= esc($formFrom) ?>"></div>
        <div class="col-md-3"><label class="form-label">Berlaku sampai</label><input class="form-control" type="date" name="effective_until" value="<?= esc($formUntil) ?>"></div>
        <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_workday" value="1" <?= $formWorkday?'checked':'' ?> id="employeeWorkday"><label class="form-check-label" for="employeeWorkday">Hari kerja</label></div></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100"><?= $editRow ? 'Simpan Perubahan' : 'Simpan Jadwal Pegawai' ?></button></div>
    </form>
    <?php endif ?>
    <div class="small text-secondary mt-3">Jam pulang harus setelah jam masuk. Periode tanggal tidak boleh bertabrakan dengan jadwal lain pada pegawai dan hari yang sama. Kosongkan tanggal untuk aturan tanpa batas periode.</div>
</div>

<div class="card">
    <div class="card-header"><strong>Daftar jadwal khusus</strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>PIN</th><th>Hari</th><th>Jam</th><th>Toleransi</th><th>Periode</th><th>Jenis</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach($rows as $row): ?>
                <tr>
                    <td><?= esc($row['employee_code']??'-') ?></td>
                    <td><?= esc($row['full_name']??'Pegawai tidak ditemukan') ?></td>
                    <td><?= esc($row['pin']) ?></td>
                    <td><?= esc($days[(int)$row['day_of_week']]??'-') ?></td>
                    <td><?= esc(substr($row['start_time'],0,5).'–'.substr($row['end_time'],0,5)) ?></td>
                    <td><?= (int)$row['late_after_minutes'] ?> menit</td>
                    <td><?= esc($row['effective_from']?:'Tanpa batas awal') ?> – <?= esc($row['effective_until']?:'Tanpa batas akhir') ?></td>
                    <td><?= $row['is_workday']?'Hari kerja':'Libur' ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="/sdit/jadwal-pegawai?edit=<?= (int)$row['id'] ?>">Edit</a>
                            <form method="post" action="/sdit/jadwal-pegawai/hapus/<?= (int)$row['id'] ?>" onsubmit="return confirm('Hapus jadwal khusus ini?')">
                                <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if(!$rows): ?><tr><td colspan="9" class="text-center text-secondary py-4">Belum ada jadwal khusus pegawai.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<div class="alert alert-info mt-3 small">Jika tidak ada jadwal khusus yang cocok untuk PIN, hari, dan tanggal, sistem memakai jadwal mingguan SDIT. Perubahan jadwal ini hanya berlaku untuk pegawai yang dipilih.</div>
<?= $this->endSection() ?>