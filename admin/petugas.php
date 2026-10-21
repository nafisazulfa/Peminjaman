<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    $aksi = is_string($_POST['aksi'] ?? null) ? $_POST['aksi'] : '';
    $id = (isset($_POST['id']) && is_string($_POST['id']) && ctype_digit($_POST['id'])) ? (int)$_POST['id'] : 0;

    if (!hash_equals($_SESSION['csrf'], $token)) {
        pesan('danger', 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.');
    } elseif ($aksi !== 'hapus' || $id < 1) {
        pesan('danger', 'Permintaan tidak valid.');
    } else {
        $petugas = ambil_satu("SELECT id_user, nama, username FROM users WHERE id_user = ? AND role = 'petugas'", 'i', [$id]);

        if (!$petugas) {
            pesan('danger', 'Data petugas tidak ditemukan.');
        } else {
            $dipakai = hitung('SELECT (SELECT COUNT(*) FROM peminjaman WHERE id_petugas = ?) + (SELECT COUNT(*) FROM pengembalian WHERE id_petugas = ?)', 'ii', [$id, $id]);

            if ($dipakai > 0) {
                pesan('warning', 'Petugas ' . $petugas['nama'] . ' sudah memproses ' . $dipakai . ' transaksi, sehingga tidak bisa dihapus.');
            } else {
                try {
                    jalankan("DELETE FROM users WHERE id_user = ? AND role = 'petugas'", 'i', [$id]);
                    catat('Hapus petugas ' . $petugas['username']);
                    pesan('success', 'Petugas ' . $petugas['nama'] . ' berhasil dihapus.');
                } catch (mysqli_sql_exception $ex) {
                    pesan('danger', pesan_error($ex));
                }
            }
        }
    }
    pindah('/admin/petugas.php');
}

$judul = 'Data Petugas';
$t = tabel_param(['nama' => 'nama', 'username' => 'username', 'hp' => 'no_hp', 'daftar' => 'created_at'], 'ASC');
$daftar = tabel_data($t, 'FROM users', 'id_user, nama, username, no_hp, created_at', ['nama', 'username', 'no_hp'], ["role = 'petugas'", '', []]);

include __DIR__ . '/../includes/header_admin.php';
?>

<?php bar_tabel($t, 'Cari nama, username, atau no HP', '/admin/petugas_form.php', 'Tambah Petugas'); ?>
<?php tabel_buka(['No', 'nama' => 'Nama', 'username' => 'Username', 'hp' => 'No HP', 'daftar' => 'Terdaftar', 'Aksi'], $t); ?>
  <?php foreach ($daftar as $i => $d): ?>
    <tr>
      <td><?= $t['offset'] + $i + 1 ?></td>
      <td class="text-start"><?= e($d['nama']) ?></td>
      <td><?= e($d['username']) ?></td>
      <td><?= e($d['no_hp'] ?: '-') ?></td>
      <td class="text-nowrap"><?= tgl($d['created_at']) ?></td>
      <td class="text-nowrap">
        <a href="<?= BASE ?>/admin/petugas_form.php?id=<?= (int)$d['id_user'] ?>" class="btn btn-warning btn-sm">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Yakin hapus petugas <?= e(addslashes($d['nama'])) ?>?')">
          <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="id" value="<?= (int)$d['id_user'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
<?php tabel_tutup($daftar, $t['q'] !== '' ? 'Data petugas tidak ditemukan.' : 'Belum ada data petugas.'); ?>
<?= paginasi($t['total'], $t['per'], $t['hal']) ?>

<?php include __DIR__ . '/../includes/footer_admin.php'; ?>
