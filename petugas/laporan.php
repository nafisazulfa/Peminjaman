<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['petugas']);
$errors = [];
$statusList = ['menunggu', 'dipinjam', 'ditolak', 'dikembalikan'];
$dari   = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-t');
$status = $_GET['status'] ?? '';
$waktu  = $_GET['waktu'] ?? '';

if (!tgl_valid($dari) || !tgl_valid($sampai)) {
    $errors[] = 'Format tanggal tidak valid.';
    $dari = date('Y-m-01'); $sampai = date('Y-m-t');
} elseif ($dari > $sampai) {
    $errors[] = 'Tanggal awal tidak boleh melebihi tanggal akhir.';
    $dari = date('Y-m-01'); $sampai = date('Y-m-t');
}
if (!in_array($waktu, ['', 'tepat', 'telat'], true)) { $errors[] = 'Pilihan ketepatan tidak valid.'; $waktu = ''; }
if ($status !== '' && !in_array($status, $statusList, true)) {
    $errors[] = 'Status tidak valid.';
    $status = '';
}

$sql = 'SELECT p.id_peminjaman, u.nama AS peminjam, CASE WHEN u.jenis = \'guru\' THEN \'Guru\' ELSE u.kelas END AS kelas, a.nama_alat, a.lokasi AS lab, k.nama_kategori, p.jumlah, p.tanggal_pinjam,
               p.tanggal_jatuh_tempo, p.status, r.kondisi, r.status_ganti, r.terlambat_hari, COALESCE(r.total_denda, 0) AS denda, r.denda_dibayar
        FROM peminjaman p
        JOIN users u ON u.id_user = p.id_user
        JOIN alat a ON a.id_alat = p.id_alat
        JOIN kategori k ON k.id_kategori = a.id_kategori
        LEFT JOIN pengembalian r ON r.id_peminjaman = p.id_peminjaman
        WHERE p.tanggal_pinjam BETWEEN ? AND ?';
$types = 'ss';
$par = [$dari, $sampai];
if ($status !== '') { $sql .= ' AND p.status = ?'; $types .= 's'; $par[] = $status; }
if ($waktu === 'tepat') { $sql .= ' AND r.id_pengembalian IS NOT NULL AND r.terlambat_hari = 0'; }
if ($waktu === 'telat') { $sql .= ' AND r.terlambat_hari > 0'; }
$sql .= ' ORDER BY p.tanggal_pinjam DESC, p.id_peminjaman DESC LIMIT 500';
$daftar = ambil_semua($sql, $types, $par);
$judul = 'Laporan Peminjaman';
include __DIR__ . '/../includes/header.php';
tampil_error($errors);
?>
<style>@media print { @page { size: A4 landscape; margin: 10mm; } }</style>
<form method="get" class="row g-2 align-items-end mb-3 d-print-none">
  <?= field('Dari tanggal', 'dari', null, ['type' => 'date', 'col' => 'col-6 col-md-3 col-xl-2', 'def' => $dari]) ?>
  <?= field('Sampai tanggal', 'sampai', null, ['type' => 'date', 'col' => 'col-6 col-md-3 col-xl-2', 'def' => $sampai]) ?>
  <div class="col-6 col-md-3 col-xl-2">
    <label class="form-label mb-1" for="status">Status</label>
    <select id="status" name="status" class="form-select form-select-sm">
      <option value="">Semua status</option>
      <?php foreach ($statusList as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-3 col-xl-2">
    <label class="form-label mb-1" for="waktu">Pengembalian</label>
    <select id="waktu" name="waktu" class="form-select form-select-sm">
      <option value="">Semua</option>
      <option value="tepat" <?= $waktu === 'tepat' ? 'selected' : '' ?>>Tepat waktu</option>
      <option value="telat" <?= $waktu === 'telat' ? 'selected' : '' ?>>Terlambat</option>
    </select>
  </div>
  <div class="col-12 col-xl-4 d-flex gap-2">
    <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1"><?= ikon('filter', 16) ?>Filter</button>
    <button type="button" class="btn btn-secondary btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()"><?= ikon('cetak', 16) ?>Cetak</button>
  </div>
</form>

<div class="d-none d-print-block text-center mb-3">
  <h2 class="h5 fw-bold mb-1">Laporan Peminjaman Alat Laboratorium</h2>
  <div>Tanggal pinjam: <?= e(tgl($dari)) ?> s/d <?= e(tgl($sampai)) ?><?= $status !== '' ? ' &middot; Status: ' . e(ucfirst($status)) : '' ?><?= $waktu === 'tepat' ? ' &middot; Tepat waktu' : ($waktu === 'telat' ? ' &middot; Terlambat' : '') ?></div>
</div>

<div>
  <?php tabel_buka(['No', 'Peminjam', 'Kelas / Guru', 'Alat', 'Lab', 'Kategori', 'Jml', 'Pinjam', 'Jatuh Tempo', 'Status', 'Denda']); ?>
    <?php foreach ($daftar as $i => $d): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td class="text-start"><?= e($d['peminjam']) ?></td>
        <td><?= e($d['kelas'] ?: '-') ?></td>
        <td class="text-start"><?= e($d['nama_alat']) ?></td>
        <td><?= e($d['lab'] ?: '-') ?></td>
        <td><?= e($d['nama_kategori']) ?></td>
        <td><?= (int)$d['jumlah'] ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_pinjam']) ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_jatuh_tempo']) ?></td>
        <td><?= status_pinjam($d) ?></td>
        <td class="text-nowrap"><?= $d['denda'] > 0 ? rp($d['denda']) : '-' ?></td>
      </tr>
    <?php endforeach; ?>
  <?php tabel_tutup($daftar, 'Tidak ada data pada periode ini.'); ?>
</div>
<div class="d-none d-print-block small text-secondary mt-3">Dicetak pada <?= date('d-m-Y H:i') ?> oleh <?= e($u['nama']) ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
