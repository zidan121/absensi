<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">Jadwal Individu Pegawai</h2>
        <p class="text-secondary mb-0"><?= esc($employee['employee_code']) ?> — <?= esc($employee['full_name']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="/pegawai/<?= (int) $employee['id'] ?>/edit">Kembali ke data pegawai</a>
</div>

<div class="alert alert-info">
    Jadwal di halaman ini mengesampingkan jadwal hari kerja dan batas keterlambatan unit untuk pegawai ini selama periode yang dipilih. Jika tidak ada jadwal individu yang berlaku, sistem menggunakan aturan unit.
</div>

<div class="card p-4 mb-4">
    <h5 class="mb-3">Tambah Jadwal Individu</h5>
    <form method="post" action="/pegawai/<?= (int) $employee['id'] ?>/jadwal/simpan">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Berlaku mulai</label>
                <input class="form-control" type="date" name="effective_from" value="<?= esc(old('effective_from', date('Y-m-d'))) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Berlaku sampai (opsional)</label>
                <input class="form-control" type="date" name="effective_to" value="<?= esc(old('effective_to', '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Jam mulai kerja</label>
                <input class="form-control" type="time" name="start_time" step="1" value="<?= esc(old('start_time', '07:30:00')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Jam selesai kerja (opsional)</label>
                <input class="form-control" type="time" name="end_time" step="1" value="<?= esc(old('end_time', '16:00:00')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Batas tidak terlambat</label>
                <input class="form-control" type="time" name="late_after" step="1" value="<?= esc(old('late_after', '07:45:59')) ?>" required>
            </div>
            <div class="col-md-12">
                <label class="form-label d-block">Hari kerja</label>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($days as $number => $label): ?>
                        <label class="form-check-label d-flex align-items-center gap-2">
                            <input class="form-check-input mt-0" type="checkbox" name="work_days[]" value="<?= (int) $number ?>" <?= in_array((string) $number, (array) old('work_days', ['1','2','3','4','5','6']), true) ? 'checked' : '' ?>>
                            <?= esc($label) ?>
                        </label>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
        <button class="btn btn-primary mt-3" type="submit">Simpan Jadwal</button>
    </form>
</div>

<div class="card p-3">
    <h5 class="mb-3">Riwayat Jadwal Pegawai</h5>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead><tr><th>Periode</th><th>Jam kerja</th><th>Batas tidak terlambat</th><th>Hari kerja</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($schedules as $schedule): ?>
                <?php $selectedDays = array_map('intval', explode(',', (string) $schedule['work_days'])); ?>
                <tr>
                    <td><?= esc(date('d-m-Y', strtotime($schedule['effective_from']))) ?> s.d. <?= esc($schedule['effective_to'] ? date('d-m-Y', strtotime($schedule['effective_to'])) : 'seterusnya') ?></td>
                    <td><?= esc($schedule['start_time']) ?><?= $schedule['end_time'] ? ' – ' . esc($schedule['end_time']) : '' ?></td>
                    <td><?= esc($schedule['late_after']) ?></td>
                    <td><?= esc(implode(', ', array_intersect_key($days, array_flip($selectedDays)))) ?></td>
                    <td>
                        <form method="post" action="/pegawai/<?= (int) $employee['id'] ?>/jadwal/hapus/<?= (int) $schedule['id'] ?>" onsubmit="return confirm('Hapus jadwal ini? Rekap periode terkait akan kembali memakai aturan unit.');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if (!$schedules): ?><tr><td colspan="5" class="text-center text-secondary">Belum ada jadwal individu. Pegawai menggunakan aturan unit.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
