<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);
$errors = [];

// Laporan hanya memuat peminjaman yang SELESAI: sudah dikembalikan, kondisi sudah diperiksa,
// alat rusak/hilang sudah diganti, dan denda (jika ada) sudah dibayar.
$syaratSelesai = "p.status = 'dikembalikan' AND r.kondisi <> 'belum_diperiksa' AND r.status_ganti <> 'belum'
    AND (r.total_denda = 0 OR r.denda_dibayar IS NOT NULL)";

$dari   = is_string($_GET['dari'] ?? null) ? $_GET['dari'] : '';
$sampai = is_string($_GET['sampai'] ?? null) ? $_GET['sampai'] : '';
$jenis  = is_string($_GET['jenis'] ?? null) ? $_GET['jenis'] : '';
$waktu  = is_string($_GET['waktu'] ?? null) ? $_GET['waktu'] : '';

if (($dari !== '' && !tgl_valid($dari)) || ($sampai !== '' && !tgl_valid($sampai))) {
    $errors[] = 'Format tanggal tidak valid.';
    $dari = $sampai = '';
} elseif ($dari !== '' && $sampai !== '' && $dari > $sampai) {
    $errors[] = 'Tanggal awal tidak boleh melebihi tanggal akhir.';
    $dari = $sampai = '';
}
if (!in_array($jenis, ['', 'siswa', 'guru'], true)) { $errors[] = 'Pilihan peminjam tidak valid.'; $jenis = ''; }
if (!in_array($waktu, ['', 'tepat', 'telat'], true)) { $errors[] = 'Pilihan ketepatan tidak valid.'; $waktu = ''; }

$where = $syaratSelesai;
$types = '';
$par = [];
if ($dari !== '')   { $where .= ' AND r.tanggal_kembali >= ?'; $types .= 's'; $par[] = $dari; }
if ($sampai !== '') { $where .= ' AND r.tanggal_kembali <= ?'; $types .= 's'; $par[] = $sampai; }
if ($jenis !== '')  { $where .= ' AND u.jenis = ?'; $types .= 's'; $par[] = $jenis; }
if ($waktu === 'tepat') $where .= ' AND r.terlambat_hari = 0';
if ($waktu === 'telat') $where .= ' AND r.terlambat_hari > 0';

