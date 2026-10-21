<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['aksi'] ?? '') === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $lama = ambil_satu('SELECT gambar FROM alat WHERE id_alat = ?', 'i', [$id]);
        try {
            jalankan('DELETE FROM alat WHERE id_alat = ?', 'i', [$id]);
            if ($lama) hapus_gambar($lama['gambar']);
            catat("Hapus alat #$id");
            pesan('success', 'Alat berhasil dihapus.');
        } catch (mysqli_sql_exception $ex) {
            pesan('danger', pesan_error($ex));
        }
        pindah('/admin/alat.php');
    }
}

$t = tabel_param(['nama' => 'a.nama_alat', 'kode' => 'a.kode_alat', 'kategori' => 'k.nama_kategori', 'stok' => 'a.stok_tersedia', 'lab' => 'a.lokasi'], 'ASC');
$daftar = tabel_data($t, 'FROM alat a JOIN kategori k ON k.id_kategori = a.id_kategori', 'a.*, k.nama_kategori', ['a.nama_alat', 'a.kode_alat', 'k.nama_kategori', 'a.lokasi']);
$judul = 'Data Alat';
include __DIR__ . '/../includes/header.php';
?>
<div>
  <?php bar_tabel($t, 'Cari nama, kode, kategori, lab', '/admin/alat_form.php', 'Tambah Alat'); ?>
  <?php tabel_buka(['No', 'Gambar', 'kode' => 'Kode', 'nama' => 'Nama Alat', 'kategori' => 'Kategori', 'stok' => 'Tersedia / Total', 'lab' => 'Lab', 'Aksi'], $t); ?>
    <?php foreach ($daftar as $i => $d): ?>
      <tr>
        <td><?= $t['offset'] + $i + 1 ?></td>
        <td><?= thumb($d['gambar']) ?></td>
        <td><?= e($d['kode_alat']) ?></td>
        <td class="text-start"><?= e($d['nama_alat']) ?></td>
        <td><?= e($d['nama_kategori']) ?></td>
        <td><?= (int)$d['stok_tersedia'] ?> / <?= (int)$d['stok_total'] ?></td>
        <td><?= e($d['lokasi'] ?: '-') ?></td>
        <td class="text-nowrap">
          <a href="<?= BASE ?>/admin/alat_form.php?id=<?= $d['id_alat'] ?>" class="btn btn-sm btn-warning">Edit</a>
          <?= form_hapus($d['id_alat']) ?>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php tabel_tutup($daftar, $t['q'] !== '' ? 'Tidak ada alat yang cocok dengan pencarian.' : 'Belum ada alat.'); ?>
  <?= paginasi($t['total'], $t['per'], $t['hal']) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
