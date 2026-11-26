<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">Pengaturan Urutan Pegawai REKAB</h2>
        <p class="text-secondary mb-0">Atur urutan pegawai dan nama tampilan pada ekspor REKAB. Kamu juga bisa mengatur gabungan KBIT + Lazis sebagai satu daftar.</p>
    </div>
    <a href="/rekap" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Kembali ke Rekap</a>
</div>

<div class="card p-3 mb-3">
    <form method="get" action="/pengaturan-urutan-pegawai" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Pilih Unit</label>
            <select name="unit" class="form-select" onchange="this.form.submit()">
                <?php foreach ($units as $code => $label): ?>
                    <option value="<?= esc($code) ?>" <?= $selectedUnit === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-md-8 d-flex align-items-end">
            <div class="small text-secondary">Pilihan Gabungan KBIT + Lazis menyatukan pegawai dari kedua unit untuk diurutkan sebagai satu daftar. Perubahan nama hanya memengaruhi kolom NAMA pada ekspor REKAB, bukan nama master NEURO.</div>
        </div>
    </form>
</div>

<div class="card p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-1">Daftar Pegawai — <?= esc($units[$selectedUnit]) ?></h5>
            <div class="small text-secondary">Gunakan panah untuk mengubah urutan. Isi nama lengkap termasuk gelar jika diperlukan.</div>
        </div>
        <span class="badge text-bg-light"><?= count($employees) ?> pegawai</span>
    </div>
    <?php if (!$employees): ?>
        <div class="alert alert-info mb-0">Belum ada pegawai pada unit ini.</div>
    <?php else: ?>
    <form method="post" action="/pengaturan-urutan-pegawai/simpan" id="employeeOrderForm">
        <?= csrf_field() ?>
        <input type="hidden" name="unit_code" value="<?= esc($selectedUnit) ?>">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr><th style="width:55px">No.</th><th>Nama Master NEURO</th><th>Nama pada REKAB (bisa diedit)</th><th>Jabatan</th><th style="width:110px">Urutan</th></tr>
                </thead>
                <tbody id="employeeOrderList">
                <?php foreach ($employees as $index => $employee): ?>
                    <tr class="employee-order-row">
                        <td class="row-number text-center fw-semibold"><?= $index + 1 ?></td>
                        <td>
                            <div class="fw-semibold"><?= esc($employee['full_name']) ?></div>
                            <div class="small text-secondary"><?= esc($employee['employee_code']) ?></div>
                        </td>
                        <td>
                            <input type="hidden" name="employee_ids[]" value="<?= (int) $employee['id'] ?>">
                            <input type="text" class="form-control form-control-sm" name="display_names[<?= (int) $employee['id'] ?>]" value="<?= esc($employee['recap_display_name']) ?>" maxlength="150" required>
                        </td>
                        <td><?= esc($employee['position'] ?: '-') ?></td>
                        <td class="text-center text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary move-up" title="Naik"><i class="bi bi-arrow-up"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary move-down" title="Turun"><i class="bi bi-arrow-down"></i></button>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-secondary">Urutan dan nama diterapkan pada sheet REKAB sesuai pilihan unit, termasuk gabungan KBIT + Lazis. Data absensi dan master pegawai tidak berubah.</small>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Pengaturan</button>
        </div>
    </form>
    <?php endif ?>
</div>

<script>
(() => {
    const list = document.getElementById('employeeOrderList');
    if (!list) return;
    function refreshNumbers() {
        [...list.querySelectorAll('tr')].forEach((row, index) => {
            row.querySelector('.row-number').textContent = index + 1;
        });
    }
    list.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const row = button.closest('tr');
        if (button.classList.contains('move-up') && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (button.classList.contains('move-down') && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        }
        refreshNumbers();
    });
})();
</script>
<?= $this->endSection() ?>
