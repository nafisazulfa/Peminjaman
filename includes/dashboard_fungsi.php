<?php
function panel_buka($judul, $ket = '', $kolom = 'col-lg-6') {
    echo '<div class="' . $kolom . '"><h2 class="text-dark h6 fw-bold mb-3">' . e($judul) . '</h2>';
}
function panel_tutup($url = '', $teks = '') {
    if ($url !== '') echo '<div class="text-end mt-2"><a href="' . BASE . $url . '" class="link-primary">Lihat semua</a></div>';
    echo '</div>';
}
function panel_kosong($teks) {
    echo '<div class="text-center text-secondary small py-4">' . e($teks) . '</div>';
}
$dasarPinjam = "SELECT p.id_peminjaman, p.jumlah, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status, u.nama, CASE WHEN u.jenis = 'guru' THEN 'Guru' ELSE u.kelas END AS kelas, a.nama_alat, a.lokasi
    FROM peminjaman p JOIN users u ON u.id_user = p.id_user JOIN alat a ON a.id_alat = p.id_alat ";

function tab_buka($tab) {
    echo '<div class="mt-4"><ul class="nav nav-tabs mb-3" role="tablist">';
    $i = 0;
    foreach ($tab as $id => $label) {
        echo '<li class="nav-item" role="presentation"><button type="button" class="nav-link' . ($i === 0 ? ' active' : '') . '" data-bs-toggle="tab" data-bs-target="#' . $id . '" role="tab">' . e($label) . '</button></li>';
        $i++;
    }
    echo '</ul><div class="tab-content">';
}
function tab_isi_buka($id, $aktif = false) {
    echo '<div class="tab-pane fade' . ($aktif ? ' show active' : '') . '" id="' . $id . '" role="tabpanel">';
}
function tab_isi_tutup($url = '', $teks = '') {
    if ($url !== '') echo '<div class="text-end mt-2"><a href="' . BASE . $url . '" class="link-primary">Lihat semua</a></div>';
    echo '</div>';
}
function tab_tutup() { echo '</div></div>'; }
function peringatan_tugas($daftar) {
    $isi = [];
    foreach ($daftar as [$jumlah, $teks, $url]) {
        if ($jumlah > 0) $isi[] = '<a href="' . BASE . $url . '" class="alert-link">' . (int)$jumlah . ' ' . e($teks) . '</a>';
    }
    if ($isi) echo '<div class="alert alert-warning py-2 px-3 small mb-3"><b>Perlu ditindaklanjuti:</b> ' . implode(' &middot; ', $isi) . '</div>';
}
