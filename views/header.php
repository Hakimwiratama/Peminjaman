<?php
$user = current_user();
$roleNames = ['admin' => 'Admin SIPINTAR','institution_admin'=>'Admin Institusi','staff'=>'Petugas','user'=>'Siswa / Mahasiswa'];
$title = ['dashboard'=>'Dashboard','catalog'=>'Katalog','borrowings'=>'Peminjaman','reports'=>'Laporan','users'=>'Data siswa/mahasiswa','profile'=>'Profil','settings'=>'Pengaturan institusi','institutions'=>'Institusi','complaints'=>'Keluhan','awaiting_approval'=>'Persetujuan institusi','login'=>'Masuk','register'=>'Pendaftaran akun','register_institution'=>'Daftar institusi','not_found'=>'Halaman tidak ditemukan'][$page] ?? 'SIPINTAR';
$flashMessage = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#142d4e">
    <title><?= e($title) ?> · <?= e($site_name ?? 'SIPINTAR') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <script defer src="assets/js/app.js"></script>
</head>
<body class="<?= $user && $page!=='awaiting_approval' ? 'app-body' : 'auth-body' ?>">
<?php if ($user && $page!=='awaiting_approval'): ?>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="?page=dashboard"><span class="brand-mark">S</span><span><?= e($site_name ?? 'SIPINTAR') ?><small>SMART LOAN SYSTEM</small></span></a>
    <div class="side-label">MENU UTAMA</div>
    <nav class="side-nav">
        <a class="<?= $page==='dashboard'?'active':'' ?>" href="?page=dashboard"><span>⌂</span> Dashboard</a>
        <?php if ($user['role'] === 'admin'): ?>
            <a class="<?= $page==='institutions'?'active':'' ?>" href="?page=institutions"><span>▦</span> Persetujuan sekolah/kampus <?php if(!empty($pending_institution_count)): ?><b class="nav-count"><?= (int)$pending_institution_count ?></b><?php endif; ?></a>
            <a class="<?= $page==='reports'?'active':'' ?>" href="?page=reports"><span>▤</span> Laporan global</a>
            <a class="<?= $page==='complaints'?'active':'' ?>" href="?page=complaints"><span>⚑</span> Keluhan global</a>
            <?php if($tenant_id): ?><a href="?page=catalog&type=item"><span>▣</span> Portal institusi aktif</a><?php endif; ?>
        <?php elseif ($user['role'] === 'user'): ?>
            <div class="side-label">LAYANAN</div>
            <a href="?page=catalog&type=item"><span>▣</span> Katalog peminjaman</a>
            <a href="?page=borrowings"><span>◷</span> Peminjaman saya</a>
            <?php if(feature_enabled($pdo,'complaints')): ?><a class="<?= $page==='complaints'?'active':'' ?>" href="?page=complaints"><span>⚑</span> Keluhan</a><?php endif; ?>
            <div class="side-label">AKUN</div>
        <?php else: ?>
            <a class="<?= $page==='borrowings'?'active':'' ?>" href="?page=borrowings"><span>◷</span> Permohonan <?php if(isset($pending_count)&&$pending_count): ?><b class="nav-count"><?= (int)$pending_count ?></b><?php endif; ?></a>
            <a class="<?= $page==='catalog'?'active':'' ?>" href="?page=catalog&type=item"><span>▣</span> Katalog & inventaris</a>
            <a class="<?= $page==='users'?'active':'' ?>" href="?page=users"><span>♙</span> Data siswa/mahasiswa</a>
            <a class="<?= $page==='complaints'?'active':'' ?>" href="?page=complaints"><span>⚑</span> Keluhan</a>
            <?php if ($user['role']==='institution_admin'): ?><a class="<?= $page==='settings'?'active':'' ?>" href="?page=settings"><span>⚙</span> Pengaturan institusi</a><?php endif; ?>
            <a class="<?= $page==='reports'?'active':'' ?>" href="?page=reports"><span>▤</span> Laporan</a>
            <div class="side-label">AKUN</div>
        <?php endif; ?>
        <a class="<?= $page==='profile'?'active':'' ?>" href="?page=profile"><span>⚙</span> Profil saya</a>
    </nav>
    <div class="sidebar-bottom">
        <div class="help-card"><span class="help-icon">✦</span><strong>Butuh bantuan?</strong><small>Hubungi administrator sekolah Anda.</small></div>
        <div class="user-mini"><div class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'],0,1))) ?></div><div class="user-meta"><strong><?= e($user['name']) ?></strong><small><?= e($roleNames[$user['role']] ?? $user['role']) ?></small></div>
            <form method="post" class="logout-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button title="Keluar" aria-label="Keluar">↗</button></form>
        </div>
    </div>
</aside>
<main class="main-content">
    <header class="topbar"><button class="menu-toggle" id="menuToggle" aria-label="Buka menu">☰</button><div class="breadcrumbs">SIPINTAR <span>/</span> <?= e($title) ?></div><div class="topbar-right"><span class="today"><?= e(date('l, d M Y')) ?></span><div class="avatar avatar-small"><?= e(mb_strtoupper(mb_substr($user['name'],0,1))) ?></div></div></header>
    <div class="page-content">
<?php else: ?>
<main class="auth-shell">
<?php endif; ?>
<?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?><button class="alert-close" aria-label="Tutup">×</button></div><?php endif; ?>
