<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['petugas']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';
    try {
        if ($aksi === 'periksa') {
            $kondisi = $_POST['kondisi'] ?? '';
            $catatan = trim($_POST['catatan'] ?? '');
            $jumlahMasalah = ($kondisi === 'baik') ? 0 : (int)($_POST['jumlah_masalah'] ?? 0);
            if (!in_array($kondisi, ['baik', 'rusak', 'hilang'], true)) {
                pesan('danger', 'Pilih kondisi alat yang valid.');
            } elseif ($kondisi !== 'baik' && mb_strlen($catatan) < 3) {
                pesan('danger', 'Catatan wajib diisi (minimal 3 karakter) untuk alat rusak atau hilang.');
            } elseif (mb_strlen($catatan) > 255) {
                pesan('danger', 'Catatan maksimal 255 karakter.');
            } else {
                panggil('CALL sp_periksa_pengembalian(?, ?, ?, ?, ?)', 'isisi', [$id, $kondisi, user()['id_user'], $catatan, $jumlahMasalah]);
                catat('Periksa pengembalian ' . ringkas_kembali($id) . ': ' . $kondisi . ($jumlahMasalah > 0 ? ' ' . $jumlahMasalah . ' unit' : ''));
                pesan('success', $kondisi === 'baik' ? 'Alat baik, stok bertambah.' : 'Kondisi dicatat. ' . $jumlahMasalah . ' unit harus diganti peminjam; stok bertambah setelah diganti (lihat Laporan Penggantian).');
            }
        } elseif ($aksi === 'selesai') {
            panggil('CALL sp_periksa_pengembalian(?, ?, ?, ?, ?)', 'isisi', [$id, 'baik', user()['id_user'], '', 0]);
            catat('Periksa pengembalian ' . ringkas_kembali($id) . ': baik');
            pesan('success', 'Pengembalian selesai, alat baik dan stok bertambah.');
        } elseif ($aksi === 'belum_kembali') {
            global $db;
            $db->begin_transaction();
            $r = ambil_satu('SELECT id_peminjaman, kondisi FROM pengembalian WHERE id_pengembalian = ? FOR UPDATE', 'i', [$id]);
            if (!$r || $r['kondisi'] !== 'belum_diperiksa') {
                $db->rollback();
                pesan('danger', 'Pengembalian ini sudah diperiksa atau tidak ditemukan.');
            } else {
                $ringkas = ringkas_pinjam($r['id_peminjaman']);
                $pemilik = ambil_satu('SELECT id_user FROM peminjaman WHERE id_peminjaman = ?', 'i', [$r['id_peminjaman']]);
                jalankan('DELETE FROM pengembalian WHERE id_pengembalian = ?', 'i', [$id]);
                jalankan("UPDATE peminjaman SET status = 'dipinjam' WHERE id_peminjaman = ?", 'i', [$r['id_peminjaman']]);
                jalankan("INSERT INTO notifikasi (id_user, jenis, pesan) VALUES (?, 'info', ?)", 'is',
                    [$pemilik['id_user'], mb_substr('Pengembalian ' . $ringkas . ' belum diterima petugas. Status kembali menjadi dipinjam; serahkan alat ke petugas lab.', 0, 255)]);
                $db->commit();
                catat('Membatalkan pengembalian ' . $ringkas . ' (alat belum dikembalikan)');
                pesan('warning', 'Pengembalian dibatalkan. Status kembali menjadi dipinjam.');
            }
        } elseif ($aksi === 'lunas') {
            tandai_denda_lunas($id);
        }
    } catch (mysqli_sql_exception $ex) {
        pesan('danger', pesan_error($ex));
    }
    pindah('/petugas/pantau.php');
}

