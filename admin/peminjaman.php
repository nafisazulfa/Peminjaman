<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['aksi'] ?? '') === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $p = ambil_satu('SELECT p.status, r.kondisi, r.status_ganti FROM peminjaman p
                         LEFT JOIN pengembalian r ON r.id_peminjaman = p.id_peminjaman WHERE p.id_peminjaman = ?', 'i', [$id]);
        if (!$p) {
            pesan('danger', 'Data tidak ditemukan.');
        } elseif ($p['status'] === 'dipinjam') {
            pesan('danger', 'Peminjaman yang sedang berjalan tidak bisa dihapus. Selesaikan pengembaliannya dulu.');
        } elseif ($p['status'] === 'dikembalikan' && ($p['kondisi'] === 'belum_diperiksa' || $p['status_ganti'] === 'belum')) {
            pesan('danger', 'Pengembalian belum selesai diperiksa atau diganti, belum bisa dihapus.');
        } else {
            $ringkas = ringkas_pinjam($id);
            $db->begin_transaction();
            try {
                jalankan('DELETE FROM pengembalian WHERE id_peminjaman = ?', 'i', [$id]);
                jalankan('DELETE FROM peminjaman WHERE id_peminjaman = ?', 'i', [$id]);
                $db->commit();
                catat('Hapus peminjaman ' . $ringkas);
                pesan('success', 'Data peminjaman berhasil dihapus.');
            } catch (mysqli_sql_exception $ex) {
                $db->rollback();
                pesan('danger', pesan_error($ex));
            }
        }
        pindah('/admin/peminjaman.php');
    }
}

// Data peminjaman yang belum selesai: menunggu persetujuan, sedang dipinjam (termasuk terlambat), dan alat rusak/hilang yang belum diganti.
$syarat = "(p.status IN ('menunggu', 'dipinjam') OR (p.status = 'dikembalikan' AND r.status_ganti = 'belum'))";

$t = tabel_param(['id' => 'p.id_peminjaman', 'alat' => 'a.nama_alat', 'peminjam' => 'u.nama', 'pinjam' => 'p.tanggal_pinjam', 'tempo' => 'p.tanggal_jatuh_tempo', 'status' => 'p.status'], 'DESC');
$daftar = tabel_data($t, 'FROM peminjaman p JOIN users u ON u.id_user = p.id_user JOIN alat a ON a.id_alat = p.id_alat
    LEFT JOIN pengembalian r ON r.id_peminjaman = p.id_peminjaman',
    'p.id_peminjaman, p.jumlah, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status, u.nama AS peminjam, a.nama_alat,
     r.kondisi, r.status_ganti, r.total_denda, r.denda_dibayar',
    ['u.nama', 'a.nama_alat', 'p.status', "DATE_FORMAT(p.tanggal_pinjam, '%d-%m-%Y')"],
    [$syarat, '', []]);
$judul = 'Data Peminjaman';
include __DIR__ . '/../includes/header.php';
?>
  <?php bar_tabel($t, 'Cari peminjam, alat, tanggal'); ?>
  <?php tabel_buka(['No', 'id' => 'Kode', 'alat' => 'Alat', 'peminjam' => 'Peminjam', 'Jml', 'pinjam' => 'Tgl Pinjam', 'tempo' => 'Jatuh Tempo', 'status' => 'Status', 'Aksi'], $t); ?>
    <?php foreach ($daftar as $i => $d): ?>
      <tr>
        <td><?= $t['offset'] + $i + 1 ?></td>
        <td class="text-nowrap"><?= kode_pinjam($d['id_peminjaman']) ?></td>
        <td class="text-start"><?= e($d['nama_alat']) ?></td>
        <td class="text-start"><?= e($d['peminjam']) ?></td>
        <td><?= (int)$d['jumlah'] ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_pinjam']) ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_jatuh_tempo']) ?></td>
        <td><?= status_pinjam($d) ?></td>
        <td class="text-nowrap">
          <?php if ($d['status'] !== 'ditolak'): ?><a href="<?= BASE ?>/bukti.php?id=<?= $d['id_peminjaman'] ?>" class="btn btn-sm btn-secondary">Bukti</a><?php endif; ?>
          <?= form_hapus($d['id_peminjaman']) ?>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php tabel_tutup($daftar, $t['q'] !== '' ? 'Tidak ada peminjaman yang cocok dengan pencarian.' : 'Tidak ada peminjaman yang menunggu, dipinjam, atau belum diganti.'); ?>
  <?= paginasi($t['total'], $t['per'], $t['hal']) ?>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
