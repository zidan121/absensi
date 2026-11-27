<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'NEURO ABSENSI') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--navy:#10264b;--navy-2:#173968;--blue:#2563eb;--ink:#17243b;--muted:#718096;--surface:#fff;--canvas:#f4f7fc;--line:#e7edf5;--sidebar:264px}
        *{box-sizing:border-box}
        body{margin:0;background:var(--canvas);color:var(--ink);font-family:'Plus Jakarta Sans',system-ui,sans-serif;font-size:14px}
        .app-shell{min-height:100vh}
        .sidebar{position:fixed;inset:0 auto 0 0;width:var(--sidebar);height:100vh;overflow-y:auto;background:linear-gradient(165deg,#10264b 0%,#142f59 58%,#1a4274 100%);padding:26px 18px;color:#fff;z-index:1030;display:flex;flex-direction:column}
        .brand-wrap{display:flex;align-items:center;gap:12px;padding:0 10px 28px;border-bottom:1px solid rgba(255,255,255,.12);margin-bottom:24px}
        .brand-mark{width:42px;height:42px;border-radius:14px;background:linear-gradient(135deg,#60a5fa,#2563eb);display:grid;place-items:center;font-size:21px;box-shadow:0 8px 20px rgba(37,99,235,.35)}
        .brand-name{font-size:16px;font-weight:800;letter-spacing:.04em;line-height:1.2}.brand-caption{font-size:10px;color:#a9bfdc;letter-spacing:.12em;margin-top:4px}
        .nav-caption{font-size:10px;font-weight:700;color:#8da8cb;letter-spacing:.14em;text-transform:uppercase;padding:0 12px;margin:8px 0 9px}
        .sidebar nav a{display:flex;align-items:center;gap:12px;color:#c9d7eb;text-decoration:none;padding:11px 13px;border-radius:11px;margin:3px 0;font-size:12px;font-weight:600;transition:.18s ease}
        .sidebar nav a i{font-size:17px;width:20px;text-align:center;color:#9db5d5}
        .sidebar nav a:hover{background:rgba(255,255,255,.09);color:#fff;transform:translateX(2px)}
        .sidebar nav a.active{background:linear-gradient(100deg,#2563eb,#3479ed);color:#fff;box-shadow:0 7px 18px rgba(0,0,0,.16)}
        .sidebar nav a.active i{color:#fff}
        .sidebar nav details{margin:4px 0}
        .sidebar nav summary{list-style:none;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 11px;border-radius:10px;color:#9fb5d3;font-size:10px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;cursor:pointer;user-select:none}
        .sidebar nav summary::-webkit-details-marker{display:none}
        .sidebar nav summary:after{content:'+';font-size:15px;line-height:1;color:#8da8cb}
        .sidebar nav details[open] summary:after{content:'−'}
        .sidebar nav summary:hover{background:rgba(255,255,255,.06);color:#fff}
        .sidebar nav details .nav-sub{padding:1px 0 4px 7px}
        .sidebar nav details .nav-sub a{padding:8px 10px;margin:2px 0;font-size:11px}
        .sidebar nav details .nav-sub a i{font-size:15px}
        .sidebar nav .nav-main{margin-bottom:5px}
        .sidebar nav .nav-main a{padding:9px 11px}
        .sidebar{padding:17px 13px}
        .brand-wrap{padding:0 8px 14px;margin-bottom:10px}
        .sidebar-bottom{padding:11px 8px 0}

        .sidebar-bottom{margin-top:auto;padding:18px 10px 0;border-top:1px solid rgba(255,255,255,.12)}
        .sidebar-bottom a{display:flex;align-items:center;gap:10px;color:#c9d7eb;text-decoration:none;font-size:12px;font-weight:600;padding:9px 4px}
        .sidebar-bottom a:hover{color:#fff}
        .main-area{margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column}
        .topbar{height:76px;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 34px;position:sticky;top:0;z-index:1020}
        .topbar-label{font-size:11px;color:var(--muted);font-weight:600}.topbar-title{font-size:14px;font-weight:800;color:var(--navy);margin-top:3px}
        .admin-chip{display:flex;align-items:center;gap:10px;background:#f4f7fc;border:1px solid var(--line);padding:7px 12px;border-radius:30px;font-size:11px;font-weight:700}
        .admin-avatar{width:30px;height:30px;display:grid;place-items:center;border-radius:50%;background:#dbeafe;color:#1d4ed8;font-size:13px}
        .content-wrap{padding:30px 34px 44px;width:100%;max-width:1600px;margin:0 auto;flex:1}
        .page-heading{margin-bottom:24px}.page-heading h1,.content-wrap h2{font-size:24px;font-weight:800;letter-spacing:-.04em;color:var(--navy)}
        .content-wrap h5{font-size:14px;font-weight:800;color:var(--navy)}
        .content-wrap p.text-secondary{color:var(--muted)!important;font-size:12px;line-height:1.7}
        .card{border:1px solid var(--line)!important;border-radius:17px!important;background:var(--surface);box-shadow:0 5px 22px rgba(24,48,84,.045)!important}
        .card h4{font-size:16px;font-weight:800;color:var(--navy)}
        .btn{border-radius:10px;font-size:12px;font-weight:700;padding:9px 15px;transition:.18s ease}
        .btn-primary{background:var(--blue);border-color:var(--blue);box-shadow:0 4px 10px rgba(37,99,235,.16)}
        .btn-primary:hover{background:#1d4ed8;border-color:#1d4ed8;transform:translateY(-1px)}
        .btn-outline-primary{color:var(--blue);border-color:#c8d8f4}.btn-outline-primary:hover{background:#eff6ff;color:#1d4ed8;border-color:#93b4ed}
        .btn-success{box-shadow:0 4px 10px rgba(25,135,84,.12)}
        .form-label{font-size:11px;font-weight:700;color:#42536b;margin-bottom:7px}
        .form-control,.form-select{min-height:41px;border:1px solid #dce5f0;border-radius:10px;font-size:12px;color:var(--ink);padding:9px 12px;background-color:#fff}
        .form-control:focus,.form-select:focus{border-color:#8bb5fa;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
        .form-check-input{border-color:#bdcadd}.form-check-input:checked{background-color:var(--blue);border-color:var(--blue)}
        .table{font-size:12px;color:#34445c;margin-bottom:0}
        .table thead th{background:#f5f8fc;color:#64748b;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line);padding:13px 12px;white-space:nowrap}
        .table tbody td{padding:12px;border-color:#edf1f6;vertical-align:middle}
        .table-hover tbody tr:hover{background:#f8fbff}
        .table-striped>tbody>tr:nth-of-type(odd)>*{--bs-table-bg:#fbfcfe}
        .badge{font-size:10px;font-weight:700;padding:6px 9px;border-radius:7px}
        .alert{border:0;border-radius:12px;font-size:12px;line-height:1.7;padding:13px 16px}
        .alert-info{background:#edf6ff;color:#24527e}.alert-warning{background:#fff8e8;color:#795514}.alert-success{background:#ecfdf3;color:#17633b}.alert-danger{background:#fff0f0;color:#9b2929}
        .text-secondary{color:var(--muted)!important}
        /* Sidebar refinement */
        :root{--sidebar:252px}
        .sidebar{
            background:
                radial-gradient(circle at 12% 4%,rgba(59,130,246,.22),transparent 27%),
                radial-gradient(circle at 100% 72%,rgba(14,165,233,.12),transparent 30%),
                linear-gradient(160deg,#0b1d3a 0%,#102b52 52%,#153b68 100%);
            padding:20px 14px 14px;
            border-right:1px solid rgba(255,255,255,.07);
            box-shadow:8px 0 28px rgba(10,30,60,.08);
            scrollbar-width:thin;
            scrollbar-color:rgba(163,190,225,.28) transparent;
        }
        .sidebar::-webkit-scrollbar{width:5px}
        .sidebar::-webkit-scrollbar-thumb{background:rgba(163,190,225,.28);border-radius:20px}
        .brand-wrap{position:relative;padding:2px 7px 17px;margin-bottom:14px;border-bottom:1px solid rgba(255,255,255,.1)}
        .brand-mark{width:43px;height:43px;flex:0 0 43px;border:1px solid rgba(255,255,255,.24);background:linear-gradient(145deg,#60a5fa,#2563eb 65%,#1d4ed8);box-shadow:0 8px 22px rgba(37,99,235,.32),inset 0 1px rgba(255,255,255,.35)}
        .brand-name{font-size:15px;letter-spacing:.055em}
        .brand-caption{font-size:9px;letter-spacing:.15em;color:#9db7d9}
        .sidebar nav{padding:2px 1px 10px}
        .sidebar nav details{margin:5px 0;padding:2px 0}
        .sidebar nav summary{padding:9px 11px;color:#8fa9ca;font-size:9px;letter-spacing:.15em;border-radius:9px}
        .sidebar nav summary:after{content:'';width:7px;height:7px;border-right:1.5px solid #8fa9ca;border-bottom:1.5px solid #8fa9ca;transform:rotate(45deg);margin:0 3px 4px 0;transition:transform .18s ease}
        .sidebar nav details[open] summary:after{content:'';transform:rotate(225deg);margin-bottom:0}
        .sidebar nav summary:hover{background:rgba(255,255,255,.055);color:#dceaff}
        .sidebar nav details .nav-sub{margin:2px 0 4px 8px;padding:2px 0 2px 9px;border-left:1px solid rgba(148,178,216,.2)}
        .sidebar nav a{position:relative;gap:11px;padding:10px 12px;margin:3px 0;border:1px solid transparent;border-radius:10px;color:#c0d0e5;font-size:11px;font-weight:600}
        .sidebar nav a i{width:19px;font-size:16px;color:#91acd0;transition:color .18s ease}
        .sidebar nav a:hover{background:rgba(255,255,255,.075);color:#fff;transform:translateX(2px)}
        .sidebar nav a.active{background:linear-gradient(100deg,#2563eb,#3982f6);border-color:rgba(191,219,254,.18);color:#fff;box-shadow:0 6px 16px rgba(2,12,35,.22),inset 0 1px rgba(255,255,255,.16)}
        .sidebar nav a.active:before{content:'';position:absolute;left:-10px;top:8px;bottom:8px;width:3px;border-radius:5px;background:#93c5fd}
        .sidebar nav a.active i{color:#fff}
        .sidebar nav .nav-main a{padding:11px 12px;margin-bottom:8px;background:rgba(255,255,255,.055);border-color:rgba(255,255,255,.06)}
        .sidebar nav .nav-main a.active{background:linear-gradient(100deg,#2563eb,#3982f6)}
        .sidebar-bottom{margin-top:auto;padding:12px 5px 2px;border-top:1px solid rgba(255,255,255,.11)}
        .sidebar-bottom a{padding:10px 11px;border-radius:10px;color:#b8cbe2;font-size:11px;transition:.18s ease}
        .sidebar-bottom a:hover{background:rgba(248,113,113,.1);color:#fecaca}

        /* Stronger 3D sidebar: layered surfaces, raised controls and depth */
        .sidebar{
            background:
                radial-gradient(ellipse at 12% 0%,rgba(96,165,250,.28),transparent 35%),
                radial-gradient(ellipse at 95% 85%,rgba(14,165,233,.2),transparent 38%),
                linear-gradient(145deg,#183c70 0%,#0b1e3b 48%,#164779 100%);
            box-shadow:inset -2px 0 0 rgba(255,255,255,.12),inset 0 2px 0 rgba(255,255,255,.1),12px 0 32px rgba(5,20,45,.28);
        }
        .brand-wrap{
            padding:12px 10px 17px;
            margin:0 0 15px;
            border:1px solid rgba(255,255,255,.11);
            border-radius:16px;
            background:linear-gradient(145deg,rgba(255,255,255,.13),rgba(255,255,255,.025));
            box-shadow:inset 0 1px 0 rgba(255,255,255,.17),0 7px 16px rgba(2,12,35,.2);
        }
        .brand-mark{
            transform:translateY(-2px);
            border:1px solid rgba(255,255,255,.38);
            box-shadow:0 4px 0 #16428e,0 8px 0 rgba(7,24,57,.55),0 14px 20px rgba(0,0,0,.32),inset 0 2px 3px rgba(255,255,255,.6),inset 0 -4px 6px rgba(14,52,125,.35);
        }
        .sidebar nav{padding:4px 2px 12px}
        .sidebar nav .nav-caption{
            color:#91b1d8;
            padding-left:12px;
            margin:13px 0 7px;
            text-shadow:0 1px 2px rgba(0,0,0,.4);
        }
        .sidebar nav details{
            margin:7px 0;
            padding:3px 5px 5px;
            border:1px solid rgba(255,255,255,.075);
            border-radius:13px;
            background:linear-gradient(145deg,rgba(255,255,255,.075),rgba(255,255,255,.018));
            box-shadow:inset 0 1px 0 rgba(255,255,255,.09),0 5px 12px rgba(1,10,28,.16);
        }
        .sidebar nav summary{
            padding:10px 10px;
            color:#b4c9e4;
            text-shadow:0 1px 2px rgba(0,0,0,.35);
        }
        .sidebar nav details[open] summary{color:#fff}
        .sidebar nav details .nav-sub{
            margin:3px 4px 2px 8px;
            padding:5px 0 4px 9px;
            border-left:1px solid rgba(174,204,240,.3);
        }
        .sidebar nav a{
            margin:4px 0;
            padding:10px 12px;
            border:1px solid rgba(255,255,255,.035);
            background:linear-gradient(145deg,rgba(255,255,255,.045),rgba(255,255,255,.012));
            box-shadow:inset 0 1px 0 rgba(255,255,255,.055),0 3px 6px rgba(0,0,0,.12);
            text-shadow:0 1px 2px rgba(0,0,0,.25);
        }
        .sidebar nav a:hover{
            transform:translateY(-2px);
            background:linear-gradient(145deg,rgba(255,255,255,.13),rgba(255,255,255,.045));
            border-color:rgba(255,255,255,.12);
            box-shadow:inset 0 1px 0 rgba(255,255,255,.16),0 7px 12px rgba(0,0,0,.22);
        }
        .sidebar nav a.active{
            transform:translateY(-2px);
            border:1px solid rgba(191,219,254,.38);
            background:linear-gradient(160deg,#65a5ff 0%,#347df0 42%,#1d55bf 100%);
            box-shadow:0 3px 0 #17418e,0 8px 0 rgba(5,22,55,.45),0 13px 18px rgba(0,0,0,.28),inset 0 2px 2px rgba(255,255,255,.48),inset 0 -4px 6px rgba(8,42,108,.32);
        }
        .sidebar nav a.active:before{
            left:-8px;
            top:7px;
            bottom:7px;
            box-shadow:0 0 12px rgba(147,197,253,.95);
        }
        .sidebar nav a.active i{color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.3)}
        .sidebar nav .nav-main a{
            border:1px solid rgba(255,255,255,.13);
            background:linear-gradient(145deg,rgba(255,255,255,.15),rgba(255,255,255,.045));
            box-shadow:inset 0 1px 0 rgba(255,255,255,.18),0 6px 12px rgba(0,0,0,.2);
        }
        .sidebar-bottom{
            margin-top:auto;
            padding:13px 7px 3px;
            border-top:1px solid rgba(255,255,255,.14);
        }
        .sidebar-bottom a{
            padding:11px 12px;
            border:1px solid rgba(255,255,255,.12);
            border-radius:12px;
            background:linear-gradient(145deg,rgba(255,255,255,.11),rgba(255,255,255,.025));
            box-shadow:inset 0 1px 0 rgba(255,255,255,.13),0 5px 10px rgba(0,0,0,.18);
        }
        .sidebar-bottom a:hover{
            transform:translateY(-2px);
            background:linear-gradient(145deg,rgba(248,113,113,.2),rgba(248,113,113,.06));
            box-shadow:0 7px 13px rgba(0,0,0,.22);
        }

        /* Global 3D visual system for every application page */
        :root{
            --depth-shadow:0 9px 0 rgba(20,48,91,.055),0 14px 26px rgba(20,48,91,.09);
            --depth-soft:0 5px 0 rgba(20,48,91,.045),0 9px 18px rgba(20,48,91,.075);
        }
        .main-area{
            background:
                radial-gradient(ellipse at 12% 0%,rgba(191,219,254,.24),transparent 34%),
                linear-gradient(145deg,#f5f8fe 0%,#edf2fa 100%);
        }
        .topbar{
            background:linear-gradient(180deg,#fff 0%,#f4f7fc 100%);
            border-bottom:1px solid #dce5f1;
            box-shadow:0 5px 0 rgba(26,57,99,.035),0 8px 18px rgba(26,57,99,.06);
        }
        .admin-chip{
            background:linear-gradient(145deg,#fff,#eaf1fb);
            border:1px solid #d5e0ef;
            box-shadow:inset 0 1px 0 #fff,0 3px 0 #d9e3f1,0 6px 12px rgba(22,48,84,.09);
        }
        .admin-avatar{
            background:linear-gradient(145deg,#eff6ff,#bfdbfe);
            box-shadow:inset 0 1px 2px #fff,0 2px 4px rgba(37,99,235,.18);
        }
        .page-heading{position:relative}
        .page-heading h1,.content-wrap h2{
            text-shadow:0 2px 0 rgba(255,255,255,.9),0 4px 9px rgba(18,48,91,.08);
        }
        .card{
            position:relative;
            border:1px solid #dce6f3!important;
            background:linear-gradient(145deg,#fff 0%,#f7faff 100%);
            box-shadow:inset 0 1px 0 rgba(255,255,255,.98),0 4px 0 #e1e9f4,0 10px 22px rgba(20,48,84,.075)!important;
            transition:transform .2s ease,box-shadow .2s ease;
        }
        .card:hover{
            transform:translateY(-2px);
            box-shadow:inset 0 1px 0 #fff,0 5px 0 #dce6f3,0 15px 26px rgba(20,48,84,.11)!important;
        }
        .card-header{
            background:linear-gradient(180deg,#f9fbff,#edf3fb)!important;
            border-bottom:1px solid #dce6f2!important;
            box-shadow:inset 0 1px 0 #fff;
            font-weight:800;
        }
        .card-footer{
            background:linear-gradient(180deg,#f3f7fd,#eaf0f8)!important;
            border-top:1px solid #dce6f2!important;
        }
        .btn{
            position:relative;
            border-width:1px;
            box-shadow:inset 0 1px 0 rgba(255,255,255,.38),0 3px 0 rgba(20,48,84,.14),0 6px 11px rgba(20,48,84,.08);
            transition:transform .16s ease,box-shadow .16s ease,filter .16s ease;
        }
        .btn:hover{
            transform:translateY(-2px);
            filter:brightness(1.035);
            box-shadow:inset 0 1px 0 rgba(255,255,255,.42),0 5px 0 rgba(20,48,84,.13),0 10px 16px rgba(20,48,84,.12);
        }
        .btn:active{
            transform:translateY(2px);
            box-shadow:inset 0 2px 4px rgba(0,0,0,.12),0 1px 0 rgba(20,48,84,.12);
        }
        .btn-primary{background:linear-gradient(145deg,#4389ff,#1d58cf);border-color:#2864d5}
        .btn-success{background:linear-gradient(145deg,#35b978,#16834e);border-color:#16834e}
        .btn-danger{background:linear-gradient(145deg,#fb7777,#d93636);border-color:#d93636}
        .btn-warning{background:linear-gradient(145deg,#ffdb79,#e8a91e);border-color:#e8a91e;color:#503500}
        .btn-outline-primary,.btn-outline-secondary,.btn-light{
            background:linear-gradient(145deg,#fff,#edf3fb);
            box-shadow:inset 0 1px 0 #fff,0 3px 0 #dce5f0,0 6px 10px rgba(20,48,84,.06);
        }
        .form-control,.form-select{
            background:linear-gradient(180deg,#f5f8fd 0%,#fff 100%);
            border:1px solid #d4dfed;
            box-shadow:inset 0 2px 4px rgba(25,54,91,.055),0 2px 0 rgba(255,255,255,.9);
            transition:border-color .18s ease,box-shadow .18s ease;
        }
        .form-control:focus,.form-select:focus{
            background:#fff;
            box-shadow:inset 0 1px 3px rgba(25,54,91,.04),0 0 0 3px rgba(37,99,235,.12),0 3px 0 rgba(37,99,235,.08);
        }
        textarea.form-control{min-height:90px}
        .input-group-text{
            border-color:#d4dfed;
            background:linear-gradient(145deg,#edf3fb,#dfe8f5);
            color:#526681;
            box-shadow:inset 0 1px 0 #fff;
        }
        .table-responsive{
            border:1px solid #dce6f2;
            border-radius:13px;
            background:linear-gradient(145deg,#fff,#f8fbff);
            box-shadow:inset 0 1px 0 #fff,0 3px 0 #e3eaf4,0 8px 16px rgba(20,48,84,.055);
        }
        .table{--bs-table-bg:transparent}
        .table thead th{
            background:linear-gradient(180deg,#f9fbff,#eaf1fa);
            box-shadow:inset 0 1px 0 #fff;
        }
        .table tbody tr{transition:background .15s ease}
        .table-hover tbody tr:hover>*{background:#edf5ff}
        .badge{
            border:1px solid rgba(80,110,150,.12);
            box-shadow:inset 0 1px 0 rgba(255,255,255,.45),0 2px 3px rgba(20,48,84,.07);
        }
        .alert{
            border:1px solid rgba(120,150,190,.2);
            box-shadow:inset 0 1px 0 rgba(255,255,255,.75),0 4px 0 rgba(20,48,84,.045),0 7px 14px rgba(20,48,84,.05);
        }
        .dropdown-menu,.modal-content,.popover,.offcanvas{
            border:1px solid #dce6f2;
            border-radius:15px;
            background:linear-gradient(145deg,#fff,#f5f8fd);
            box-shadow:inset 0 1px 0 #fff,0 8px 0 rgba(20,48,84,.045),0 18px 35px rgba(20,48,84,.16);
        }
        .dropdown-item{border-radius:7px;font-size:12px;font-weight:600;padding:8px 12px}
        .dropdown-item:hover{background:linear-gradient(145deg,#edf5ff,#dceaff);color:#174da8}
        .modal-header,.modal-footer{
            background:linear-gradient(180deg,#f9fbff,#edf3fb);
            border-color:#dce6f2;
        }
        .nav-tabs{border-bottom:1px solid #d9e3f0;gap:5px}
        .nav-tabs .nav-link{
            border:1px solid #dce5f0;
            border-bottom:0;
            border-radius:10px 10px 0 0;
            background:linear-gradient(180deg,#f7faff,#eaf0f8);
            color:#60718a;
            font-size:12px;font-weight:700;
        }
        .nav-tabs .nav-link.active{
            color:#174da8;
            background:linear-gradient(180deg,#fff,#f4f8ff);
            box-shadow:inset 0 2px 0 #fff,0 -3px 0 #dce6f2;
        }
        .list-group-item{
            border-color:#e0e8f2;
            background:linear-gradient(145deg,#fff,#f8faff);
        }
        .pagination .page-link{
            border-color:#dce5f0;
            background:linear-gradient(145deg,#fff,#edf3fb);
            box-shadow:0 2px 0 #dce5f0;
            font-size:12px;font-weight:700;
        }
        .pagination .page-item.active .page-link{
            background:linear-gradient(145deg,#4389ff,#1d58cf);
            border-color:#2864d5;
        }
        .progress{
            background:linear-gradient(180deg,#e1e9f4,#f5f8fc);
            box-shadow:inset 0 2px 4px rgba(20,48,84,.1);
            border-radius:20px;
        }
        .progress-bar{background:linear-gradient(180deg,#69a5ff,#2563eb);box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 1px 3px rgba(20,48,84,.15)}
        .accordion-item{
            border:1px solid #dce6f2!important;
            border-radius:12px!important;
            overflow:hidden;
            box-shadow:0 3px 0 #e5ecf5,0 7px 13px rgba(20,48,84,.05);
            margin-bottom:9px;
        }
        .accordion-button{background:linear-gradient(180deg,#fff,#edf3fb);font-size:12px;font-weight:800;color:var(--navy)}
        .accordion-button:not(.collapsed){background:linear-gradient(180deg,#eaf3ff,#dceaff);color:#174da8;box-shadow:inset 0 -1px 0 #cbdcf3}
        .breadcrumb{font-size:11px;font-weight:700}
        .page-link:focus{box-shadow:0 0 0 3px rgba(37,99,235,.12)}
        @media(prefers-reduced-motion:reduce){
            .card,.btn,.sidebar nav a{transition:none!important}
            .card:hover,.btn:hover{transform:none}
        }

        @media(max-width:991.98px){:root{--sidebar:220px}.sidebar{width:var(--sidebar)}.topbar{padding:0 22px}.content-wrap{padding:24px 22px}}
        @media(max-width:767.98px){.sidebar{position:relative;width:100%;height:auto;min-height:0;padding:13px 14px}.brand-wrap{padding:0 5px 12px;margin-bottom:12px}.brand-mark{width:35px;height:35px;border-radius:11px}.brand-name{font-size:14px}.brand-caption{font-size:9px}.sidebar nav{display:flex;gap:5px;overflow-x:auto;padding-bottom:4px}.nav-caption{display:none}.sidebar nav a{white-space:nowrap;padding:9px 11px;margin:0;font-size:11px}.sidebar nav a i{font-size:15px}.sidebar-bottom{display:none}.main-area{margin-left:0}.topbar{height:58px;padding:0 16px;position:relative}.content-wrap{padding:21px 14px 32px}.content-wrap h2,.page-heading h1{font-size:21px}.admin-chip{padding:5px 8px}.admin-chip span{display:none}}
        @media(max-width:575.98px){.card{border-radius:14px!important}.content-wrap .row{--bs-gutter-x:.7rem}.btn{padding:8px 11px}.table-responsive{border-radius:10px}}
    </style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand-wrap"><div class="brand-mark"><i class="bi bi-fingerprint"></i></div><div><div class="brand-name">NEURO ABSENSI</div><div class="brand-caption">ATTENDANCE MANAGEMENT</div></div></div>
        <?php $currentPath = uri_string(); ?>
        <nav>
            <div class="nav-caption">Beranda</div>
            <div class="nav-main">
                <a class="<?= $currentPath === '' ? 'active' : '' ?>" href="/"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a>
            </div>

            <div class="nav-caption">Operasional</div>
            <details <?= (str_starts_with($currentPath, 'pegawai') || str_starts_with($currentPath, 'rekap')) ? 'open' : '' ?>>
                <summary>Data & Kehadiran</summary>
                <div class="nav-sub">
                    <a class="<?= str_starts_with($currentPath, 'pegawai') ? 'active' : '' ?>" href="/pegawai"><i class="bi bi-people-fill"></i>Data Pegawai</a>
                    <a class="<?= str_starts_with($currentPath, 'rekap') ? 'active' : '' ?>" href="/rekap"><i class="bi bi-calendar2-check-fill"></i>Rekap Absensi</a>
                </div>
            </details>

            <div class="nav-caption">Impor Fingerprint</div>
            <details <?= (str_starts_with($currentPath, 'impor') || str_starts_with($currentPath, 'sdit')) ? 'open' : '' ?>>
                <summary>Pengolahan Data</summary>
                <div class="nav-sub">
                    <a class="<?= str_starts_with($currentPath, 'impor') && !str_starts_with($currentPath, 'impor-backup') ? 'active' : '' ?>" href="/impor"><i class="bi bi-file-earmark-arrow-up-fill"></i>Impor Log TXT</a>
                    <a class="<?= str_starts_with($currentPath, 'impor-backup') ? 'active' : '' ?>" href="/impor-backup"><i class="bi bi-database-fill-up"></i>Impor Backup SQL</a>
                    <a class="<?= str_starts_with($currentPath, 'sdit') ? 'active' : '' ?>" href="/sdit"><i class="bi bi-building"></i>Modul SDIT (BIN)</a>
                </div>
            </details>

            <div class="nav-caption">Pengaturan</div>
            <details <?= (str_starts_with($currentPath, 'pengaturan-absensi') || str_starts_with($currentPath, 'pengaturan-urutan-pegawai') || str_starts_with($currentPath, 'kalender-kerja') || str_starts_with($currentPath, 'jadwal-hari-kerja')) ? 'open' : '' ?>>
                <summary>Aturan Kehadiran</summary>
                <div class="nav-sub">
                    <a class="<?= str_starts_with($currentPath, 'pengaturan-absensi') ? 'active' : '' ?>" href="/pengaturan-absensi"><i class="bi bi-sliders"></i>Pengaturan Absensi</a>
                    <a class="<?= str_starts_with($currentPath, 'jadwal-hari-kerja') ? 'active' : '' ?>" href="/jadwal-hari-kerja"><i class="bi bi-calendar-week-fill"></i>Jadwal Hari Kerja</a>
                    <a class="<?= str_starts_with($currentPath, 'kalender-kerja') ? 'active' : '' ?>" href="/kalender-kerja"><i class="bi bi-calendar-event-fill"></i>Kalender Kerja</a>
                    <a class="<?= str_starts_with($currentPath, 'pengaturan-urutan-pegawai') ? 'active' : '' ?>" href="/pengaturan-urutan-pegawai"><i class="bi bi-list-ol"></i>Urutan Rekap Pegawai</a>
                </div>
            </details>

            <div class="nav-caption">Administrasi</div>
            <details <?= (str_starts_with($currentPath, 'akun') || str_starts_with($currentPath, 'backup')) ? 'open' : '' ?>>
                <summary>Akun & Sistem</summary>
                <div class="nav-sub">
                    <a class="<?= str_starts_with($currentPath, 'akun') ? 'active' : '' ?>" href="/akun"><i class="bi bi-person-gear"></i>Pengaturan Akun</a>
                    <a class="<?= str_starts_with($currentPath, 'backup') ? 'active' : '' ?>" href="/backup"><i class="bi bi-database-fill-down"></i>Backup & Pemulihan</a>
                </div>
            </details>
        </nav>
        <div class="sidebar-bottom"><a href="/logout"><i class="bi bi-box-arrow-left"></i>Keluar dari aplikasi</a></div>
    </aside>
    <div class="main-area">
        <header class="topbar"><div><div class="topbar-label">YAYASAN BINA INSANI GROBOGAN</div><div class="topbar-title"><?= esc($title ?? 'Sistem Manajemen Absensi') ?></div></div><div class="admin-chip"><div class="admin-avatar"><i class="bi bi-person-fill"></i></div><span><?= esc(session()->get('full_name') ?? session()->get('username') ?? 'Administrator') ?></span></div></header>
        <main class="content-wrap">
            <?php if ($msg = session()->getFlashdata('success')): ?><div class="alert alert-success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc($msg) ?></div><?php endif ?>
            <?php if ($msg = session()->getFlashdata('error')): ?><div class="alert alert-danger mb-3"><i class="bi bi-exclamation-circle-fill me-2"></i><?= esc($msg) ?></div><?php endif ?>
            <?= $this->renderSection('content') ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>