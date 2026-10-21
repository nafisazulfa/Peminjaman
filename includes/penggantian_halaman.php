<?php
$u = user();
$urlHalaman = '/' . $folder . '/penggantian.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ganti') {
    $id = (int)($_POST['id'] ?? 0);
    $baris = ambil_satu("SELECT jumlah_bermasalah - jumlah_diganti AS sisa FROM pengembalian WHERE id_pengembalian = ? AND status_ganti = 'belum'", 'i', [$id]);
    $jumlah = $baris ? (int)$baris['sisa'] : 0;
    if ($jumlah < 1) {
        pesan('danger', 'Data penggantian tidak ditemukan atau sudah selesai.');
    } else {
        try {
            panggil('CALL sp_konfirmasi_ganti(?, ?, ?)', 'iii', [$id, $u['id_user'], $jumlah]);
            catat('Konfirmasi alat pengganti ' . ringkas_kembali($id) . ' (' . $jumlah . ' unit)');
            pesan('success', $jumlah . ' unit alat pengganti diterima, stok bertambah.');
        } catch (mysqli_sql_exception $ex) {
            pesan('danger', pesan_error($ex));
        }
    }
    $balik = [];
    foreach (['status', 'dari', 'sampai'] as $k) if (is_string($_POST[$k] ?? null) && $_POST[$k] !== '') $balik[$k] = $_POST[$k];
    pindah($urlHalaman . ($balik ? '?' . http_build_query($balik) : ''));
}

$errors = [];
$status = $_GET['status'] ?? '';
$dari   = $_GET['dari'] ?? '';
$sampai = $_GET['sampai'] ?? '';
if (!in_array($status, ['', 'belum', 'sudah'], true)) { $errors[] = 'Status tidak valid.'; $status = ''; }
foreach (['dari' => &$dari, 'sampai' => &$sampai] as $nama => &$v) {
    if ($v !== '' && !tgl_valid($v)) { $errors[] = 'Format tanggal tidak valid.'; $v = ''; }
}
unset($v);
if ($dari !== '' && $sampai !== '' && $dari > $sampai) { $errors[] = 'Tanggal awal tidak boleh melebihi tanggal akhir.'; $dari = $sampai = ''; }

$sql = "SELECT r.id_pengembalian, r.id_peminjaman, r.kondisi, r.status_ganti, r.jumlah_bermasalah, r.jumlah_diganti,
               r.tanggal_kembali, r.tanggal_ganti, r.catatan, u.nama AS peminjam,
               CASE WHEN u.jenis = 'guru' THEN 'Guru' ELSE u.kelas END AS kelas, a.nama_alat, a.lokasi
        FROM pengembalian r
        JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman
        JOIN users u ON u.id_user = p.id_user
        JOIN alat a ON a.id_alat = p.id_alat
        WHERE r.status_ganti IN ('belum', 'sudah')";
$types = ''; $par = [];
if ($status !== '') { $sql .= ' AND r.status_ganti = ?'; $types .= 's'; $par[] = $status; }
if ($dari !== '')   { $sql .= ' AND r.tanggal_kembali >= ?'; $types .= 's'; $par[] = $dari; }
if ($sampai !== '') { $sql .= ' AND r.tanggal_kembali <= ?'; $types .= 's'; $par[] = $sampai; }
$sql .= " ORDER BY (r.status_ganti = 'belum') DESC, r.tanggal_kembali DESC, r.id_pengembalian DESC LIMIT 500";
$daftar = ambil_semua($sql, $types, $par);

$kasusBelum = $kasusSudah = $unitBelum = 0;
foreach ($daftar as $d) {
    if ($d['status_ganti'] === 'belum') { $kasusBelum++; $unitBelum += $d['jumlah_bermasalah'] - $d['jumlah_diganti']; }
    else $kasusSudah++;
}
$judul = 'Laporan Penggantian Alat';
include __DIR__ . '/header.php';
tampil_error($errors);

