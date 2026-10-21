<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['petugas']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'setujui') {
        try {
            panggil('CALL sp_setujui_peminjaman(?, ?)', 'ii', [$id, user()['id_user']]);
            catat('Menyetujui peminjaman ' . ringkas_pinjam($id));
            pesan('success', 'Peminjaman disetujui, stok alat dikurangi.');
        } catch (mysqli_sql_exception $ex) {
            pesan('danger', pesan_error($ex));
        }
    } elseif ($aksi === 'tolak') {
        $alasan = trim($_POST['alasan'] ?? '');
        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
            pesan('danger', 'Alasan penolakan harus 5-255 karakter.');
        } else {
            $st = jalankan("UPDATE peminjaman SET status = 'ditolak', alasan_tolak = ?, id_petugas = ? WHERE id_peminjaman = ? AND status = 'menunggu'",
                'sii', [$alasan, user()['id_user'], $id]);
            if ($st->affected_rows > 0) {
                catat('Menolak peminjaman ' . ringkas_pinjam($id));
                pesan('success', 'Pengajuan ditolak.');
            }
            else pesan('warning', 'Pengajuan sudah diproses sebelumnya.');
        }
    }
    pindah('/petugas/persetujuan.php');
}

$t = tabel_param(['id' => 'p.id_peminjaman', 'alat' => 'a.nama_alat', 'peminjam' => 'u.nama', 'pinjam' => 'p.tanggal_pinjam', 'tempo' => 'p.tanggal_jatuh_tempo'], 'ASC');
$daftar = tabel_data($t, 'FROM peminjaman p JOIN users u ON u.id_user = p.id_user JOIN alat a ON a.id_alat = p.id_alat',
    "p.*, u.nama AS peminjam, CASE WHEN u.jenis = 'guru' THEN 'Guru' ELSE u.kelas END AS kelas, a.nama_alat, a.gambar, a.stok_tersedia",
    ['u.nama', 'a.nama_alat', 'p.keperluan'], ["p.status = 'menunggu'", '', []]);
$judul = 'Persetujuan Peminjaman';
include __DIR__ . '/../includes/header.php';
?>
<div>
  <?php bar_tabel($t, 'Cari peminjam, alat, keperluan'); ?>
  <?php tabel_buka(['No', 'alat' => 'Alat', 'peminjam' => 'Peminjam', 'Kelas', 'Jumlah', 'pinjam' => 'Tgl Pinjam', 'tempo' => 'Tgl Kembali', 'Keperluan', 'Keputusan'], $t); ?>
    <?php foreach ($daftar as $i => $d): $kurang = $d['jumlah'] > $d['stok_tersedia']; ?>
      <tr>
        <td><?= $t['offset'] + $i + 1 ?></td>
        <td class="text-start"><?= e($d['nama_alat']) ?></td>
        <td class="text-start"><?= e($d['peminjam']) ?></td>
        <td><?= e($d['kelas'] ?: '-') ?></td>
        <td><?= (int)$d['jumlah'] ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_pinjam']) ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_jatuh_tempo']) ?></td>
        <td class="text-start"><?= e($d['keperluan']) ?></td>
        <td>
          <div class="d-flex flex-nowrap justify-content-center align-items-center gap-2">
            <form method="post"><input type="hidden" name="id" value="<?= $d['id_peminjaman'] ?>"><input type="hidden" name="aksi" value="setujui">
              <button class="btn btn-success btn-sm" <?= $kurang ? 'disabled title="Stok tidak cukup"' : '' ?>>Setujui</button></form>
            <form method="post" class="input-group input-group-sm flex-nowrap w-auto"><input type="hidden" name="id" value="<?= $d['id_peminjaman'] ?>"><input type="hidden" name="aksi" value="tolak">
              <input name="alasan" class="form-control form-control-sm" placeholder="Alasan menolak" minlength="5" maxlength="255" required>
              <button class="btn btn-danger btn-sm">Tolak</button></form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php tabel_tutup($daftar, $t['q'] !== '' ? 'Tidak ada pengajuan yang cocok dengan pencarian.' : 'Tidak ada pengajuan yang menunggu.'); ?>
  <?= paginasi($t['total'], $t['per'], $t['hal']) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