$aktif = ambil_semua("SELECT p.*, u.nama AS peminjam, a.nama_alat
    FROM peminjaman p JOIN users u ON u.id_user = p.id_user JOIN alat a ON a.id_alat = p.id_alat
    WHERE p.status = 'dipinjam' ORDER BY p.tanggal_jatuh_tempo LIMIT 100");
[$daftar, $t] = daftar_pengembalian();
$judul = 'Pantau Pengembalian';
include __DIR__ . '/../includes/header.php';
?>
<div class="mb-4">
  <h2 class="text-dark h6 fw-bold mb-3">Sedang Dipinjam</h2>
  <?php tabel_buka(['No', 'Alat', 'Peminjam', 'Jumlah', 'Jatuh Tempo', 'Status', 'Denda Berjalan', 'Bukti']); ?>
    <?php foreach ($aktif as $i => $d): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td class="text-start"><?= e($d['nama_alat']) ?></td>
        <td class="text-start"><?= e($d['peminjam']) ?></td>
        <td><?= (int)$d['jumlah'] ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_jatuh_tempo']) ?></td>
        <td><?= badge_status($d['status'], $d['tanggal_jatuh_tempo']) ?></td>
        <td class="text-nowrap"><?= rp(denda_berjalan($d['tanggal_jatuh_tempo'], $d['jumlah'])) ?></td>
        <td><a href="<?= BASE ?>/bukti.php?id=<?= $d['id_peminjaman'] ?>" class="btn btn-secondary btn-sm d-inline-flex align-items-center gap-1"><?= ikon('cetak', 16) ?>Cetak</a></td>
      </tr>
    <?php endforeach; ?>
  <?php tabel_tutup($aktif, 'Tidak ada alat yang sedang dipinjam.'); ?>
</div>

<div>
  <h2 class="text-dark h6 fw-bold mb-3">Pengembalian Masuk</h2>
  <?php bar_tabel($t, 'Cari alat, peminjam, kondisi'); ?>
  <?php tabel_buka(['No', 'alat' => 'Alat', 'peminjam' => 'Peminjam', 'kembali' => 'Tgl Kembali', 'denda' => 'Denda', 'Status', 'Tindakan'], $t); ?>
    <?php foreach ($daftar as $i => $d): ?>
      <tr>
        <td><?= $t['offset'] + $i + 1 ?></td>
        <td class="text-start"><?= e($d['nama_alat']) ?> <span class="text-secondary">x<?= (int)$d['jumlah'] ?></span></td>
        <td class="text-start"><?= e($d['peminjam']) ?></td>
        <td class="text-nowrap"><?= tgl($d['tanggal_kembali']) ?></td>
        <td class="text-nowrap"><?= $d['total_denda'] > 0 ? rp($d['total_denda']) : '-' ?>
          <?php if ($d['total_denda'] > 0 && !$d['denda_dibayar']): ?><form method="post" class="d-inline" onsubmit="return confirm('Denda sudah dibayar?')"><input type="hidden" name="aksi" value="lunas"><input type="hidden" name="id" value="<?= $d['id_pengembalian'] ?>"><button class="btn btn-sm btn-outline-success py-0 mt-1">Tandai lunas</button></form><?php endif; ?></td>
        <td><?= status_kembali($d) ?><?= $d['catatan'] ? '<div class="small text-secondary">' . e($d['catatan']) . '</div>' : '' ?></td>
        <td>
          <?php if ($d['kondisi'] === 'belum_diperiksa'): ?>
            <div class="d-flex flex-nowrap justify-content-center align-items-center gap-1">
              <form method="post" class="m-0" onsubmit="return confirm('Alat sudah diterima dalam kondisi baik?')"><input type="hidden" name="aksi" value="selesai"><input type="hidden" name="id" value="<?= $d['id_pengembalian'] ?>">
                <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-1"><?= ikon('cek', 14) ?>Selesai</button></form>
              <form method="post" class="m-0" onsubmit="return confirm('Alat belum diterima? Status akan kembali menjadi dipinjam.')"><input type="hidden" name="aksi" value="belum_kembali"><input type="hidden" name="id" value="<?= $d['id_pengembalian'] ?>">
                <button class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"><?= ikon('jam', 14) ?>Belum dikembalikan</button></form>
            </div>
            <details class="mt-2">
              <summary class="link-primary" style="cursor:pointer">Rusak / hilang</summary>
              <form method="post" class="input-group input-group-sm flex-nowrap mt-2"><input type="hidden" name="aksi" value="periksa"><input type="hidden" name="id" value="<?= $d['id_pengembalian'] ?>">
                <select name="kondisi" class="form-select form-select-sm" required>
                  <option value="">Kondisi</option><option value="baik">Baik</option><option value="rusak">Rusak</option><option value="hilang">Hilang</option>
                </select>
                <input name="jumlah_masalah" type="number" placeholder="Jml" min="1" max="<?= (int)$d['jumlah'] ?>" value="<?= (int)$d['jumlah'] ?>" class="form-control form-control-sm" style="max-width:70px" title="Jumlah unit rusak/hilang (diabaikan jika kondisi baik)">
                <input name="catatan" class="form-control form-control-sm" placeholder="Catatan (wajib jika rusak/hilang)" maxlength="255">
                <button class="btn btn-primary btn-sm">Simpan</button>
              </form>
            </details>
          <?php elseif ($d['status_ganti'] === 'belum'): ?>
            <a href="<?= BASE ?>/petugas/penggantian.php?status=belum" class="btn btn-sm btn-success">Proses penggantian</a>
          <?php else: ?><span class="text-secondary">Selesai</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php tabel_tutup($daftar, $t['q'] !== '' ? 'Tidak ada pengembalian yang cocok dengan pencarian.' : 'Belum ada pengembalian.'); ?>
  <?= paginasi($t['total'], $t['per'], $t['hal']) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