echo '<div class="row g-3 mb-3">';
kartu('Belum diganti', $kasusBelum, $unitBelum . ' unit stok masih kurang', 'col-6', $kasusBelum ? 'text-danger' : '', 'danger', 'telat');
kartu('Sudah diganti', $kasusSudah, 'kasus selesai, stok kembali', 'col-6', '', 'success', 'cek');
echo '</div>';
?>
<style>@media print { @page { size: A4 landscape; margin: 10mm; } }</style>
<form method="get" class="row g-2 align-items-end mb-3 d-print-none">
  <div class="col-6 col-md-3">
    <label class="form-label mb-1" for="status">Status penggantian</label>
    <select id="status" name="status" class="form-select form-select-sm">
      <option value="">Semua</option>
      <option value="belum" <?= $status === 'belum' ? 'selected' : '' ?>>Belum diganti</option>
      <option value="sudah" <?= $status === 'sudah' ? 'selected' : '' ?>>Sudah diganti</option>
    </select>
  </div>
  <?= field('Tgl kembali dari', 'dari', null, ['type' => 'date', 'col' => 'col-6 col-md-3', 'def' => $dari]) ?>
  <?= field('Sampai', 'sampai', null, ['type' => 'date', 'col' => 'col-6 col-md-3', 'def' => $sampai]) ?>
  <div class="col-6 col-md-3 d-flex gap-2">
    <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1"><?= ikon('filter', 16) ?>Filter</button>
    <button type="button" class="btn btn-secondary btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()"><?= ikon('cetak', 16) ?>Cetak</button>
  </div>
</form>

<div>
  <h2 class="text-dark h6 fw-bold mb-3">Daftar Penggantian Alat Rusak / Hilang</h2>
  <div class="d-none d-print-block mb-2">Tanggal kembali: <?= $dari !== '' ? e(tgl($dari)) : 'awal' ?> s/d <?= $sampai !== '' ? e(tgl($sampai)) : 'sekarang' ?> &middot; Dicetak <?= date('d-m-Y H:i') ?></div>
  <?php tabel_buka(['No', 'Kode', 'Peminjam', 'Kelas', 'Alat', 'Jumlah', 'Kondisi', 'Status', 'Tgl Kembali', 'Tgl Diganti', 'Aksi']); ?>
    <?php foreach ($daftar as $i => $d): $sisa = $d['jumlah_bermasalah'] - $d['jumlah_diganti']; ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td class="text-nowrap"><?= kode_pinjam($d['id_peminjaman']) ?></td>
        <td class="text-start"><?= e($d['peminjam']) ?></td>
        <td><?= e($d['kelas'] ?: '-') ?></td>
        <td class="text-start"><?= e($d['nama_alat']) ?><?= $d['catatan'] ? '<div class="small text-secondary">' . e($d['catatan']) . '</div>' : '' ?></td>
        <td><?= (int)$d['jumlah_bermasalah'] ?></td>
        <td><?= badge_kondisi($d['kondisi']) ?></td>
        <td><?= badge_ganti($d['status_ganti']) ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_kembali']) ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_ganti']) ?></td>
        <td class="d-print-none">
          <?php if ($d['status_ganti'] === 'belum'): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Terima <?= (int)$sisa ?> unit alat pengganti?')">
              <input type="hidden" name="aksi" value="ganti"><input type="hidden" name="id" value="<?= (int)$d['id_pengembalian'] ?>">
              <input type="hidden" name="status" value="<?= e($status) ?>"><input type="hidden" name="dari" value="<?= e($dari) ?>"><input type="hidden" name="sampai" value="<?= e($sampai) ?>">
              <button class="btn btn-success btn-sm text-nowrap">Sudah diganti</button>
            </form>
          <?php else: ?><span class="text-secondary">Selesai</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php tabel_tutup($daftar, 'Tidak ada data penggantian alat.'); ?>
</div>
<?php include __DIR__ . '/footer.php'; ?>
