<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= esc($title ?? 'Masuk — NEURO ABSENSI') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}body{min-height:100vh;margin:0;font-family:'Plus Jakarta Sans',sans-serif;background:#f3f7fd;color:#17243b}
        .login-shell{min-height:100vh;display:grid;grid-template-columns:1.05fr .95fr}
        .visual-panel{background:linear-gradient(145deg,#10264b,#1b4a83);color:#fff;padding:clamp(35px,7vw,100px);display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}
        .visual-panel:before,.visual-panel:after{content:'';position:absolute;border:1px solid rgba(255,255,255,.09);border-radius:50%;width:440px;height:440px;right:-140px;top:12%}
        .visual-panel:after{width:300px;height:300px;right:-70px;top:22%}
        .brand{font-weight:800;letter-spacing:.05em;font-size:19px;position:relative;z-index:1}.brand i{font-size:25px;margin-right:10px;color:#93c5fd}
        .visual-copy{position:relative;z-index:1;max-width:530px}.visual-copy .eyebrow{font-size:11px;letter-spacing:.16em;color:#9ec5fa;font-weight:800}.visual-copy h1{font-size:clamp(30px,4vw,48px);font-weight:800;line-height:1.16;margin:18px 0}.visual-copy p{color:#c9d9ee;font-size:14px;line-height:1.9;max-width:450px}
        .visual-foot{position:relative;z-index:1;color:#9fb6d5;font-size:11px}
        .form-panel{display:flex;align-items:center;justify-content:center;padding:35px 24px}.login-card{width:100%;max-width:420px;background:#fff;border:1px solid #e5ecf5;border-radius:22px;padding:clamp(25px,4vw,42px);box-shadow:0 18px 55px rgba(24,48,84,.08)}
        .login-icon{width:52px;height:52px;border-radius:16px;display:grid;place-items:center;background:#eaf2ff;color:#2563eb;font-size:25px;margin-bottom:25px}
        h2{font-size:25px;font-weight:800;color:#10264b;letter-spacing:-.04em}.sub{font-size:12px;color:#718096;line-height:1.7;margin-bottom:27px}
        .form-label{font-size:11px;font-weight:700;color:#42536b;margin-bottom:8px}.form-control{height:46px;border:1px solid #dce5f0;border-radius:10px;font-size:12px;padding:10px 13px}.form-control:focus{border-color:#8bb5fa;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
        .btn-primary{height:46px;border-radius:10px;background:#2563eb;border-color:#2563eb;font-size:12px;font-weight:700;box-shadow:0 7px 16px rgba(37,99,235,.18)}.btn-primary:hover{background:#1d4ed8;border-color:#1d4ed8}
        .alert{border:0;border-radius:10px;font-size:12px}
        @media(max-width:767px){.login-shell{grid-template-columns:1fr}.visual-panel{min-height:235px;padding:25px 26px}.visual-copy{margin-top:36px}.visual-copy h1{font-size:27px;margin:10px 0}.visual-copy p{font-size:12px;margin-bottom:0}.visual-foot{display:none}.form-panel{padding:25px 16px}.login-card{max-width:480px}}
    </style>
</head>
<body>
<div class="login-shell">
    <section class="visual-panel">
        <div class="brand"><i class="bi bi-fingerprint"></i>NEURO ABSENSI</div>
        <div class="visual-copy">
            <div class="eyebrow">YAYASAN BINA INSANI GROBOGAN</div>
            <h1>Kelola kehadiran dengan lebih mudah.</h1>
            <p>Satu ruang kerja untuk data pegawai, fingerprint, kalender kerja, dan rekap absensi seluruh unit.</p>
        </div>
        <div class="visual-foot">Attendance Management System · Local Application</div>
    </section>
    <main class="form-panel">
        <div class="login-card">
            <div class="login-icon"><i class="bi bi-shield-lock-fill"></i></div>
            <h2>Selamat datang</h2>
            <p class="sub">Masuk menggunakan akun administrator untuk melanjutkan ke sistem.</p>
            <?php if ($msg = session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc($msg) ?></div><?php endif ?>
            <form method="post" action="/login">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" autocomplete="username" placeholder="Masukkan username" required></div>
                <div class="mb-4"><label class="form-label">Password</label><input type="password" class="form-control" name="password" autocomplete="current-password" placeholder="Masukkan password" required></div>
                <button class="btn btn-primary w-100" type="submit">Masuk ke Dashboard <i class="bi bi-arrow-right ms-1"></i></button>
            </form>
        </div>
    </main>
</div>
</body>
</html>