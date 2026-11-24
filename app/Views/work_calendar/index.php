<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">Kalender Kerja</h2>
        <p class="text-secondary mb-0">Atur hari libur, izin masuk siang, dan hari kerja khusus termasuk hari Minggu.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/rekap">Kembali ke Rekap</a>
</div>

<div class="alert alert-info">
    Prioritas aturan tanggal: pegawai tertentu, lalu unit, lalu semua unit. Hari libur membatalkan perhitungan keterlambatan. Masuk siang dan hari kerja khusus memakai batas waktu yang kamu tentukan.
</div>

<div class="card p-3 mb-4">
    <h5 class="mb-3">Tambah Pengaturan Kalender</h5>
    <form method="post" action="/kalender-kerja/simpan">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tanggal mulai</label>
                <input class="form-control" type="date" name="start_date" value="<?= esc(old('start_date', date('Y-m-d'))) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal selesai</label>
                <input class="form-control" type="date" name="end_date" value="<?= esc(old('end_date', date('Y-m-d'))) ?>" required>
                <small class="text-secondary">Untuk satu hari, samakan dengan tanggal mulai.</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Jenis pengaturan</label>
                <select class="form-select" name="event_type" required>
                    <option value="holiday">Hari libur</option>
                    <option value="late_start">Masuk siang / jam khusus</option>
                    <option value="workday">Hari kerja khusus (misalnya Minggu)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Nama / keterangan</label>
                <input class="form-control" name="title" maxlength="150" placeholder="Contoh: Libur semester / Rapat Ahad">
            </div>
            <div class="col-md-4">
                <label class="form-label">Berlaku untuk</label>
                <select class="form-select" name="target_type" required>
                    <option value="all">Semua unit</option>
                    <option value="unit">Unit tertentu</option>
                    <option value="employee">Pegawai tertentu</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Unit (jika memilih unit tertentu)</label>
                <select class="form-select" name="unit_code">
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= esc($unit) ?>"><?= esc($unit === 'YAYASAN' ? 'Yayasan' : $unit) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Pegawai (jika memilih pegawai tertentu)</label>
                <select class="form-select" name="employee_id">
                    <option value="">Pilih pegawai</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?= (int) $employee['id'] ?>"><?= esc($employee['full_name'] . ' — ' . $employee['unit_code'] . ' — PIN ' . $employee['fingerprint_pin']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Batas tidak terlambat</label>
                <input class="form-control" type="time" name="late_after" step="1" value="08:30:59">
                <small class="text-secondary">Wajib untuk Masuk Siang dan Hari Kerja Khusus. Tidak digunakan untuk hari libur.</small>
            </div>
            <div class="col-md-8">
                <label class="form-label">Catatan tambahan</label>
                <input class="form-control" name="note" placeholder="Opsional">
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary" type="submit">Simpan Kalender</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Daftar Kalender Kerja</h5>
        <span class="badge text-bg-light"><?= count($events) ?> data terbaru</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead><tr><th>Tanggal</th><th>Pengaturan</th><th>Berlaku untuk</th><th>Batas tidak terlambat</th><th>Catatan</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <?php
                    $targetLabel = match ($event['target_type']) {
                        'all' => 'Semua unit',
                        'unit' => $event['unit_code'],
                        default => ($event['full_name'] ?? 'Pegawai') . ' (pegawai)',
                    };
                    $typeLabel = match ($event['event_type']) {
                        'holiday' => 'Hari Libur',
                        'late_start' => 'Masuk Siang',
                        default => 'Hari Kerja Khusus',
                    };
                ?>
                <tr>
                    <td><?= esc(date('d-m-Y', strtotime($event['event_date']))) ?></td>
                    <td><?= esc($typeLabel) ?><br><small class="text-secondary"><?= esc($event['title']) ?></small></td>
                    <td><?= esc($targetLabel) ?></td>
                    <td><?= esc($event['late_after'] ?: '-') ?></td>
                    <td><?= esc($event['note'] ?: '-') ?></td>
                    <td>
                        <form method="post" action="/kalender-kerja/hapus/<?= (int) $event['id'] ?>" onsubmit="return confirm('Hapus pengaturan kalender ini?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if (!$events): ?><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada pengaturan kalender.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
    <small class="text-secondary">Menampilkan maksimal 150 pengaturan terbaru.</small>
</div>
<?= $this->endSection() ?>
