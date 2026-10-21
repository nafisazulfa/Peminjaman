<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['peminjam']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_user = (int)user()['id_user'];
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'baca') {
        jalankan('UPDATE notifikasi SET dibaca = 1 WHERE id_notif = ? AND id_user = ?', 'ii', [(int)($_POST['id'] ?? 0), $id_user]);
    } elseif ($aksi === 'semua') {
        jalankan('UPDATE notifikasi SET dibaca = 1 WHERE id_user = ?', 'i', [$id_user]);
    }
}
$boleh = ['dashboard.php', 'pengembalian.php', 'ajukan.php'];
$kembali = is_string($_POST['kembali'] ?? null) && in_array($_POST['kembali'], $boleh, true) ? $_POST['kembali'] : 'dashboard.php';
pindah('/peminjam/' . $kembali);
