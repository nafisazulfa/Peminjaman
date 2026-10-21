<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['peminjam']);
$u = user();
$judul = 'Dashboard';
include __DIR__ . '/../includes/header.php';

    $id = $u['id_user'];
    $menunggu  = hitung("SELECT COUNT(*) FROM peminjaman WHERE id_user = ? AND status = 'menunggu'", 'i', [$id]);
    $dipinjam  = hitung("SELECT COUNT(*) FROM peminjaman WHERE id_user = ? AND status = 'dipinjam'", 'i', [$id]);
    $unit      = hitung("SELECT COALESCE(SUM(jumlah),0) FROM peminjaman WHERE id_user = ? AND status = 'dipinjam'", 'i', [$id]);
    $telat     = hitung("SELECT COUNT(*) FROM peminjaman WHERE id_user = ? AND status = 'dipinjam' AND tanggal_jatuh_tempo < CURDATE()", 'i', [$id]);
    $total_pinjam = hitung('SELECT COUNT(*) FROM peminjaman WHERE id_user = ?', 'i', [$id]);
    $selesai   = hitung("SELECT COUNT(*) FROM peminjaman WHERE id_user = ? AND status = 'dikembalikan'", 'i', [$id]);
    $perluGanti = hitung("SELECT COUNT(*) FROM pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman WHERE p.id_user = ? AND r.status_ganti = 'belum'", 'i', [$id]);
    $dendaTercatat = hitung("SELECT COALESCE(SUM(r.total_denda),0) FROM pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman WHERE p.id_user = ?", 'i', [$id]);
    $aktif = ambil_semua("SELECT jumlah, tanggal_jatuh_tempo FROM peminjaman WHERE id_user = ? AND status = 'dipinjam' ORDER BY tanggal_jatuh_tempo", 'i', [$id]);
    $dendaBerjalan = 0;
    foreach ($aktif as $x) $dendaBerjalan += denda_berjalan($x['tanggal_jatuh_tempo'], $x['jumlah']);

    $blokir = blokir_peminjaman($id);

    if ($blokir) {
        echo '<div class="alert alert-danger py-2 px-3 small"><b>Anda belum bisa meminjam alat lagi:</b>';
        foreach ($blokir as $m) echo '<div>&bull; ' . e($m) . '</div>';
        echo '</div>';
    }
    if ($dendaTercatat > 0) echo '<div class="alert alert-secondary py-2 px-3 small">Total denda keterlambatan yang sudah tercatat: ' . rp($dendaTercatat) . '.</div>';
    if ($perluGanti > 0) echo '<div class="alert alert-warning py-2 px-3 small">Ada ' . $perluGanti . ' alat rusak/hilang yang harus diganti. Hubungi petugas lab.</div>';

    echo '<div class="row g-3">';
    kartu('Total Peminjaman', $total_pinjam, 'seluruh pengajuan Anda', 'col-6 col-xl-3', '', 'secondary', 'kalender');
    kartu('Menunggu Persetujuan', $menunggu, 'belum diproses petugas', 'col-6 col-xl-3', '', 'warning', 'jam');
    kartu('Sedang Dipinjam', $dipinjam, $unit . ' unit alat belum kembali', 'col-6 col-xl-3', '', 'primary', 'box');
    kartu('Selesai', $selesai, 'sudah dikembalikan', 'col-6 col-xl-3', '', 'success', 'cek');
    echo '</div>';

    $aturan = [
        ['box',      'Beberapa Alat', 'Satu pengajuan dapat memuat lebih dari satu alat sekaligus.'],
        ['kalender', 'Durasi Peminjaman', 'Maksimal ' . MAKS_HARI . ' hari sejak tanggal pinjam.'],
        ['uang',     'Denda Keterlambatan', rp(TARIF_DENDA) . ' per hari untuk setiap alat yang terlambat.'],
        ['ganti',    'Rusak atau Hilang', 'Alat yang rusak atau hilang wajib diganti oleh peminjam.'],
        ['jam',      'Peminjaman Berikutnya', 'Peminjaman baru tidak dapat diajukan sebelum peminjaman sebelumnya dikembalikan.'],
        ['cek',      'Selesaikan Tanggungan', 'Kembalikan alat, ganti yang rusak, dan bayar denda sebelum meminjam lagi.'],
    ];
    echo '<div class="aturan-bagian"><h2 class="aturan-judul">Aturan Peminjaman</h2><span class="aturan-garis"></span>'
       . '<div class="row g-3 text-start">';
    foreach ($aturan as [$ik, $judulAturan, $isi]) {
        echo '<div class="col-12 col-md-6 col-xl-4"><div class="aturan-kotak h-100">'
           . '<span class="aturan-ikon">' . ikon($ik, 22) . '</span>'
           . '<div><div class="aturan-nama">' . e($judulAturan) . '</div>'
           . '<div class="aturan-isi">' . e($isi) . '</div></div></div></div>';
    }
    echo '</div></div>';

    $cari = mb_substr(trim($_GET['cari'] ?? ''), 0, 50);
    $kat  = (int)($_GET['kat'] ?? 0);
    $lab  = $_GET['lab'] ?? '';
    if (!in_array($lab, DAFTAR_LAB, true)) $lab = '';
    $hal  = max(1, (int)($_GET['hal'] ?? 1));
    $per  = 8;
    $halR = max(1, (int)($_GET['hal_r'] ?? 1));
    $perR = 8;
    $kategori = ambil_semua('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori');
    $like = '%' . $cari . '%';
    $where = 'WHERE (a.nama_alat LIKE ? OR a.kode_alat LIKE ?)';
    $types = 'ss';
    $par = [$like, $like];
    if ($kat > 0) { $where .= ' AND a.id_kategori = ?'; $types .= 'i'; $par[] = $kat; }
    if ($lab !== '') { $where .= ' AND a.lokasi = ?'; $types .= 's'; $par[] = $lab; }
    $total  = hitung("SELECT COUNT(*) FROM alat a $where", $types, $par);
    $daftar = ambil_semua("SELECT a.*, k.nama_kategori FROM alat a JOIN kategori k ON k.id_kategori = a.id_kategori
        $where ORDER BY a.nama_alat LIMIT ? OFFSET ?", $types . 'ii', array_merge($par, [$per, ($hal - 1) * $per]));
    ?>
    <h2 class="text-dark h6 fw-bold mt-4 mb-1" id="daftar-alat"><span class="garis-judul"></span>Daftar Alat</h2>
    <form method="get" action="#daftar-alat" class="row g-2 mb-3">
      <div class="col-md-4"><input name="cari" class="form-control form-control-sm" placeholder="Cari nama atau kode alat" maxlength="50" value="<?= e($cari) ?>"></div>
      <div class="col-md-3">
        <select name="lab" class="form-select form-select-sm">
          <option value="">Semua lab</option>
          <?php foreach (DAFTAR_LAB as $l): ?><option value="<?= e($l) ?>" <?= $lab === $l ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="kat" class="form-select form-select-sm">
          <option value="0">Semua kategori</option>
          <?= opsi($kategori, 'id_kategori', fn($r) => $r['nama_kategori'], $kat) ?>
        </select>
      </div>
      <div class="col-md-2"><button class="btn btn-primary w-100 btn-sm">Cari</button></div>
    </form>
    <div class="row g-3 mb-3">
      <?php foreach ($daftar as $d): ?>
        <div class="col-6 col-md-4 col-xl-3">
          <div class="card kartu-alat h-100">
            <?= $d['gambar'] ? foto($d['gambar']) : '' ?>
            <div class="card-body">
              <h3 class="text-dark small fw-bold mb-1"><?= e($d['nama_alat']) ?></h3>
              <div class="small text-secondary mb-2"><?= e($d['nama_kategori']) ?> | <?= e($d['kode_alat']) ?></div>
              <div class="mb-1"><?= $d['stok_tersedia'] > 0 ? badge('Tersedia: ' . (int)$d['stok_tersedia'], 'success') : badge('Habis', 'secondary') ?></div>
              <div class="small text-secondary mb-2"><?= e($d['lokasi'] ?: '-') ?></div>
              <?php $pst = $d['stok_total'] > 0 ? (int)round($d['stok_tersedia'] / $d['stok_total'] * 100) : 0; ?>
              <div class="progress progress-tipis" role="progressbar"><div class="progress-bar persen-<?= 5 * (int)round($pst / 5) ?> bg-<?= $pst >= 50 ? 'success' : ($pst >= 20 ? 'warning' : 'danger') ?>"></div></div>
              <div class="small text-secondary mt-1"><?= (int)$d['stok_tersedia'] ?> dari <?= (int)$d['stok_total'] ?> unit</div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-3">
              <?php if ($blokir): ?>
                <button class="btn btn-secondary btn-sm w-100" disabled>Selesaikan tanggungan</button>
              <?php elseif ($d['stok_tersedia'] > 0): ?>
                <a href="<?= BASE ?>/peminjam/ajukan.php?alat=<?= $d['id_alat'] ?>" class="btn btn-primary btn-sm w-100">Pinjam</a>
              <?php else: ?>
                <button class="btn btn-secondary btn-sm w-100" disabled>Tidak tersedia</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$daftar): ?><div class="col-12 text-center text-secondary py-4">Alat tidak ditemukan.</div><?php endif; ?>
    </div>
    <?= paginasi($total, $per, $hal, 'hal', '#daftar-alat') ?>

    <?php
    $totalR = $total_pinjam;
    $riwayat = ambil_semua('SELECT p.*, a.nama_alat, a.gambar, r.tanggal_kembali, r.total_denda, r.terlambat_hari, r.kondisi, r.status_ganti, r.jumlah_bermasalah, r.jumlah_diganti, r.denda_dibayar
        FROM peminjaman p
        JOIN alat a ON a.id_alat = p.id_alat
        LEFT JOIN pengembalian r ON r.id_peminjaman = p.id_peminjaman
        WHERE p.id_user = ? ORDER BY p.id_peminjaman DESC LIMIT ? OFFSET ?', 'iii', [$id, $perR, ($halR - 1) * $perR]);
    ?>
    <div class="d-flex justify-content-between align-items-end mt-4 mb-2" id="riwayat">
      <h2 class="text-dark h6 fw-bold mb-0"><span class="garis-judul"></span>Riwayat Peminjaman Saya</h2>
      <a href="<?= BASE ?>/peminjam/pengembalian.php" class="link-primary">Lihat semua</a>
    </div>
    <div>
    <?php tabel_buka(['No', 'Kode', 'Alat', 'Jumlah', 'Tgl Pinjam', 'Jatuh Tempo', 'Tgl Kembali', 'Status', 'Keterangan', 'Aksi']); ?>

          <?php foreach ($riwayat as $n => $d): ?>
            <tr>
              <td><?= ($halR - 1) * $perR + $n + 1 ?></td>
              <td class="text-nowrap"><?= kode_pinjam($d['id_peminjaman']) ?></td>
              <td class="text-start"><?= e($d['nama_alat']) ?></td>
              <td><?= (int)$d['jumlah'] ?></td>
              <td class="text-nowrap"><?= tgl($d['tanggal_pinjam']) ?></td>
              <td class="text-nowrap"><?= tgl($d['tanggal_jatuh_tempo']) ?></td>
              <td class="text-nowrap"><?= tgl($d['tanggal_kembali']) ?></td>
              <td><?= status_pinjam($d) ?></td>
              <td class="small text-start">
                <?php if ($d['status'] === 'menunggu'): ?>Menunggu persetujuan petugas.
                <?php elseif ($d['status'] === 'ditolak'): ?>Ditolak: <?= e($d['alasan_tolak'] ?: '-') ?>
                <?php elseif ($d['status'] === 'dipinjam'): $t = hari_telat($d['tanggal_jatuh_tempo']); ?>
                  <?= $t > 0 ? 'Terlambat ' . $t . ' hari, denda berjalan ' . rp(denda_berjalan($d['tanggal_jatuh_tempo'], $d['jumlah'])) . '.' : 'Kembalikan sebelum ' . tgl($d['tanggal_jatuh_tempo']) . '.' ?>
                <?php else: ?><?= $d['total_denda'] > 0 ? 'Denda ' . rp($d['total_denda']) : '-' ?>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex flex-nowrap justify-content-center align-items-center gap-1">
                  <?php if ($d['status'] === 'dipinjam'): ?>
                    <form method="post" action="<?= BASE ?>/peminjam/pengembalian.php" class="m-0" onsubmit="return confirm('Kembalikan alat ini sekarang?')">
                      <input type="hidden" name="id" value="<?= $d['id_peminjaman'] ?>">
                      <button class="btn btn-primary btn-sm py-0">Kembalikan</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($d['status'] === 'menunggu'): ?>
                    <form method="post" action="<?= BASE ?>/peminjam/pengembalian.php" class="m-0" onsubmit="return confirm('Batalkan pengajuan ini?')">
                      <input type="hidden" name="aksi" value="batal">
                      <input type="hidden" name="id" value="<?= $d['id_peminjaman'] ?>">
                      <button class="btn btn-danger btn-sm py-0">Batalkan</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($d['status'] !== 'ditolak'): ?>
                    <a href="<?= BASE ?>/bukti.php?id=<?= $d['id_peminjaman'] ?>" class="btn btn-secondary btn-sm py-0">Bukti</a>
                  <?php else: ?>-<?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php tabel_tutup($riwayat, 'Belum ada riwayat peminjaman.'); ?>
    </div>
    <?= paginasi($totalR, $perR, $halR, 'hal_r', '#riwayat') ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
