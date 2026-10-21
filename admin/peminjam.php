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
    $kembali = 'siswa';

    if (!hash_equals($_SESSION['csrf'], $token)) {
        pesan('danger', 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.');
    } elseif ($aksi !== 'hapus' || $id < 1) {
        pesan('danger', 'Permintaan tidak valid.');
    } else {
        $anggota = ambil_satu("SELECT id_user, nama, nomor_induk AS username, jenis FROM users WHERE id_user = ? AND role = 'peminjam'", 'i', [$id]);

        if (!$anggota) {
            pesan('danger', 'Data anggota tidak ditemukan.');
        } else {
            $kembali = $anggota['jenis'] === 'guru' ? 'guru' : 'siswa';
            $riwayat = hitung('SELECT COUNT(*) FROM peminjaman WHERE id_user = ?', 'i', [$id]);

            if ($riwayat > 0) {
                pesan('warning', $anggota['nama'] . ' memiliki ' . $riwayat . ' riwayat peminjaman, sehingga tidak bisa dihapus.');
            } else {
                try {
                    jalankan("DELETE FROM users WHERE id_user = ? AND role = 'peminjam'", 'i', [$id]);
                    catat('Hapus anggota ' . $anggota['username']);
                    pesan('success', 'Anggota ' . $anggota['nama'] . ' berhasil dihapus.');
                } catch (mysqli_sql_exception $ex) {
                    pesan('danger', pesan_error($ex));
                }
            }
        }
    }
    pindah('/admin/peminjam.php?jenis=' . $kembali);
}

$judul = 'Data Peminjam';
$jenis = (isset($_GET['jenis']) && $_GET['jenis'] === 'guru') ? 'guru' : 'siswa';
$siswa = $jenis === 'siswa';

$jumlahSiswa = hitung("SELECT COUNT(*) FROM users WHERE role = 'peminjam' AND jenis = 'siswa'");
$jumlahGuru = hitung("SELECT COUNT(*) FROM users WHERE role = 'peminjam' AND jenis = 'guru'");

$t = tabel_param(['nama' => 'nama', 'induk' => 'nomor_induk', 'kelas' => 'kelas', 'hp' => 'no_hp'], 'ASC');
$daftar = tabel_data($t, 'FROM users', 'id_user, nama, nomor_induk, kelas, no_hp', ['nama', 'nomor_induk', 'kelas', 'no_hp'], ["role = 'peminjam' AND jenis = ?", 's', [$jenis]]);

$kolom = ['No', 'nama' => 'Nama', 'induk' => $siswa ? 'NISN' : 'NIP'];
if ($siswa) {
    $kolom['kelas'] = 'Kelas';
}
$kolom['hp'] = 'No HP';
$kolom[] = 'Aksi';

include __DIR__ . '/../includes/header_admin.php';
?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link<?= $siswa ? ' active' : '' ?>" href="<?= BASE ?>/admin/peminjam.php?jenis=siswa">Siswa <span class="badge text-bg-secondary"><?= $jumlahSiswa ?></span></a>
  </li>
  <li class="nav-item">
    <a class="nav-link<?= !$siswa ? ' active' : '' ?>" href="<?= BASE ?>/admin/peminjam.php?jenis=guru">Guru <span class="badge text-bg-secondary"><?= $jumlahGuru ?></span></a>
  </li>
</ul>

<?php bar_tabel($t, 'Cari nama, ' . ($siswa ? 'NISN, kelas' : 'NIP') . ', atau no HP', '/admin/peminjam_form.php?jenis=' . $jenis, 'Tambah Anggota'); ?>
<?php tabel_buka($kolom, $t); ?>
  <?php foreach ($daftar as $i => $d): ?>
    <tr>
      <td><?= $t['offset'] + $i + 1 ?></td>
      <td class="text-start"><?= e($d['nama']) ?></td>
      <td><?= e($d['nomor_induk'] ?: '-') ?></td>
      <?php if ($siswa): ?><td><?= e($d['kelas'] ?: '-') ?></td><?php endif; ?>
      <td><?= e($d['no_hp'] ?: '-') ?></td>
      <td class="text-nowrap">
        <a href="<?= BASE ?>/admin/peminjam_form.php?id=<?= (int)$d['id_user'] ?>" class="btn btn-warning btn-sm">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Yakin hapus anggota <?= e(addslashes($d['nama'])) ?>?')">
          <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="id" value="<?= (int)$d['id_user'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
<?php tabel_tutup($daftar, $t['q'] !== '' ? 'Data tidak ditemukan.' : 'Belum ada data ' . $jenis . '.'); ?>
<?= paginasi($t['total'], $t['per'], $t['hal']) ?>

<?php include __DIR__ . '/../includes/footer_admin.php'; ?>
