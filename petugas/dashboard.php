<?php
require_once __DIR__ . '/../config/fungsi.php';
require_once __DIR__ . '/../includes/dashboard_fungsi.php';
wajib_login(['petugas']);
$u = user();
$judul = 'Dashboard';
include __DIR__ . '/../includes/header.php';

peringatan_tugas([
    [hitung("SELECT COUNT(*) FROM pengembalian WHERE kondisi = 'belum_diperiksa'"), 'pengembalian belum diperiksa', '/petugas/pantau.php'],
    [hitung("SELECT COUNT(*) FROM pengembalian WHERE status_ganti = 'belum'"), 'alat rusak atau hilang belum diganti', '/petugas/pantau.php'],
]);

echo '<div class="row g-3">';
kartu('Menunggu persetujuan', hitung("SELECT COUNT(*) FROM peminjaman WHERE status = 'menunggu'"), 'perlu diproses', 'col-6 col-xl-3', '', 'warning', 'cek');
kartu('Sedang dipinjam', hitung("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'"), 'belum kembali', 'col-6 col-xl-3', '', 'primary', 'box');
kartu('Jatuh tempo hari ini', hitung("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam' AND tanggal_jatuh_tempo = CURDATE()"), 'harus kembali hari ini', 'col-6 col-xl-3', '', 'info', 'kalender');
kartu('Terlambat', hitung("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam' AND tanggal_jatuh_tempo < CURDATE()"), 'lewat jatuh tempo', 'col-6 col-xl-3', '', 'danger', 'telat');
echo '</div>';

$hariIni = ambil_semua($dasarPinjam . "WHERE p.status = 'dipinjam' AND p.tanggal_jatuh_tempo = CURDATE() ORDER BY u.nama LIMIT 5");
$telat = ambil_semua($dasarPinjam . "WHERE p.status = 'dipinjam' AND p.tanggal_jatuh_tempo < CURDATE() ORDER BY p.tanggal_jatuh_tempo LIMIT 5");
$ganti = ambil_semua("SELECT r.kondisi, r.tanggal_kembali, p.jumlah, u.nama, CASE WHEN u.jenis = 'guru' THEN 'Guru' ELSE u.kelas END AS kelas, a.nama_alat, a.lokasi
    FROM pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman
    JOIN users u ON u.id_user = p.id_user JOIN alat a ON a.id_alat = p.id_alat
    WHERE r.status_ganti = 'belum' ORDER BY r.id_pengembalian LIMIT 5");

tab_buka(['tab-hariini' => 'Wajib Kembali Hari Ini', 'tab-telat' => 'Terlambat', 'tab-ganti' => 'Belum Diganti']);

tab_isi_buka('tab-hariini', true);
if ($hariIni) {
    tabel_buka(['Peminjam', 'Kelas', 'Alat', 'Jml']);
    foreach ($hariIni as $d) {
        echo '<tr><td class="text-start">' . e($d['nama']) . '</td><td>' . e($d['kelas'] ?: '-') . '</td><td class="text-start">' . e($d['nama_alat']) . ' <span class="text-secondary">' . e($d['lokasi']) . '</span></td><td>' . (int)$d['jumlah'] . '</td></tr>';
    }
    tabel_tutup();
} else {
    panel_kosong('Tidak ada alat yang jatuh tempo hari ini.');
}
tab_isi_tutup('/petugas/pantau.php', 'Pantau pengembalian');

tab_isi_buka('tab-telat');
if ($telat) {
    tabel_buka(['Peminjam', 'Kelas', 'Alat', 'Telat', 'Denda berjalan']);
    foreach ($telat as $d) {
        echo '<tr><td class="text-start">' . e($d['nama']) . '</td><td>' . e($d['kelas'] ?: '-') . '</td><td class="text-start">' . e($d['nama_alat']) . ' x' . (int)$d['jumlah'] . '</td><td>'
           . hari_telat($d['tanggal_jatuh_tempo']) . ' hari</td><td class="text-danger">' . rp(denda_berjalan($d['tanggal_jatuh_tempo'], $d['jumlah'])) . '</td></tr>';
    }
    tabel_tutup();
} else {
    panel_kosong('Tidak ada keterlambatan.');
}
tab_isi_tutup('/petugas/pantau.php', 'Pantau pengembalian');

tab_isi_buka('tab-ganti');
if ($ganti) {
    tabel_buka(['Peminjam', 'Kelas', 'Alat', 'Jml', 'Kondisi', 'Tanggal kembali']);
    foreach ($ganti as $d) {
        echo '<tr><td class="text-start">' . e($d['nama']) . '</td><td>' . e($d['kelas'] ?: '-') . '</td><td class="text-start">' . e($d['nama_alat']) . ' <span class="text-secondary">' . e($d['lokasi']) . '</span></td><td>'
           . (int)$d['jumlah'] . '</td><td>' . badge_kondisi($d['kondisi']) . '</td><td>' . tgl($d['tanggal_kembali']) . '</td></tr>';
    }
    tabel_tutup();
} else {
    panel_kosong('Tidak ada alat yang perlu diganti.');
}
tab_isi_tutup('/petugas/pantau.php', 'Buka pantau pengembalian');

tab_tutup();

include __DIR__ . '/../includes/footer.php';
