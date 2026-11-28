<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$editRow = $editRow ?? null;
$formDate = $editRow['work_date'] ?? '';
$formType = $editRow['day_type'] ?? 'holiday';
$formDescription = $editRow['description'] ?? '';
$formAfternoon = !empty($editRow['allow_afternoon_entry']);
?>
<?php if(session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if(session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>

<div class="page-heading">
    <h1>Kalender Kerja SDIT</h1>
    <p class="text-secondary">Atur pengecualian tanggal tertentu tanpa mengubah jadwal mingguan SDIT.</p>
</div>

<div class="card p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h5 class="mb-0"><?= $editRow ? 'Ubah Pengaturan Tanggal' : 'Tambah Pengaturan Tanggal' ?></h5>
        <?php if($editRow): ?><a href="/sdit/kalender?bulan=<?= esc($month) ?>" class="btn btn-sm btn-outline-secondary">Batal Edit</a><?php endif ?>
    </div>
    <form method="post" action="/sdit/kalender/simpan" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-2">
            <label class="form-label">Tanggal</label>
            <input class="form-control" type="date" name="work_date" value="<?= esc($formDate) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Jenis hari</label>
            <select class="form-select" name="day_type" required>
                <option value="holiday" <?= $formType==='holiday'?'selected':'' ?>>Hari libur</option>
                <option value="workday" <?= $formType==='workday'?'selected':'' ?>>Hari kerja</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Keterangan</label>
            <input class="form-control" name="description" maxlength="255" value="<?= esc($formDescription) ?>" placeholder="Contoh: Libur nasional / kegiatan sekolah">
        </div>
        <div class="col-md-2">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="allow_afternoon_entry" value="1" id="afternoon" <?= $formAfternoon?'checked':'' ?>>
                <label class="form-check-label" for="afternoon">Izin masuk siang</label>
            </div>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100"><?= $editRow ? 'Simpan Perubahan' : 'Simpan' ?></button>
        </div>
    </form>
    <div class="small text-secondary mt-2">Izin masuk siang berlaku pada tanggal berjenis Hari Kerja. Pada tanggal tersebut, keterlambatan tidak dihitung sebagai terlambat.</div>
</div>

<form method="get" class="d-flex gap-2 align-items-end mb-3">
    <div><label class="form-label">Bulan</label><input class="form-control" type="month" name="bulan" value="<?= esc($month) ?>"></div>
    <button class="btn btn-outline-primary">Tampilkan</button>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th>Izin masuk siang</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach($rows as $row): ?>
                <tr>
                    <td><?= esc(date('d-m-Y',strtotime($row['work_date']))) ?></td>
                    <td><?= $row['day_type']==='holiday'?'Hari Libur':'Hari Kerja' ?></td>
                    <td><?= esc($row['description']??'-') ?></td>
                    <td><?= $row['allow_afternoon_entry']?'Ya':'Tidak' ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="/sdit/kalender?bulan=<?= esc($month) ?>&edit=<?= esc($row['work_date']) ?>">Edit</a>
                            <form method="post" action="/sdit/kalender/hapus/<?= (int)$row['id'] ?>" onsubmit="return confirm('Hapus pengaturan tanggal ini?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if(!$rows): ?><tr><td colspan="5" class="text-center text-secondary py-4">Belum ada pengaturan kalender pada bulan ini.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>