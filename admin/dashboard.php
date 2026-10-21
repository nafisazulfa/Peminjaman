<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);

$judul = 'Dashboard';
$awalBulan = date('Y-m-01');
$akhirBulan = date('Y-m-t');

$staf = ambil_satu("SELECT COUNT(*) AS total, COALESCE(SUM(role = 'admin'), 0) AS admin, COALESCE(SUM(role = 'petugas'), 0) AS petugas FROM users WHERE role IN ('admin', 'petugas')");
$totalPetugas = (int)$staf['total'];
$totalAdmin = (int)$staf['admin'];
$totalKasir = (int)$staf['petugas'];

$totalPengguna = hitung("SELECT COUNT(*) FROM users WHERE role = 'peminjam'");

$alatRingkas = ambil_satu('SELECT COUNT(*) AS jenis, COALESCE(SUM(stok_total), 0) AS unit FROM alat');
$totalJenisAlat = (int)$alatRingkas['jenis'];
$totalUnitAlat = (int)$alatRingkas['unit'];

$totalKategori = hitung('SELECT COUNT(*) FROM kategori');

$belumKembali = hitung("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'");
$totalTransaksi = hitung('SELECT COUNT(*) FROM peminjaman');
$totalTelat = hitung("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam' AND tanggal_jatuh_tempo < CURDATE()");

$kartu = [
    ['Total Petugas', $totalPetugas, $totalAdmin . ' admin, ' . $totalKasir . ' petugas', 'user', 'primary'],
    ['Total Pengguna', $totalPengguna, 'anggota peminjam terdaftar', 'user', 'success'],
    ['Total Kategori', $totalKategori, 'kategori alat', 'tag', 'primary'],
    ['Total Alat', $totalUnitAlat, $totalJenisAlat . ' jenis alat', 'box', 'info'],
    ['Belum Dikembalikan', $belumKembali, 'peminjaman sedang berjalan', 'jam', 'warning'],
    ['Total Transaksi', $totalTransaksi, 'seluruh peminjaman', 'kalender', 'secondary'],
    ['Telat Dikembalikan', $totalTelat, 'lewat jatuh tempo', 'telat', 'danger']
];

$t = tabel_param(['pinjam' => 'p.tanggal_pinjam', 'id' => 'p.id_peminjaman', 'peminjam' => 'u.nama', 'kelas' => 'u.kelas', 'alat' => 'a.nama_alat', 'jumlah' => 'p.jumlah', 'tempo' => 'p.tanggal_jatuh_tempo', 'status' => 'p.status'], 'DESC');
$daftarPinjam = tabel_data(
    $t,
    'FROM peminjaman p
        JOIN users u ON u.id_user = p.id_user
        JOIN alat a ON a.id_alat = p.id_alat
        JOIN kategori k ON k.id_kategori = a.id_kategori
        LEFT JOIN users pt ON pt.id_user = p.id_petugas
        LEFT JOIN pengembalian r ON r.id_peminjaman = p.id_peminjaman',
    'p.id_peminjaman, p.jumlah, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.keperluan, p.status, p.alasan_tolak,
        u.nama, u.jenis, u.kelas, u.nomor_induk, a.nama_alat, k.nama_kategori, pt.nama AS nama_petugas, r.tanggal_kembali, r.total_denda',
    ['u.nama', 'a.nama_alat'],
    ['p.tanggal_pinjam BETWEEN ? AND ?', 'ss', [$awalBulan, $akhirBulan]]
);
$tabel_jangkar = '#peminjaman-bulan-ini';

$namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$tanggalSekarang = $namaHari[(int)date('w')] . ', ' . date('j') . ' ' . $namaBulan[(int)date('n') - 1] . ' ' . date('Y');
$jamSekarang = date('H:i:s');

$skrip = <<<'JS'
<script>
function perbaruiWaktu() {
    var sekarang = new Date();
    var zona = { timeZone: 'Asia/Jakarta' };
    document.getElementById('jam-hidup').textContent = sekarang.toLocaleTimeString('id-ID', Object.assign({ hour12: false }, zona)).replace(/\./g, ':');
    document.getElementById('tanggal-hidup').textContent = sekarang.toLocaleDateString('id-ID', Object.assign({ weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }, zona));
}
perbaruiWaktu();
setInterval(perbaruiWaktu, 1000);
</script>
JS;

include __DIR__ . '/../includes/header_admin.php';
?>

<div class="row g-3 mb-4">
  <?php foreach ($kartu as [$label, $nilai, $keterangan, $ikonKartu, $warna]) kartu($label, (int)$nilai, '', 'col-6 col-lg-4 col-xl-3', '', $warna, $ikonKartu); ?>

  <div class="col-6 col-lg-4 col-xl-3">
    <div class="card kartu-stat h-100">
      <div class="card-body px-3 py-2 d-flex align-items-center gap-3">
        <div class="ikon-tile bg-primary-subtle text-primary-emphasis"><?= ikon('jam', 20) ?></div>
        <div>
          <div class="text-secondary small">Waktu Sekarang</div>
          <div class="fs-5 fw-bold lh-1 my-1" id="jam-hidup"><?= e($jamSekarang) ?></div>
          <div class="small text-secondary" id="tanggal-hidup"><?= e($tanggalSekarang) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3" id="peminjaman-bulan-ini">
  <h2 class="h6 fw-bold mb-0">Peminjaman Bulan Ini</h2>
  <a href="<?= BASE ?>/admin/peminjaman.php" class="link-primary">Lihat semua</a>
</div>
<?php tabel_buka(['No', 'id' => 'Kode', 'peminjam' => 'Peminjam', 'kelas' => 'Kelas', 'alat' => 'Alat', 'jumlah' => 'Jumlah', 'pinjam' => 'Tgl Pinjam', 'tempo' => 'Jatuh Tempo', 'status' => 'Status', 'Aksi'], $t); ?>
  <?php foreach ($daftarPinjam as $i => $d): ?>
    <?php
    $jenisPeminjam = $d['jenis'] === 'guru' ? 'Guru' : ($d['kelas'] ?: 'Siswa');
    ?>
    <tr>
      <td><?= $t['offset'] + $i + 1 ?></td>
      <td class="text-nowrap"><?= kode_pinjam($d['id_peminjaman']) ?></td>
      <td class="text-start"><?= e($d['nama']) ?></td>
      <td><?= e($jenisPeminjam) ?></td>
      <td class="text-start"><?= e($d['nama_alat']) ?></td>
      <td class="text-center"><?= (int)$d['jumlah'] ?></td>
      <td class="text-nowrap"><?= tgl($d['tanggal_pinjam']) ?></td>
      <td class="text-nowrap"><?= tgl($d['tanggal_jatuh_tempo']) ?></td>
      <td><?= badge_status($d['status'], $d['tanggal_jatuh_tempo']) ?></td>
      <td class="text-center">
        <a href="<?= BASE ?>/admin/peminjaman_detail.php?id=<?= (int)$d['id_peminjaman'] ?>" class="btn btn-info btn-sm d-inline-flex align-items-center gap-1"><?= ikon('mata', 14) ?>Detail</a>
      </td>
    </tr>
  <?php endforeach; ?>
<?php tabel_tutup($daftarPinjam, 'Belum ada peminjaman bulan ini.'); ?>
<?= paginasi($t['total'], $t['per'], $t['hal'], 'hal', '#peminjaman-bulan-ini') ?>

<?php include __DIR__ . '/../includes/footer_admin.php'; ?>
