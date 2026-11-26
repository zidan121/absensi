<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<div class="page-heading d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1>Pengaturan Urutan Jabatan</h1>
        <p class="text-secondary mb-0">Atur prioritas jabatan yang tampil pada sheet REKAB saat ekspor Excel.</p>
    </div>
    <a href="/rekap" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Kembali ke Rekap</a>
</div>

<div class="alert alert-info">
    <strong>Aturan:</strong> Jabatan diurutkan dari atas ke bawah. Jabatan yang belum masuk daftar akan otomatis ditempatkan di bawah secara alfabetis. Pengaturan ini berlaku global untuk semua unit dan hanya mengubah urutan pada ekspor REKAB, bukan data pegawai atau perhitungan absensi.
</div>

<div class="card">
    <div class="card-body p-4">
        <form method="post" action="/pengaturan-urutan-jabatan/simpan" id="positionOrderForm">
            <?= csrf_field() ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Prioritas Jabatan</h4>
                <span class="text-secondary small">Geser dengan tombol panah</span>
            </div>
            <div id="positionList" class="d-grid gap-2">
                <?php foreach ($positions as $position): ?>
                    <div class="position-row d-flex align-items-center gap-2 border rounded-3 p-2">
                        <span class="text-secondary fw-bold px-2"><i class="bi bi-grip-vertical"></i></span>
                        <input type="text" name="positions[]" class="form-control position-name" maxlength="100" value="<?= esc($position) ?>" required>
                        <button type="button" class="btn btn-outline-secondary move-up" title="Naik"><i class="bi bi-arrow-up"></i></button>
                        <button type="button" class="btn btn-outline-secondary move-down" title="Turun"><i class="bi bi-arrow-down"></i></button>
                        <button type="button" class="btn btn-outline-danger remove-position" title="Keluarkan dari daftar urutan"><i class="bi bi-x-lg"></i></button>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="row g-2 mt-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="newPosition">Tambah jabatan ke daftar prioritas</label>
                    <input type="text" name="new_position" id="newPosition" class="form-control" maxlength="100" placeholder="Contoh: Kepala TU">
                </div>
                <div class="col-md-4">
                    <button type="button" id="addPosition" class="btn btn-outline-primary w-100"><i class="bi bi-plus-lg me-1"></i>Tambah Jabatan</button>
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
                <small class="text-secondary">Mengeluarkan jabatan hanya menghapus prioritasnya, bukan menghapus jabatan pegawai.</small>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Urutan</button>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const list = document.getElementById('positionList');
    const addButton = document.getElementById('addPosition');
    const newInput = document.getElementById('newPosition');

    function createRow(value) {
        const row = document.createElement('div');
        row.className = 'position-row d-flex align-items-center gap-2 border rounded-3 p-2';
        row.innerHTML = '<span class="text-secondary fw-bold px-2"><i class="bi bi-grip-vertical"></i></span>' +
            '<input type="text" name="positions[]" class="form-control position-name" maxlength="100" required>' +
            '<button type="button" class="btn btn-outline-secondary move-up" title="Naik"><i class="bi bi-arrow-up"></i></button>' +
            '<button type="button" class="btn btn-outline-secondary move-down" title="Turun"><i class="bi bi-arrow-down"></i></button>' +
            '<button type="button" class="btn btn-outline-danger remove-position" title="Keluarkan dari daftar urutan"><i class="bi bi-x-lg"></i></button>';
        row.querySelector('input').value = value || '';
        return row;
    }

    addButton.addEventListener('click', () => {
        const value = newInput.value.trim();
        if (!value) { newInput.focus(); return; }
        const duplicate = [...list.querySelectorAll('.position-name')].some(input => input.value.trim().toLowerCase() === value.toLowerCase());
        if (duplicate) { alert('Jabatan tersebut sudah ada dalam daftar.'); return; }
        list.appendChild(createRow(value));
        newInput.value = '';
        newInput.focus();
    });

    list.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const row = button.closest('.position-row');
        if (button.classList.contains('move-up') && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (button.classList.contains('move-down') && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        } else if (button.classList.contains('remove-position')) {
            row.remove();
        }
    });

    document.getElementById('positionOrderForm').addEventListener('submit', event => {
        const names = [...list.querySelectorAll('.position-name')].map(input => input.value.trim().toLowerCase()).filter(Boolean);
        if (new Set(names).size !== names.length) {
            event.preventDefault();
            alert('Ada nama jabatan yang duplikat. Periksa kembali daftar.');
        }
    });
})();
</script>
<?= $this->endSection() ?>
