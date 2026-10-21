<?php
require_once __DIR__ . '/config/fungsi.php';
wajib_login();
$u = user();
$id = (int)($_GET['id'] ?? 0);

$d = ambil_satu('SELECT p.*, u.nama, u.jenis, u.nomor_induk, u.kelas, a.nama_alat, a.kode_alat, a.lokasi, k.nama_kategori,
        t.nama AS petugas, r.tanggal_kembali, r.total_denda, r.kondisi
    FROM peminjaman p
    JOIN users u ON u.id_user = p.id_user
    JOIN alat a ON a.id_alat = p.id_alat
    JOIN kategori k ON k.id_kategori = a.id_kategori
    LEFT JOIN users t ON t.id_user = p.id_petugas
    LEFT JOIN pengembalian r ON r.id_peminjaman = p.id_peminjaman
    WHERE p.id_peminjaman = ?', 'i', [$id]);

if (!$d || ($u['role'] === 'peminjam' && (int)$d['id_user'] !== (int)$u['id_user'])) {
    pesan('danger', 'Data peminjaman tidak ditemukan.');
    pindah('/index.php');
}
if ($d['status'] === 'ditolak') {
    pesan('warning', 'Pengajuan yang ditolak tidak memiliki bukti peminjaman.');
    pindah('/index.php');
}

$baris = [
    'Kode peminjaman' => kode_pinjam($d['id_peminjaman']),
    'Nama peminjam'   => $d['nama'] . ' (' . ($d['jenis'] === 'guru' ? 'Guru' : 'Siswa' . ($d['kelas'] ? ' ' . $d['kelas'] : '')) . ')',
    ($d['jenis'] === 'guru' ? 'NIP' : 'NIS') => $d['nomor_induk'] ?: '-',
    'Alat'            => $d['nama_alat'] . ' (' . $d['kode_alat'] . ') - ' . $d['nama_kategori'],
    'Lab'             => $d['lokasi'] ?: '-',
    'Jumlah'          => $d['jumlah'] . ' unit',
    'Tanggal pinjam'  => tgl($d['tanggal_pinjam']),
    'Jatuh tempo'     => tgl($d['tanggal_jatuh_tempo']),
    'Keperluan'       => $d['keperluan'] ?: '-',
    'Status'          => ucfirst($d['status']),
];
if ($d['status'] === 'dikembalikan') {
    $baris['Tanggal kembali'] = tgl($d['tanggal_kembali']);
    $baris['Kondisi alat']    = ucfirst(str_replace('_', ' ', $d['kondisi']));
    $baris['Denda']           = rp($d['total_denda']);
}

$judul = $d['status'] === 'menunggu' ? 'Bukti Pengajuan Peminjaman' : 'Bukti Peminjaman Alat';
include __DIR__ . '/includes/header.php';
?>
<div class="d-print-none mb-3">
  <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()"><?= ikon('cetak', 16) ?>Cetak Bukti</button>
  <a href="<?= BASE ?>/index.php" class="btn btn-secondary btn-sm">Kembali</a>
</div>

<div class="card col-lg-8">
  <div class="card-body p-4">
    <div class="text-center border-bottom pb-3 mb-3">
      <div class="fw-bold text-dark fs-5">BUKTI PEMINJAMAN ALAT LABORATORIUM</div>
      <div class="text-secondary">Lab Komputer dan Lab Informatika</div>
    </div>

    <dl class="row mb-4">
      <?php foreach ($baris as $label => $isi): ?>
        <dt class="col-sm-4 text-secondary fw-normal d-flex justify-content-between pe-3"><span><?= e($label) ?></span><span>:</span></dt>
        <dd class="col-sm-8"><?= e($isi) ?></dd>
      <?php endforeach; ?>
    </dl>

    <div class="small text-secondary mb-4">Alat wajib dikembalikan paling lambat pada tanggal jatuh tempo. Keterlambatan dikenai denda <?= rp(TARIF_DENDA) ?> per hari untuk setiap alat. Alat yang rusak atau hilang wajib diganti.</div>

    <div class="row text-center">
      <div class="col-6">
        <div>Peminjam</div>
        <div class="py-5"></div>
        <div class="border-top pt-1 mx-3"><?= e($d['nama']) ?></div>
      </div>
      <div class="col-6">
        <div>Petugas Lab</div>
        <div class="py-5"></div>
        <div class="border-top pt-1 mx-3"><?= e($d['petugas'] ?: '........................') ?></div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