$daftar = ambil_semua("SELECT p.id_peminjaman, p.jumlah, p.tanggal_pinjam, p.tanggal_jatuh_tempo,
        u.nama AS peminjam, u.jenis, u.kelas, a.nama_alat, a.lokasi,
        r.tanggal_kembali, r.terlambat_hari, r.total_denda, r.kondisi, r.status_ganti, r.jumlah_bermasalah
    FROM pengembalian r
    JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman
    JOIN users u ON u.id_user = p.id_user
    JOIN alat a ON a.id_alat = p.id_alat
    WHERE $where ORDER BY r.tanggal_kembali DESC, p.id_peminjaman DESC LIMIT 500", $types, $par);

$jumlahTelat = 0; $totalDenda = 0; $totalUnit = 0;
foreach ($daftar as $d) {
    if ((int)$d['terlambat_hari'] > 0) $jumlahTelat++;
    $totalDenda += (float)$d['total_denda'];
    $totalUnit += (int)$d['jumlah'];
}

if ($dari !== '' && $sampai !== '') $periode = tgl($dari) . ' s/d ' . tgl($sampai);
elseif ($dari !== '') $periode = 'sejak ' . tgl($dari);
elseif ($sampai !== '') $periode = 'sampai ' . tgl($sampai);
else $periode = 'Semua periode';

$judul = 'Laporan Peminjaman';
include __DIR__ . '/../includes/header.php';
tampil_error($errors);
?>
<style>@media print { @page { size: A4 landscape; margin: 10mm; } }</style>
<div class="d-none d-print-block text-center mb-3">
  <h2 class="h5 fw-bold mb-1">Laporan Peminjaman Alat Laboratorium</h2>
  <div>Peminjaman yang sudah selesai &middot; Tanggal kembali: <?= e($periode) ?></div>
</div>

<div class="row g-3 mb-3">
  <?php
  kartu('Total Transaksi', count($daftar), '', 'col-12 col-md-6', '', 'success', 'kalender');
  kartu('Jumlah Denda', rp($totalDenda), '', 'col-12 col-md-6', '', 'danger', 'uang');
  ?>
</div>

<div class="card mb-3 d-print-none"><div class="card-body">
  <form method="get" class="row g-3 align-items-end">
    <?= field('Tgl kembali dari', 'dari', null, ['type' => 'date', 'col' => 'col-6 col-lg-3', 'def' => $dari]) ?>
    <?= field('Sampai', 'sampai', null, ['type' => 'date', 'col' => 'col-6 col-lg-3', 'def' => $sampai]) ?>
    <div class="col-6 col-lg-3">
      <label class="form-label mb-1" for="jenis">Peminjam</label>
      <select id="jenis" name="jenis" class="form-select form-select-sm">
        <option value="">Semua</option>
        <option value="siswa" <?= $jenis === 'siswa' ? 'selected' : '' ?>>Siswa</option>
        <option value="guru" <?= $jenis === 'guru' ? 'selected' : '' ?>>Guru</option>
      </select>
    </div>
    <div class="col-6 col-lg-3">
      <label class="form-label mb-1" for="waktu">Ketepatan</label>
      <select id="waktu" name="waktu" class="form-select form-select-sm">
        <option value="">Semua</option>
        <option value="tepat" <?= $waktu === 'tepat' ? 'selected' : '' ?>>Tepat waktu</option>
        <option value="telat" <?= $waktu === 'telat' ? 'selected' : '' ?>>Terlambat</option>
      </select>
    </div>
    <div class="col-12 d-flex flex-wrap gap-2 justify-content-end border-top pt-3">
      <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1"><?= ikon('filter', 16) ?>Filter</button>
      <a href="<?= BASE ?>/admin/laporan.php" class="btn btn-secondary btn-sm">Reset</a>
      <button type="button" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()"><?= ikon('cetak', 16) ?>Cetak</button>
    </div>
  </form>
</div></div>

<div class="card"><div class="card-body p-3">
<?php tabel_buka(['No', 'Kode', 'Peminjam', 'Alat', 'Jml', 'Tgl Pinjam', 'Tgl Kembali', 'Ketepatan', 'Kondisi', 'Denda', 'Aksi']); ?>
  <?php foreach ($daftar as $i => $d): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td class="text-nowrap"><?= kode_pinjam($d['id_peminjaman']) ?></td>
      <td class="text-start"><?= e($d['peminjam']) ?> <span class="text-secondary"><?= e($d['jenis'] === 'guru' ? 'Guru' : $d['kelas']) ?></span></td>
      <td class="text-start"><?= e($d['nama_alat']) ?> <span class="text-secondary"><?= e($d['lokasi']) ?></span></td>
      <td><?= (int)$d['jumlah'] ?></td>
      <td class="text-nowrap"><?= tgl($d['tanggal_pinjam']) ?></td>
      <td class="text-nowrap"><?= tgl($d['tanggal_kembali']) ?></td>
      <td><?= badge_ketepatan($d['terlambat_hari']) ?></td>
      <td><?= badge_kondisi($d['kondisi']) ?><?= (int)$d['jumlah_bermasalah'] > 0 ? ' <span class="small text-secondary">' . (int)$d['jumlah_bermasalah'] . ' unit diganti</span>' : '' ?></td>
      <td class="text-nowrap"><?= $d['total_denda'] > 0 ? rp($d['total_denda']) . ' <span class="small text-secondary">lunas</span>' : '-' ?></td>
      <td class="text-nowrap d-print-none"><a href="<?= BASE ?>/admin/peminjaman_detail.php?id=<?= (int)$d['id_peminjaman'] ?>" class="btn btn-sm btn-info d-inline-flex align-items-center gap-1"><?= ikon('mata', 14) ?>Detail</a></td>
    </tr>
  <?php endforeach; ?>
<?php tabel_tutup($daftar, 'Tidak ada peminjaman selesai yang cocok dengan filter.'); ?>
</div></div>

<div class="d-none d-print-block small text-secondary mt-3">Dicetak pada <?= date('d-m-Y H:i') ?> oleh <?= e($u['nama']) ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
