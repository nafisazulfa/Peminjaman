<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['peminjam']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['aksi'] ?? '') === 'batal') {
        $ringkas = ringkas_pinjam($id);
        $st = jalankan("DELETE FROM peminjaman WHERE id_peminjaman = ? AND id_user = ? AND status = 'menunggu'", 'ii', [$id, user()['id_user']]);
        if ($st->affected_rows > 0) {
            catat('Membatalkan pengajuan peminjaman ' . $ringkas);
            pesan('success', 'Pengajuan berhasil dibatalkan.');
        } else {
            pesan('warning', 'Pengajuan tidak bisa dibatalkan karena sudah diproses petugas.');
        }
        pindah('/peminjam/pengembalian.php');
    }
    try {
        panggil('CALL sp_kembalikan_alat(?, ?)', 'ii', [$id, user()['id_user']]);
        catat('Mengembalikan alat ' . ringkas_pinjam($id) . ket_telat($id));
        $r = ambil_satu('SELECT terlambat_hari, total_denda FROM pengembalian WHERE id_peminjaman = ?', 'i', [$id]);
        if ($r && (int)$r['terlambat_hari'] > 0) {
            pesan('warning', 'Pengembalian Anda TERLAMBAT ' . (int)$r['terlambat_hari'] . ' hari. Denda keterlambatan ' . rp($r['total_denda'])
                . '. Serahkan alat ke petugas untuk diperiksa dan selesaikan denda sesuai arahan petugas.');
        } else {
            pesan('success', 'Alat dikembalikan tepat waktu. Serahkan alat ke petugas untuk diperiksa.');
        }
    } catch (mysqli_sql_exception $ex) {
        pesan('danger', pesan_error($ex));
    }
    pindah('/peminjam/pengembalian.php');
}

$t = tabel_param(['id' => 'p.id_peminjaman', 'alat' => 'a.nama_alat', 'pinjam' => 'p.tanggal_pinjam', 'tempo' => 'p.tanggal_jatuh_tempo', 'status' => 'p.status'], 'DESC');
$daftar = tabel_data($t, 'FROM peminjaman p JOIN alat a ON a.id_alat = p.id_alat LEFT JOIN pengembalian r ON r.id_peminjaman = p.id_peminjaman',
    'p.*, a.nama_alat, r.total_denda, r.terlambat_hari, r.kondisi, r.status_ganti, r.jumlah_bermasalah, r.jumlah_diganti, r.denda_dibayar', ['a.nama_alat', 'p.status'], ['p.id_user = ?', 'i', [user()['id_user']]]);
$perluGanti = array_filter($daftar, fn($d) => ($d['status_ganti'] ?? '') === 'belum');

$judul = 'Pengembalian Alat';
include __DIR__ . '/../includes/header.php';
if ($perluGanti) echo '<div class="alert alert-warning">Ada alat rusak atau hilang yang belum Anda ganti. Serahkan alat pengganti ke petugas lab; stok baru kembali setelah penggantian dikonfirmasi.</div>';
?>
<div>
  <?php bar_tabel($t, 'Cari alat atau status'); ?>
  <?php tabel_buka(['No', 'id' => 'Kode', 'alat' => 'Alat', 'Jml', 'pinjam' => 'Tgl Pinjam', 'tempo' => 'Tgl Kembali', 'status' => 'Status', 'Denda', 'Aksi'], $t); ?>
    <?php foreach ($daftar as $i => $d): ?>
      <tr>
        <td><?= $t['offset'] + $i + 1 ?></td>
        <td class="text-nowrap"><?= kode_pinjam($d['id_peminjaman']) ?></td>
        <td class="text-start"><?= e($d['nama_alat']) ?></td>
        <td><?= (int)$d['jumlah'] ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_pinjam']) ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_jatuh_tempo']) ?></td>
        <td>
          <?= status_pinjam($d) ?>
          <?php if ($d['status'] === 'ditolak' && $d['alasan_tolak']): ?><div class="small text-secondary"><?= e($d['alasan_tolak']) ?></div><?php endif; ?>
        </td>
        <td class="text-nowrap">
          <?php if ($d['status'] === 'dikembalikan'): ?><?= $d['total_denda'] > 0 ? rp($d['total_denda']) : '-' ?>
          <?php elseif ($d['status'] === 'dipinjam'): $dn = denda_berjalan($d['tanggal_jatuh_tempo'], $d['jumlah']); ?>
            <?= $dn > 0 ? '<span class="text-danger">' . rp($dn) . '</span>' : '-' ?>
          <?php else: ?>-<?php endif; ?>
        </td>
        <td class="text-nowrap">
          <?php if ($d['status'] === 'dipinjam'): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Kembalikan alat ini sekarang?')"><input type="hidden" name="id" value="<?= $d['id_peminjaman'] ?>">
              <button class="btn btn-sm btn-primary">Kembalikan</button></form>
          <?php endif; ?>
          <?php if ($d['status'] === 'menunggu'): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Batalkan pengajuan ini?')"><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= $d['id_peminjaman'] ?>">
              <button class="btn btn-sm btn-danger">Batalkan</button></form>
          <?php endif; ?>
          <?php if ($d['status'] !== 'ditolak'): ?>
            <a href="<?= BASE ?>/bukti.php?id=<?= $d['id_peminjaman'] ?>" class="btn btn-sm btn-secondary">Bukti</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php tabel_tutup($daftar, $t['q'] !== '' ? 'Tidak ada peminjaman yang cocok dengan pencarian.' : 'Belum ada peminjaman.'); ?>
  <?= paginasi($t['total'], $t['per'], $t['hal']) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
