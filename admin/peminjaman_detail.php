<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);

$id = (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) ? (int)$_GET['id'] : 0;

$d = ambil_satu("SELECT p.*, u.nama, u.jenis, u.nomor_induk, u.kelas, u.no_hp,
        a.nama_alat, a.kode_alat, a.lokasi, a.stok_total, a.stok_tersedia, k.nama_kategori,
        pt.nama AS nama_petugas,
        r.id_pengembalian, r.tanggal_kembali, r.terlambat_hari, r.total_denda, r.kondisi, r.status_ganti,
        r.jumlah_bermasalah, r.jumlah_diganti, r.tanggal_ganti, r.denda_dibayar, r.catatan, pr.nama AS nama_pemeriksa
    FROM peminjaman p
    JOIN users u ON u.id_user = p.id_user
    JOIN alat a ON a.id_alat = p.id_alat
    JOIN kategori k ON k.id_kategori = a.id_kategori
    LEFT JOIN users pt ON pt.id_user = p.id_petugas
    LEFT JOIN pengembalian r ON r.id_peminjaman = p.id_peminjaman
    LEFT JOIN users pr ON pr.id_user = r.id_petugas
    WHERE p.id_peminjaman = ?", 'i', [$id]);

if (!$d) {
    pesan('danger', 'Data peminjaman tidak ditemukan.');
    pindah('/admin/peminjaman.php');
}

$kode = kode_pinjam($d['id_peminjaman']);
$guru = $d['jenis'] === 'guru';
$jenisPeminjam = $guru ? 'Guru' : 'Siswa' . ($d['kelas'] ? ' ' . $d['kelas'] : '');
$durasi = (int)round((strtotime($d['tanggal_jatuh_tempo']) - strtotime($d['tanggal_pinjam'])) / 86400);
$sudahKembali = $d['status'] === 'dikembalikan' && $d['id_pengembalian'];
$telatBerjalan = $d['status'] === 'dipinjam' ? hari_telat($d['tanggal_jatuh_tempo']) : 0;

// Alur peminjaman
$alur = [['Diajukan', tgl($d['tanggal_pinjam']), 'Oleh ' . $d['nama'], 'success']];
if ($d['status'] === 'menunggu') {
    $alur[] = ['Menunggu persetujuan petugas', '', '', 'warning'];
} elseif ($d['status'] === 'ditolak') {
    $alur[] = ['Ditolak', '', 'Oleh ' . ($d['nama_petugas'] ?: '-') . ($d['alasan_tolak'] ? ' - ' . $d['alasan_tolak'] : ''), 'danger'];
} else {
    $alur[] = ['Disetujui', '', 'Oleh ' . ($d['nama_petugas'] ?: '-'), 'success'];
    if ($sudahKembali) {
        $alur[] = ['Dikembalikan', tgl($d['tanggal_kembali']), $d['terlambat_hari'] > 0 ? 'Terlambat ' . (int)$d['terlambat_hari'] . ' hari' : 'Tepat waktu', $d['terlambat_hari'] > 0 ? 'warning' : 'success'];
        if ($d['kondisi'] === 'belum_diperiksa') {
            $alur[] = ['Menunggu pemeriksaan kondisi', '', '', 'secondary'];
        } else {
            $alur[] = ['Diperiksa: ' . ucfirst($d['kondisi']), '', 'Oleh ' . ($d['nama_pemeriksa'] ?: '-'), $d['kondisi'] === 'baik' ? 'success' : 'danger'];
            if ($d['status_ganti'] === 'belum') $alur[] = ['Menunggu penggantian alat', '', (int)$d['jumlah_diganti'] . ' dari ' . (int)$d['jumlah_bermasalah'] . ' unit sudah diganti', 'danger'];
            if ($d['status_ganti'] === 'sudah') $alur[] = ['Alat sudah diganti', tgl($d['tanggal_ganti']), (int)$d['jumlah_bermasalah'] . ' unit', 'success'];
        }
    } else {
        $alur[] = [$telatBerjalan > 0 ? 'Sedang dipinjam, terlambat ' . $telatBerjalan . ' hari' : 'Sedang dipinjam', 'Jatuh tempo ' . tgl($d['tanggal_jatuh_tempo']), '', $telatBerjalan > 0 ? 'danger' : 'primary'];
    }
}

function baris_detail($label, $isi) {
    echo '<dt class="col-sm-4 text-secondary fw-normal">' . e($label) . '</dt><dd class="col-sm-8 mb-2">' . $isi . '</dd>';
}
function judul_bagian($no, $teks) {
    echo '<div class="d-flex align-items-center gap-2 mb-3"><span class="detail-no">' . $no . '</span><h3 class="h6 fw-bold mb-0">' . e($teks) . '</h3></div>';
}

$judul = 'Detail Peminjaman';
include __DIR__ . '/../includes/header_admin.php';
?>

<div class="card">
  <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 px-4 py-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <h2 class="h5 fw-bold mb-0"><?= e($kode) ?></h2>
        <?= badge_status($d['status'], $d['tanggal_jatuh_tempo']) ?>
      </div>
      <div class="text-secondary mt-1">Diajukan <?= e(tgl($d['tanggal_pinjam'])) ?> oleh <?= e($d['nama']) ?></div>
    </div>
    <div class="d-flex gap-2 d-print-none">
      <?php if ($d['status'] !== 'ditolak'): ?><a href="<?= BASE ?>/bukti.php?id=<?= (int)$d['id_peminjaman'] ?>" class="btn btn-secondary btn-sm d-inline-flex align-items-center gap-1"><?= ikon('cetak', 16) ?>Bukti</a><?php endif; ?>
      <a href="<?= BASE ?>/admin/peminjaman.php" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
  </div>

  <div class="row g-0">
    <div class="col-lg-7 p-4">
      <?php judul_bagian(1, 'Identitas Peminjam'); ?>
      <dl class="row mb-4">
        <?php
        baris_detail('Nama', e($d['nama']));
        baris_detail('Kategori', e($jenisPeminjam));
        baris_detail($guru ? 'NIP' : 'NISN', e($d['nomor_induk'] ?: '-'));
        baris_detail('No HP', e($d['no_hp'] ?: '-'));
        ?>
      </dl>

      <?php judul_bagian(2, 'Data Peminjaman'); ?>
      <dl class="row mb-4">
        <?php
        baris_detail('Alat', e($d['nama_alat']) . ' <span class="text-secondary">(' . e($d['kode_alat']) . ')</span>');
        baris_detail('Kategori alat', e($d['nama_kategori']));
        baris_detail('Lokasi', e($d['lokasi'] ?: '-'));
        baris_detail('Jumlah', (int)$d['jumlah'] . ' unit');
        baris_detail('Tanggal pinjam', tgl($d['tanggal_pinjam']));
        baris_detail('Jatuh tempo', tgl($d['tanggal_jatuh_tempo']) . ' <span class="text-secondary">(' . $durasi . ' hari)</span>');
        baris_detail('Keperluan', nl2br(e($d['keperluan'] ?: '-')));
        ?>
      </dl>

      <?php judul_bagian(3, 'Pelayanan'); ?>
      <dl class="row mb-4">
        <?php
        baris_detail('Status', badge_status($d['status'], $d['tanggal_jatuh_tempo']));
        baris_detail($d['status'] === 'ditolak' ? 'Ditolak oleh' : 'Diproses oleh', e($d['nama_petugas'] ?: '-'));
        if ($d['status'] === 'ditolak') baris_detail('Alasan', e($d['alasan_tolak'] ?: '-'));
        if ($telatBerjalan > 0) {
            baris_detail('Terlambat', '<span class="text-danger">' . $telatBerjalan . ' hari</span>');
            baris_detail('Denda berjalan', '<span class="text-danger">' . rp(denda_berjalan($d['tanggal_jatuh_tempo'], $d['jumlah'])) . '</span> <span class="text-secondary">(' . rp(TARIF_DENDA) . ' / hari / unit)</span>');
        }
        ?>
      </dl>

      <?php judul_bagian(4, 'Pengembalian & Penyelesaian'); ?>
      <?php if ($sudahKembali): ?>
        <dl class="row mb-0">
          <?php
          baris_detail('Tanggal kembali', tgl($d['tanggal_kembali']));
          baris_detail('Ketepatan', (int)$d['terlambat_hari'] > 0 ? '<span class="text-danger">Terlambat ' . (int)$d['terlambat_hari'] . ' hari</span>' : 'Tepat waktu');
          baris_detail('Total denda', rp($d['total_denda']));
          if ((float)$d['total_denda'] > 0) {
              baris_detail('Pembayaran denda', $d['denda_dibayar'] ? '<span class="badge text-bg-success">Lunas</span> <span class="text-secondary">' . e(date('d-m-Y H:i', strtotime($d['denda_dibayar']))) . '</span>' : '<span class="badge text-bg-warning">Belum dibayar</span>');
          }
          baris_detail('Kondisi alat', badge_kondisi($d['kondisi']));
          baris_detail('Diperiksa oleh', e($d['nama_pemeriksa'] ?: '-'));
          if ((int)$d['jumlah_bermasalah'] > 0) {
              baris_detail('Unit rusak/hilang', (int)$d['jumlah_bermasalah'] . ' unit');
              baris_detail('Penggantian', badge_ganti($d['status_ganti']) . ' <span class="text-secondary">' . (int)$d['jumlah_diganti'] . ' dari ' . (int)$d['jumlah_bermasalah'] . ' unit' . ($d['tanggal_ganti'] ? ', selesai ' . tgl($d['tanggal_ganti']) : '') . '</span>');
          }
          baris_detail('Catatan', nl2br(e($d['catatan'] ?: '-')));
          ?>
        </dl>
      <?php else: ?>
        <div class="text-secondary"><?= $d['status'] === 'dipinjam' ? 'Alat belum dikembalikan.' : 'Belum ada data pengembalian.' ?></div>
      <?php endif; ?>
    </div>

    <div class="col-lg-5 p-4 bg-body-tertiary border-start rounded-bottom-end">
      <h3 class="h6 fw-bold mb-4">Alur Peminjaman</h3>
      <ul class="alur list-unstyled mb-0">
        <?php foreach ($alur as [$judulAlur, $waktuAlur, $ketAlur, $warnaAlur]): ?>
          <li class="alur-item">
            <span class="alur-titik bg-<?= $warnaAlur ?>"></span>
            <div class="fw-semibold"><?= e($judulAlur) ?></div>
            <?php if ($waktuAlur !== ''): ?><div class="text-secondary"><?= e($waktuAlur) ?></div><?php endif; ?>
            <?php if ($ketAlur !== ''): ?><div class="text-secondary"><?= e($ketAlur) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer_admin.php'; ?>
