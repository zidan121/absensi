<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="mb-4">
    <div class="text-primary fw-bold small mb-2"><i class="bi bi-person-gear me-1"></i> PREFERENSI PENGGUNA</div>
    <h2 class="mb-1">Pengaturan Akun</h2>
    <p class="text-secondary mb-0">Perbarui identitas login dan keamanan akun administrator.</p>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded-4 d-grid place-items-center" style="width:52px;height:52px;background:#eaf2ff;color:#2563eb;font-size:23px"><i class="bi bi-person-fill"></i></div>
                <div><h5 class="mb-1">Profil Administrator</h5><div class="small text-secondary">Informasi akun yang digunakan saat masuk.</div></div>
            </div>
            <form method="post" action="/akun/simpan">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Nama lengkap</label>
                    <input class="form-control" name="full_name" maxlength="150" value="<?= esc(old('full_name', $user['full_name'])) ?>" placeholder="Nama administrator" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" minlength="3" maxlength="80" value="<?= esc(old('username', $user['username'])) ?>" autocomplete="username" required>
                    <div class="form-text">3–80 karakter. Gunakan huruf, angka, titik, garis bawah, atau tanda hubung.</div>
                </div>
                <hr class="my-4">
                <div class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-shield-lock text-primary fs-5"></i><h5 class="mb-0">Ganti Password</h5></div>
                <div class="alert alert-info">Kosongkan ketiga kolom password jika tidak ingin mengganti password.</div>
                <div class="mb-3">
                    <label class="form-label">Password saat ini</label>
                    <input class="form-control" type="password" name="current_password" autocomplete="current-password" placeholder="Wajib diisi jika mengganti password">
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Password baru</label>
                        <input class="form-control" type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Konfirmasi password baru</label>
                        <input class="form-control" type="password" name="confirm_password" minlength="8" autocomplete="new-password" placeholder="Ulangi password baru">
                    </div>
                </div>
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Simpan Pengaturan</button>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card p-4 mb-3">
            <h5 class="mb-3">Informasi Akun</h5>
            <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">ID akun</span><strong>#<?= (int) $user['id'] ?></strong></div>
            <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Status</span><span class="badge text-bg-success">Aktif</span></div>
            <div class="d-flex justify-content-between py-2"><span class="text-secondary">Dibuat pada</span><strong><?= esc($user['created_at'] ? date('d-m-Y', strtotime($user['created_at'])) : '-') ?></strong></div>
        </div>
        <div class="card p-4">
            <div class="d-flex gap-3 align-items-start">
                <div class="rounded-3 p-2" style="background:#fff5e8;color:#d97706"><i class="bi bi-exclamation-triangle fs-5"></i></div>
                <div><h5 class="mb-2">Tips keamanan</h5><ul class="small text-secondary ps-3 mb-0"><li>Gunakan password unik minimal 8 karakter.</li><li>Jangan membagikan password kepada orang lain.</li><li>Selalu keluar dari aplikasi jika menggunakan komputer bersama.</li></ul></div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
