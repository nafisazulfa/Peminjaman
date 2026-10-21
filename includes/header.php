<?php
require_once __DIR__ . '/../config/fungsi.php';
$u = user();
$menu = [
    'admin' => [['admin/dashboard.php', 'Dashboard'], ['admin/petugas.php', 'Data Petugas'], ['admin/peminjam.php', 'Data Peminjam'], ['admin/kategori.php', 'Data Kategori'], ['admin/alat.php', 'Data Alat'],
                ['admin/peminjaman.php', 'Data Peminjaman'], ['admin/laporan.php', 'Laporan Peminjaman'], ['admin/penggantian.php', 'Penggantian Alat'], ['admin/log.php', 'Log Aktivitas']],
    'petugas' => [['petugas/dashboard.php', 'Dashboard'], ['petugas/persetujuan.php', 'Persetujuan'], ['petugas/pantau.php', 'Pantau Pengembalian'], ['petugas/penggantian.php', 'Penggantian Alat'], ['petugas/laporan.php', 'Laporan']],
    'peminjam' => [['peminjam/dashboard.php', 'Dashboard'], ['peminjam/dashboard.php#daftar-alat', 'Daftar Alat'], ['peminjam/ajukan.php', 'Ajukan Peminjaman'], ['peminjam/pengembalian.php', 'Pengembalian Alat']],
];
$ikon = [
    'dashboard.php'            => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
    'admin/user.php'           => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'admin/kategori.php'       => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1"/>',
    'admin/alat.php'           => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>',
    'admin/peminjaman.php'     => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>',
    'admin/laporan.php' => '<polyline points="9 10 4 15 9 20"/><path d="M20 4v7a4 4 0 0 1-4 4H4"/>',
    'admin/log.php'            => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
    'petugas/persetujuan.php'  => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14 9 11"/>',
    'petugas/pantau.php'       => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    'petugas/penggantian.php'  => '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
    'petugas/laporan.php'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8M16 17H8"/>',
    'peminjam/ajukan.php'      => '<circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>',
    'peminjam/pengembalian.php'=> '<polyline points="9 10 4 15 9 20"/><path d="M20 4v7a4 4 0 0 1-4 4H4"/>',
];
$warnaRole = ['admin' => 'primary', 'petugas' => 'warning', 'peminjam' => 'success'];
$ikon['admin/dashboard.php'] = $ikon['petugas/dashboard.php'] = $ikon['dashboard.php'];
$ikon['peminjam/dashboard.php'] = $ikon['dashboard.php'];
$ikon['peminjam/dashboard.php#daftar-alat'] = '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>';
$ikon['admin/penggantian.php'] = $ikon['petugas/penggantian.php'];
$ikon['admin/petugas.php'] = $ikon['admin/peminjam.php'] = $ikon['admin/user.php'];
$labelRole = ($u['role'] === 'peminjam' && !empty($u['jenis'])) ? $u['jenis'] : $u['role'];
$path = $_SERVER['SCRIPT_NAME'];
$pakaiSidebar = true;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($judul ?? 'Aplikasi') ?></title>
  <?= css_tema() ?>
</head>
<body class="app-body small">
<div class="d-flex">
    <div class="offcanvas-lg offcanvas-start sidebar-lab text-white flex-shrink-0 d-print-none lebar-sidebar" tabindex="-1" id="sidebar">
    <div class="offcanvas-header d-lg-none">
      <span class="offcanvas-title fw-semibold">Menu</span>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-3 sticky-top vh-100">
      <a class="d-flex align-items-center gap-3 text-white text-decoration-none px-1 pt-1" href="<?= BASE ?>/index.php">
        <span class="brand-tile"><svg width="34" height="34" fill="currentColor" viewBox="0 0 16 16"><path d="M13.5 3a.5.5 0 0 1 .5.5V11H2V3.5a.5.5 0 0 1 .5-.5zm-11-1A1.5 1.5 0 0 0 1 3.5V12h14V3.5A1.5 1.5 0 0 0 13.5 2zM0 12.5h16a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 12.5"/></svg></span>
        <span class="lh-sm"><span class="d-block fw-bold">Inventaris Lab</span><span class="d-block small text-white-50">Peminjaman Alat Sekolah</span></span>
      </a>
      <div class="sb-label">Menu <?= e(ucfirst($u['role'])) ?></div>
      <nav class="d-flex flex-column gap-1">
        <?php foreach ($menu[$u['role']] as [$url, $label]): $dasarUrl = preg_replace('/\.php.*$/', '', $url);
          $dasarFile = basename($dasarUrl); $berkas = basename($path, '.php');
          $menuAktif = strpos($url, '#') === false && strpos($path, dirname($dasarUrl) . '/') !== false && ($berkas === $dasarFile || strpos($berkas, $dasarFile . '_') === 0); ?>
          <a class="sb-link<?= $menuAktif ? ' aktif' : '' ?>" href="<?= BASE . '/' . $url ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><?= $ikon[$url] ?? '' ?></svg><?= $label ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>

    <div class="flex-grow-1 overflow-x-auto">
    <header class="topbar px-4 py-2 d-flex align-items-center d-print-none">
      <button class="btn btn-secondary btn-sm d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">Menu</button>
      <div class="me-auto lh-sm">
        <h1 class="h6 fw-bold text-dark mb-0"><?= e($judul ?? '') ?></h1>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span class="d-none d-sm-inline">Halo, <b><?= e($u['nama']) ?></b> <span class="badge text-bg-<?= $warnaRole[$u['role']] ?? 'secondary' ?>"><?= e($labelRole) ?></span></span>
        <form method="post" action="<?= BASE ?>/logout.php" class="m-0"><button class="btn btn-danger btn-sm py-0 px-2 small d-inline-flex align-items-center gap-1"><?= ikon('keluar', 14) ?>Logout</button></form>
      </div>
    </header>
<main class="container-xxl px-4 py-4 pb-5">
  <?php tampil_pesan(); ?>
  <?php notifikasi_peminjam(); ?>
