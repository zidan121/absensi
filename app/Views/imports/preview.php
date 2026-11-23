<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h2>Pratinjau Impor</h2>
<p class="text-secondary"><?= esc($originalName) ?> — <?= esc($device['device_name']) ?></p>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card p-3"><small>Scan valid</small><div class="h3"><?= count($preview['records']) ?></div></div></div>
    <div class="col-md-4"><div class="card p-3"><small>Baris invalid</small><div class="h3"><?= count($preview['invalid']) ?></div></div></div>
</div>

<?php
$fromDate = (string) ($_GET['from_date'] ?? '');
$toDate = (string) ($_GET['to_date'] ?? '');
$isValidDate = static function (string $value): bool {
    if ($value === '') return true;
    $date = \\DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
if (! $isValidDate($fromDate) || ! $isValidDate($toDate) || ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate)) {
    $fromDate = '';
    $toDate = '';
}
$filteredRecords = array_values(array_filter($preview['records'], static function ($record) use ($fromDate, $toDate) {
    $scanDate = substr($record['scanned_at'], 0, 10);
    return ($fromDate === '' || $scanDate >= $fromDate)
        && ($toDate === '' || $scanDate <= $toDate);
}));
$shownRecords = array_slice($filteredRecords, -60);
?>
<form method="get" action="/impor/preview" class="card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Dari tanggal</label>
            <input class="form-control" type="date" name="from_date" value="<?= esc($fromDate) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Sampai tanggal</label>
            <input class="form-control" type="date" name="to_date" value="<?= esc($toDate) ?>" min="<?= esc($fromDate) ?>">
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="/impor/preview">Reset</a>
        </div>
    </div>
    <small class="text-secondary mt-2">Kosongkan tanggal jika ingin melihat semua periode. Filter ini hanya mengatur pratinjau; seluruh scan valid tetap disimpan saat tombol Simpan scan valid ditekan.</small>
</form>

<div class="card p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <strong>Daftar scan</strong>
        <span class="badge text-bg-light"><?= count($filteredRecords) ?> hasil filter</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>PIN</th><th>Nama</th><th>Waktu scan</th><th>Mode</th><th>IN/OUT</th></tr></thead>
            <tbody>
                <?php foreach ($shownRecords as $record): ?>
                <tr>
                    <td><?= esc($record['pin']) ?></td>
                    <td><?= esc($record['employee_name_source']) ?></td>
                    <td><?= esc($record['scanned_at']) ?></td>
                    <td><?= esc($record['verify_mode']) ?></td>
                    <td><?= esc($record['inout_mode']) ?></td>
                </tr>
                <?php endforeach ?>
                <?php if (empty($shownRecords)): ?>
                <tr><td colspan="5" class="text-center text-secondary">Tidak ada scan pada rentang tanggal tersebut.</td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <small class="text-secondary">Menampilkan maksimal 60 scan terakhir dari <?= count($filteredRecords) ?> hasil filter.</small>
</div>
<?php if (count($preview['invalid'])): ?>
<div class="alert alert-warning">Ada <?= count($preview['invalid']) ?> baris invalid. Baris tersebut tidak disimpan sebagai scan sah.</div>
<?php endif ?>
<form method="post" action="/impor/proses">
    <?= csrf_field() ?>
    <button class="btn btn-success" <?= empty($preview['records']) ? 'disabled' : '' ?>>Simpan scan valid</button>
    <a class="btn btn-light" href="/impor">Batalkan</a>
</form>
<?= $this->endSection() ?>
