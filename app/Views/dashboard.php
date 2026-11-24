<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="text-primary fw-bold small mb-2"><i class="bi bi-stars me-1"></i> SISTEM MANAJEMEN KEHADIRAN</div>
        <h2 class="mb-2">Dashboard</h2>
        <p class="text-secondary mb-0">Selamat datang, <?= esc($username ?? 'Admin') ?>. Kelola data dan proses absensi yayasan dari satu tempat.</p>
    </div>
    <div class="badge text-bg-primary px-3 py-2"><i class="bi bi-shield-check me-1"></i> NEURO ABSENSI V1</div>
</div>

<div class="card p-4 mb-4" style="background:linear-gradient(115deg,#10264b,#1e4b86);color:#fff;overflow:hidden;position:relative">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <div class="small fw-bold mb-2" style="color:#a9c9f7;letter-spacing:.1em">PUSAT KENDALI ABSENSI</div>
            <h3 class="fw-bold mb-2" style="font-size:clamp(20px,3vw,28px);color:#fff">Data kehadiran lebih tertata.</h3>
            <p class="mb-3" style="color:#d4e2f5;max-width:600px;font-size:12px">Impor data fingerprint, kelola pegawai, atur kalender kerja, lalu susun rekap kehadiran dengan aturan unit masing-masing.</p>
            <a class="btn btn-light me-2" href="/rekap"><i class="bi bi-calendar2-check me-1"></i> Buka rekap</a>
            <a class="btn btn-outline-light" href="/impor"><i class="bi bi-upload me-1"></i> Impor log</a>
        </div>
        <div class="col-lg-4 d-none d-lg-flex justify-content-center">
            <div style="width:138px;height:138px;border-radius:38px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);display:grid;place-items:center;font-size:68px;color:#d8e9ff"><i class="bi bi-fingerprint"></i></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="text-secondary small fw-semibold mb-2">Sumber mesin aktif</div><div class="fs-3 fw-bold" style="color:#10264b">2</div><div class="text-secondary small">SMPIT dan RAIT/KBIT</div></div>
                <div class="rounded-3 p-3" style="background:#eaf2ff;color:#2563eb"><i class="bi bi-device-ssd fs-4"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="text-secondary small fw-semibold mb-2">Metode impor</div><div class="fs-4 fw-bold" style="color:#10264b">TXT Revo</div><div class="text-secondary small">File log dari fingerprint</div></div>
                <div class="rounded-3 p-3" style="background:#e9f8f0;color:#15945b"><i class="bi bi-file-earmark-arrow-up fs-4"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="text-secondary small fw-semibold mb-2">Cakupan versi</div><div class="fs-4 fw-bold" style="color:#10264b">V1</div><div class="text-secondary small">SDIT belum memakai impor fingerprint</div></div>
                <div class="rounded-3 p-3" style="background:#fff5e8;color:#d97706"><i class="bi bi-layers fs-4"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div><h5 class="mb-1">Akses cepat</h5><p class="text-secondary mb-0">Pilih pekerjaan yang ingin dilakukan.</p></div>
</div>
<div class="row g-3">
    <div class="col-md-6 col-xl-3">
        <a href="/pegawai" class="card p-3 h-100 text-decoration-none">
            <div class="rounded-3 mb-3 p-3 d-inline-flex" style="background:#eaf2ff;color:#2563eb"><i class="bi bi-people-fill fs-4"></i></div>
            <h5>Data Pegawai</h5><p class="text-secondary mb-0">Kelola nama, unit, jabatan, dan status pegawai.</p>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="/impor" class="card p-3 h-100 text-decoration-none">
            <div class="rounded-3 mb-3 p-3 d-inline-flex" style="background:#e9f8f0;color:#15945b"><i class="bi bi-fingerprint fs-4"></i></div>
            <h5>Impor Fingerprint</h5><p class="text-secondary mb-0">Baca file TXT dan periksa pratinjau sebelum disimpan.</p>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="/kalender-kerja" class="card p-3 h-100 text-decoration-none">
            <div class="rounded-3 mb-3 p-3 d-inline-flex" style="background:#fff5e8;color:#d97706"><i class="bi bi-calendar-event-fill fs-4"></i></div>
            <h5>Kalender Kerja</h5><p class="text-secondary mb-0">Atur hari libur, izin masuk siang, dan hari kerja khusus.</p>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="/jadwal-hari-kerja" class="card p-3 h-100 text-decoration-none">
            <div class="rounded-3 mb-3 p-3 d-inline-flex" style="background:#f2edff;color:#7654c8"><i class="bi bi-calendar-week-fill fs-4"></i></div>
            <h5>Jadwal Mingguan</h5><p class="text-secondary mb-0">Tentukan hari kerja dan libur rutin setiap unit.</p>
        </a>
    </div>
</div>
<style>
.content-wrap a.card{transition:transform .18s ease,box-shadow .18s ease}
.content-wrap a.card:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(24,48,84,.1)!important}
</style>
<?= $this->endSection() ?>