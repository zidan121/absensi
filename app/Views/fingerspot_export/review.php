<?= $this->extend('layout') ?><?= $this->section('content') ?>
<?php
$matched = count(array_filter($review['rows'], static fn($r) => $r['status'] === 'matched'));
$mismatch = count(array_filter($review['rows'], static fn($r) => $r['status'] === 'name_mismatch'));
$notFound = count(array_filter($review['rows'], static fn($r) => $r['status'] === 'not_found'));
$duplicate = count(array_filter($review['rows'], static fn($r) => $r['status'] === 'duplicate'));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h2>Review PIN Pegawai — <?= esc($sourceLabel) ?></h2><p class="text-secondary mb-0">Cocokkan data NEURO ABSENSI dengan backup database Fingerspot sebelum membuat SQL.</p></div>
  <a class="btn btn-outline-secondary" href="/backup/fingerspot">Kembali</a>
</div>
<div class="alert alert-info">Serial mesin terverifikasi: <strong><?= esc($review['serial']) ?></strong>. Data pembanding diambil dari tabel pegawai pada backup Fingerspot yang disimpan di aplikasi. Review ini tidak mengubah data pegawai di kedua sistem.</div>
<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3"><div class="card p-3"><div class="small text-secondary">PIN dan nama cocok</div><div class="fs-3 text-success fw-semibold"><?= $matched ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="card p-3"><div class="small text-secondary">Nama berbeda</div><div class="fs-3 text-warning fw-semibold"><?= $mismatch ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="card p-3"><div class="small text-secondary">PIN tidak ditemukan</div><div class="fs-3 text-danger fw-semibold"><?= $notFound ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="card p-3"><div class="small text-secondary">PIN ganda</div><div class="fs-3 text-danger fw-semibold"><?= $duplicate ?></div></div></div>
</div>
<form method="post" action="/backup/fingerspot/generate" id="pinReviewForm">
  <?= csrf_field() ?>
  <input type="hidden" name="source" value="<?= esc($sourceKey) ?>">
  <div class="card p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <div><h5 class="mb-1">Daftar pegawai dan PIN</h5><div class="small text-secondary">PIN cocok otomatis dicentang. Nama berbeda perlu Anda centang secara manual setelah memastikan identitasnya.</div></div>
      <div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-primary" id="selectMatched">Pilih yang cocok</button><button type="button" class="btn btn-sm btn-outline-secondary" id="clearSelection">Kosongkan pilihan</button></div>
    </div>
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead><tr><th>Pilih</th><th>PIN</th><th>Nama NEURO</th><th>Nama Fingerspot</th><th>Unit</th><th>Jumlah scan</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($review['rows'] as $row): ?>
          <?php
            $canSelect = in_array($row['status'], ['matched', 'name_mismatch'], true);
            $checked = $row['status'] === 'matched';
            $badge = match ($row['status']) {
              'matched' => 'success',
              'name_mismatch' => 'warning',
              default => 'danger',
            };
          ?>
          <tr>
            <td><?php if ($canSelect): ?><input class="form-check-input pin-choice" type="checkbox" name="approved_pins[]" value="<?= esc($row['pin']) ?>" <?= $checked ? 'checked' : '' ?>><?php else: ?><span class="text-secondary">—</span><?php endif ?></td>
            <td class="fw-semibold"><?= esc($row['pin']) ?></td>
            <td><?= esc($row['neuro_name']) ?></td>
            <td><?= $row['backup_name'] !== '' ? esc($row['backup_name']) : '<span class="text-secondary">Tidak ada pasangan</span>' ?></td>
            <td><?= esc($row['unit']) ?></td>
            <td><?= number_format($row['scan_count'], 0, ',', '.') ?></td>
            <td><span class="badge text-bg-<?= $badge ?>"><?= esc($row['status_label']) ?></span></td>
          </tr>
        <?php endforeach ?>
        <?php if (!$review['rows']): ?><tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada data pegawai ber-PIN pada perangkat ini.</td></tr><?php endif ?>
        </tbody>
      </table>
    </div>
    <div class="alert alert-warning mb-3"><strong>Perhatian:</strong> PIN dengan nama berbeda hanya akan diekspor jika Anda centang sendiri. PIN tidak ditemukan dan PIN ganda tidak dapat dipilih. Data pegawai Fingerspot tidak akan diperbarui oleh SQL ini.</div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="small text-secondary"><span id="selectedCount"><?= $matched ?></span> PIN dipilih untuk ekspor</div>
      <button type="submit" class="btn btn-primary" <?= !$review['eligible_pins'] ? 'disabled' : '' ?>><i class="bi bi-file-earmark-code me-1"></i>Buat SQL dari PIN terpilih</button>
    </div>
  </div>
</form>
<script>
(() => {
  const boxes = [...document.querySelectorAll('.pin-choice')];
  const count = document.getElementById('selectedCount');
  const refresh = () => { count.textContent = boxes.filter(box => box.checked).length; };
  document.getElementById('selectMatched')?.addEventListener('click', () => {
    boxes.forEach(box => { const row = box.closest('tr'); box.checked = row?.querySelector('.badge')?.classList.contains('text-bg-success') ?? false; });
    refresh();
  });
  document.getElementById('clearSelection')?.addEventListener('click', () => { boxes.forEach(box => box.checked = false); refresh(); });
  boxes.forEach(box => box.addEventListener('change', refresh));
  document.getElementById('pinReviewForm')?.addEventListener('submit', event => {
    if (!boxes.some(box => box.checked)) { event.preventDefault(); alert('Pilih minimal satu PIN yang sudah diperiksa.'); }
  });
})();
</script>
<?= $this->endSection() ?>