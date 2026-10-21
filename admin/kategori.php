<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['aksi'] ?? '') === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            jalankan('DELETE FROM kategori WHERE id_kategori = ?', 'i', [$id]);
            catat("Hapus kategori #$id");
            pesan('success', 'Kategori berhasil dihapus.');
        } catch (mysqli_sql_exception $ex) {
            pesan('danger', pesan_error($ex));
        }
        pindah('/admin/kategori.php');
    }
}

$t = tabel_param(['nama' => 'k.nama_kategori', 'jumlah' => 'jumlah_alat'], 'ASC');
$daftar = tabel_data($t, 'FROM kategori k', 'k.*, (SELECT COUNT(*) FROM alat a WHERE a.id_kategori = k.id_kategori) AS jumlah_alat', ['k.nama_kategori', 'k.deskripsi']);
$judul = 'Data Kategori';
include __DIR__ . '/../includes/header.php';
?>
  <?php bar_tabel($t, 'Cari nama atau deskripsi kategori', '/admin/kategori_form.php', 'Tambah Kategori'); ?>
  <?php tabel_buka(['No', 'nama' => 'Kategori', 'Deskripsi', 'jumlah' => 'Jumlah Alat', 'Aksi'], $t); ?>
    <?php foreach ($daftar as $i => $d): ?>
      <tr>
        <td><?= $t['offset'] + $i + 1 ?></td>
        <td class="text-start"><?= e($d['nama_kategori']) ?></td>
        <td class="text-start"><?= e($d['deskripsi'] ?: '-') ?></td>
        <td><?= (int)$d['jumlah_alat'] ?></td>
        <td class="text-nowrap">
          <a href="<?= BASE ?>/admin/kategori_form.php?id=<?= $d['id_kategori'] ?>" class="btn btn-sm btn-warning">Edit</a>
          <?= form_hapus($d['id_kategori']) ?>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php tabel_tutup($daftar, $t['q'] !== '' ? 'Tidak ada kategori yang cocok dengan pencarian.' : 'Belum ada kategori.'); ?>
  <?= paginasi($t['total'], $t['per'], $t['hal']) ?>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
