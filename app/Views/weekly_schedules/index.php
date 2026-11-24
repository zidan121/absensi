<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h2 class="mb-1">Jadwal Hari Kerja per Unit</h2>
<p class="text-secondary mb-3">Tentukan hari kerja rutin. Hari yang tidak dicentang dianggap hari libur mingguan untuk perhitungan keterlambatan.</p>

<div class="alert alert-info">
    Pengaturan awal KBIT dan SDIT sudah dibuat Senin–Jumat (Sabtu dan Minggu libur). Kamu tetap bisa mengubahnya, termasuk membuat periode jadwal baru jika kebijakan berubah.
</div>

<div class="card p-3 mb-4">
    <h5 class="mb-3">Tambah Periode Jadwal</h5>
    <form method="post" action="/jadwal-hari-kerja/simpan">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Unit</label>
                <select class="form-select" name="unit_code" required>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= esc($unit) ?>"><?= esc($unit === 'YAYASAN' ? 'Yayasan' : $unit) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Berlaku mulai</label>
                <input class="form-control" type="date" name="effective_from" value="<?= esc(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Berlaku sampai (opsional)</label>
                <input class="form-control" type="date" name="effective_to">
            </div>
            <div class="col-md-3">
                <label class="form-label">Pilih hari kerja</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ([1=>'Sen',2=>'Sel',3=>'Rab',4=>'Kam',5=>'Jum',6=>'Sab',7=>'Min'] as $number=>$label): ?>
                        <label class="border rounded px-2 py-1"><input type="checkbox" name="work_days[]" value="<?= $number ?>" <?= in_array($number, [1,2,3,4,5], true) ? 'checked' : '' ?>> <?= $label ?></label>
                    <?php endforeach ?>
                </div>
                <small class="text-secondary">Centang hari yang menjadi hari kerja.</small>
            </div>
        </div>
        <button class="btn btn-primary mt-3" type="submit">Simpan Jadwal</button>
    </form>
</div>

<div class="card p-3">
    <h5 class="mb-3">Riwayat Jadwal per Unit</h5>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead><tr><th>Unit</th><th>Hari kerja</th><th>Hari libur mingguan</th><th>Berlaku mulai</th><th>Berlaku sampai</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    $selected = array_map('intval', explode(',', $row['work_days']));
                    $labels = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];
                    $working = array_intersect_key($labels, array_flip($selected));
                    $off = array_diff_key($labels, array_flip($selected));
                ?>
                <tr>
                    <td><?= esc($row['unit_code']) ?></td>
                    <td><?= esc(implode(', ', $working)) ?></td>
                    <td><?= esc(implode(', ', $off)) ?></td>
                    <td><?= esc(date('d-m-Y', strtotime($row['effective_from']))) ?></td>
                    <td><?= esc($row['effective_to'] ? date('d-m-Y', strtotime($row['effective_to'])) : 'Seterusnya') ?></td>
                    <td>
                        <form method="post" action="/jadwal-hari-kerja/hapus/<?= (int) $row['id'] ?>" onsubmit="return confirm('Hapus periode jadwal ini?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-secondary">Belum ada jadwal.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
