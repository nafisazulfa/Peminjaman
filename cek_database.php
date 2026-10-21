<?php
require_once __DIR__ . '/config/fungsi.php';

$perlu = [
    'users'         => ['id_user', 'nama', 'username', 'password', 'role', 'no_hp', 'jenis', 'nomor_induk', 'kelas', 'created_at'],
    'kategori'      => ['id_kategori', 'nama_kategori', 'deskripsi'],
    'alat'          => ['id_alat', 'id_kategori', 'kode_alat', 'nama_alat', 'stok_total', 'stok_tersedia', 'lokasi', 'deskripsi', 'gambar'],
    'peminjaman'    => ['id_peminjaman', 'id_user', 'id_alat', 'jumlah', 'tanggal_pinjam', 'tanggal_jatuh_tempo', 'keperluan', 'status', 'id_petugas', 'alasan_tolak'],
    'pengembalian'  => ['id_pengembalian', 'id_peminjaman', 'tanggal_kembali', 'terlambat_hari', 'total_denda', 'kondisi', 'status_ganti', 'jumlah_bermasalah', 'jumlah_diganti', 'tanggal_ganti', 'denda_dibayar', 'id_petugas', 'catatan'],
    'notifikasi'    => ['id_notif', 'id_user', 'jenis', 'pesan', 'dibaca', 'created_at'],
    'log_aktivitas' => ['id_log', 'id_user', 'aktivitas', 'waktu'],
];
$rutin = ['fn_hitung_denda', 'sp_setujui_peminjaman', 'sp_kembalikan_alat', 'sp_periksa_pengembalian', 'sp_konfirmasi_ganti'];

$ada = [];
foreach (ambil_semua("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'db_peminjaman_lab'") as $r) {
    $ada[$r['TABLE_NAME']][] = $r['COLUMN_NAME'];
}
$adaRutin = [];
foreach (ambil_semua("SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = 'db_peminjaman_lab'") as $r) {
    $adaRutin[] = $r['ROUTINE_NAME'];
}

$masalah = [];
foreach ($perlu as $tabel => $kolom) {
    if (!isset($ada[$tabel])) { $masalah[] = "Tabel $tabel tidak ada."; continue; }
    foreach ($kolom as $k) if (!in_array($k, $ada[$tabel], true)) $masalah[] = "Kolom $tabel.$k belum ada.";
}
foreach ($rutin as $nama) if (!in_array($nama, $adaRutin, true)) $masalah[] = "Function/procedure $nama belum ada.";

$tanpaNomor = isset($ada['users']) ? ambil_semua("SELECT nama FROM users WHERE role = 'peminjam' AND (nomor_induk IS NULL OR nomor_induk = '')") : [];
$passwordWajib = ambil_satu("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'db_peminjaman_lab' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password'");
if ($passwordWajib && $passwordWajib['IS_NULLABLE'] === 'NO') $masalah[] = 'Kolom users.password masih wajib diisi (harus boleh kosong untuk login NISN/NIP).';
$param = ambil_semua("SELECT SPECIFIC_NAME, COUNT(*) AS n FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA = 'db_peminjaman_lab' AND PARAMETER_NAME IS NOT NULL GROUP BY SPECIFIC_NAME");
$jmlParam = array_column($param, 'n', 'SPECIFIC_NAME');
if (isset($jmlParam['sp_periksa_pengembalian']) && (int)$jmlParam['sp_periksa_pengembalian'] < 5) $masalah[] = 'Procedure sp_periksa_pengembalian masih versi lama.';
if (isset($jmlParam['sp_konfirmasi_ganti']) && (int)$jmlParam['sp_konfirmasi_ganti'] < 3) $masalah[] = 'Procedure sp_konfirmasi_ganti masih versi lama.';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cek Database</title>
  <?= css_tema() ?>
</head>
<body class="bg-light small">
<div class="container py-4"><div class="row"><div class="col-lg-8">
  <h1 class="h5 fw-bold mb-3">Cek Database</h1>
  <?php if (!$masalah): ?>
    <div class="alert alert-success">Struktur database sudah sesuai. Semua tabel, kolom, function, dan procedure lengkap.</div>
  <?php else: ?>
    <div class="alert alert-danger">Struktur database belum sesuai dengan aplikasi. Ditemukan <?= count($masalah) ?> masalah:</div>
    <ul class="list-group mb-3">
      <?php foreach ($masalah as $m): ?><li class="list-group-item"><?= e($m) ?></li><?php endforeach; ?>
    </ul>
    <div class="card"><div class="card-body">
      <div class="fw-bold mb-2">Cara memperbaiki (data lama tetap aman)</div>
      <ol class="mb-0">
        <li>Buka phpMyAdmin, pilih database <b>db_peminjaman_lab</b>.</li>
        <li>Klik tab <b>SQL</b>.</li>
        <li>Buka file <b>database/migrasi_tanpa_import_ulang.sql</b>, salin seluruh isinya, tempel, lalu klik <b>Kirim</b>.</li>
        <li>Muat ulang halaman ini sampai muncul pesan hijau.</li>
      </ol>
    </div></div>
  <?php endif; ?>
  <?php if ($tanpaNomor): ?>
    <div class="alert alert-warning">
      <?= count($tanpaNomor) ?> peminjam belum punya NISN/NIP sehingga tidak bisa login. Lengkapi lewat menu Data Peminjam:
      <?= e(implode(', ', array_column($tanpaNomor, 'nama'))) ?>.
    </div>
  <?php endif; ?>
  <a href="<?= BASE ?>/login.php" class="btn btn-primary btn-sm mt-3">Ke halaman login</a>
</div></div></div>
</body>
</html>
