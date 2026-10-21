<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Jakarta');

$root_web = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT']));
$root_app = str_replace('\\', '/', dirname(__DIR__));
define('BASE', rtrim(str_ireplace($root_web, '', $root_app), '/'));
define('TARIF_DENDA', 10000);
define('MAKS_HARI', 3);
define('TEMA', 'bawaan');
define('DAFTAR_LAB', ['Lab Komputer', 'Lab Informatika']);
define('MAKS_AKTIF_SISWA', 3);
define('MAKS_AKTIF_GURU', 6);

set_exception_handler(function ($ex) {
    http_response_code(500);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Terjadi Kesalahan</title><link href="' . BASE . '/assets/css/bootstrap.min.css" rel="stylesheet"></head>'
       . '<body class="bg-light small"><div class="container py-5">'
       . '<div class="alert alert-danger"><div class="fw-bold mb-1">Terjadi kesalahan pada database.</div>'
       . 'Kemungkinan struktur database belum diperbarui. Buka <a href="' . BASE . '/cek_database.php" class="alert-link">halaman cek database</a> untuk melihat penyebabnya.'
       . '</div><div class="text-secondary">Detail teknis: ' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>'
       . '</div></body></html>';
});

$db = new mysqli('localhost', 'root', '', 'db_peminjaman_lab');
$db->set_charset('utf8mb4');

define('PASSWORD_MIN', 5);
define('PASSWORD_MAKS', 50);

function buat_hash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
// Mendukung hash lama (MD5) dan otomatis memperbarui ke hash baru saat login berhasil.
function cek_password($password, $hash, $idUser = 0) {
    $hash = (string)$hash;
    if ($hash === '') return false;
    if (strncmp($hash, '$', 1) === 0) {
        return password_verify($password, $hash);
    }
    if (!hash_equals($hash, md5($password))) return false;
    if ($idUser > 0) {
        jalankan('UPDATE users SET password = ? WHERE id_user = ?', 'si', [buat_hash($password), $idUser]);
    }
    return true;
}
function error_password($password) {
    if ($password === '') return 'Password wajib diisi.';
    if (mb_strlen($password) < PASSWORD_MIN || mb_strlen($password) > PASSWORD_MAKS) {
        return 'Password harus ' . PASSWORD_MIN . '-' . PASSWORD_MAKS . ' karakter.';
    }
    return null;
}
function batas_login_cek() {
    $g = $_SESSION['gagal_login'] ?? ['n' => 0, 'sampai' => 0];
    return $g['sampai'] > time() ? $g['sampai'] - time() : 0;
}
function batas_login_gagal() {
    $g = $_SESSION['gagal_login'] ?? ['n' => 0, 'sampai' => 0];
    $g['n']++;
    if ($g['n'] >= 5) $g = ['n' => 0, 'sampai' => time() + 60];
    $_SESSION['gagal_login'] = $g;
}

function jalankan($sql, $types = '', $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}
function ambil_semua($sql, $types = '', $params = []) {
    return jalankan($sql, $types, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function ambil_satu($sql, $types = '', $params = []) {
    $r = ambil_semua($sql, $types, $params);
    return $r[0] ?? null;
}
function hitung($sql, $types = '', $params = []) {
    $r = ambil_satu($sql, $types, $params);
    return $r ? (int)array_values($r)[0] : 0;
}
function pesan_error($ex) {
    if ($ex->getCode() == 1644) return $ex->getMessage();
    if ($ex->getCode() == 1451) return 'Data tidak bisa dihapus karena masih dipakai data lain.';
    if ($ex->getCode() == 1062) return 'Data duplikat, sudah ada di database.';
    return 'Terjadi kesalahan pada database.';
}

function css_tema() {
    $tema = in_array(TEMA, ['bawaan', 'hijau', 'indigo', 'abu'], true) ? TEMA : 'bawaan';
    return '<link href="' . BASE . '/assets/css/bootstrap.min.css" rel="stylesheet">' . "\n  "
         . '<link href="' . BASE . '/assets/css/tema/' . $tema . '.css" rel="stylesheet">' . "\n  "
         . '<link href="' . BASE . '/assets/css/app.css" rel="stylesheet">';
}
function e($t) { return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); }
function rp($n) { return 'Rp' . number_format((float)$n, 0, ',', '.'); }
function tgl($d) { return $d ? date('d-m-Y', strtotime($d)) : '-'; }
function nilai($k, $data = null, $d = '') { return e($_POST[$k] ?? ($data[$k] ?? $d)); }

function pindah($url) { header('Location: ' . BASE . $url); exit; }
function pesan($tipe, $teks) { $_SESSION['pesan'] = [$tipe, $teks]; }
function tampil_pesan() {
    if (!empty($_SESSION['pesan'])) {
        [$t, $s] = $_SESSION['pesan'];
        unset($_SESSION['pesan']);
        echo '<div class="alert alert-' . e($t) . ' py-2 px-3 small" role="alert">' . e($s) . '</div>';
    }
}
function tampil_error($errors) {
    if (!$errors) return;
    echo '<div class="alert alert-danger py-2 px-3 small" role="alert">';
    foreach ($errors as $x) echo '<div>' . e($x) . '</div>';
    echo '</div>';
}
function kode_pinjam($id) { return 'PJM-' . str_pad((int)$id, 4, '0', STR_PAD_LEFT); }
function badge($teks, $warna) { return '<span class="badge text-bg-' . $warna . '">' . e($teks) . '</span>'; }
function badge_status($s, $tempo = null) {
    $w = ['menunggu' => 'warning', 'dipinjam' => 'primary', 'ditolak' => 'danger', 'dikembalikan' => 'success'];
    if ($s === 'dipinjam' && $tempo && $tempo < date('Y-m-d')) return badge('Terlambat dikembalikan', 'danger');
    return badge(ucfirst($s), $w[$s] ?? 'secondary');
}
function badge_kondisi($k) {
    $w = ['belum_diperiksa' => 'secondary', 'baik' => 'success', 'rusak' => 'warning', 'hilang' => 'danger'];
    return badge(ucfirst(str_replace('_', ' ', $k)), $w[$k] ?? 'secondary');
}
function badge_ganti($g) {
    if ($g === 'belum') return badge('Belum diganti', 'danger');
    if ($g === 'sudah') return badge('Sudah diganti', 'success');
    return '<span class="text-secondary">-</span>';
}
function status_kembali($d) {
    $kondisi = $d['kondisi'] ?? null;
    if ($kondisi === null) return badge('Dikembalikan', 'success');
    if ($kondisi === 'belum_diperiksa') return badge('Perlu diperiksa', 'secondary');
    if (($d['status_ganti'] ?? '') === 'belum') return badge('Perlu diganti', 'danger');
    $denda = (float)($d['total_denda'] ?? $d['denda'] ?? 0);
    if ($denda > 0 && empty($d['denda_dibayar'])) return badge('Denda belum lunas', 'warning');
    return badge('Selesai', 'success');
}
function status_pinjam($d) {
    return $d['status'] === 'dikembalikan' ? status_kembali($d) : badge_status($d['status'], $d['tanggal_jatuh_tempo']);
}
function ikon($nama, $ukuran = 22) {
    $p = [
        'user'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'tag'      => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1"/>',
        'box'      => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>',
        'jam'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'cek'      => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14 9 11"/>',
        'telat'    => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
        'kalender' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'uang'     => '<path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'ganti'    => '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
        'cetak'    => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'filter'   => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'keluar'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'mata'     => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    ];
    return '<svg width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($p[$nama] ?? '') . '</svg>';
}
function kartu($label, $nilai, $ket = '', $col = 'col-6 col-md-3', $warna = '', $aksen = 'secondary', $ikon = '') {
    echo '<div class="' . $col . '"><div class="card kartu-stat h-100"><div class="card-body px-3 py-2 d-flex align-items-center gap-3">'
       . '<div class="ikon-tile bg-' . $aksen . '-subtle text-' . $aksen . '-emphasis">' . ikon($ikon, 20) . '</div>'
       . '<div>'
       . '<div class="text-secondary small">' . e($label) . '</div>'
       . '<div class="fs-5 fw-bold lh-1 my-1 ' . $warna . '">' . e($nilai) . '</div>'
       . ($ket !== '' ? '<div class="small text-secondary">' . e($ket) . '</div>' : '')
       . '</div></div></div></div>';
}

function field($label, $name, $data = null, $o = []) {
    $type = $o['type'] ?? 'text';
    $col  = $o['col'] ?? 'col-md-6';
    $val  = $type === 'password' ? '' : nilai($name, $data, $o['def'] ?? '');
    return '<div class="' . $col . '"><label class="form-label mb-1" for="' . $name . '">' . $label . '</label>'
         . '<input id="' . $name . '" name="' . $name . '" type="' . $type . '" class="form-control form-control-sm" placeholder="'
         . e($o['ph'] ?? '') . '" value="' . $val . '" ' . ($o['extra'] ?? '') . '></div>';
}
function opsi($daftar, $kunci, $labelFn, $terpilih) {
    $h = '';
    foreach ($daftar as $r) {
        $v = $r[$kunci];
        $h .= '<option value="' . e($v) . '"' . ((string)$v === (string)$terpilih ? ' selected' : '') . '>' . e($labelFn($r)) . '</option>';
    }
    return $h;
}
function form_hapus($id) {
    return '<form method="post" class="d-inline" onsubmit="return confirm(\'Yakin hapus data ini?\')">'
         . '<input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="' . (int)$id . '">'
         . '<button class="btn btn-sm btn-danger">Hapus</button></form>';
}
function paginasi($total, $per, $hal, $nama = 'hal', $anchor = '') {
    $n = (int)ceil($total / $per);
    if ($n <= 1) return '';
    $link = fn($i) => '?' . e(http_build_query(array_merge($_GET, [$nama => $i]))) . e($anchor);
    $h = '<nav><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">';
    $h .= '<li class="page-item' . ($hal <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . $link(max(1, $hal - 1)) . '">&lsaquo;</a></li>';
    for ($i = 1; $i <= $n; $i++) {
        $h .= '<li class="page-item' . ($i == $hal ? ' active' : '') . '"><a class="page-link" href="' . $link($i) . '">' . $i . '</a></li>';
    }
    $h .= '<li class="page-item' . ($hal >= $n ? ' disabled' : '') . '"><a class="page-link" href="' . $link(min($n, $hal + 1)) . '">&rsaquo;</a></li>';
    return $h . '</ul></nav>';
}

function foto($g) {
    $isi = $g ? '<img src="' . BASE . '/uploads/' . rawurlencode(basename($g)) . '" class="object-fit-cover" alt="Gambar alat">'
              : '<div class="bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center">Tanpa gambar</div>';
    return '<div class="ratio ratio-16x9 rounded-top overflow-hidden">' . $isi . '</div>';
}
function thumb($g) {
    return $g ? '<img src="' . BASE . '/uploads/' . rawurlencode(basename($g)) . '" width="48" height="48" class="rounded object-fit-cover" alt="">'
              : '<span class="badge text-bg-light border">-</span>';
}
function hapus_gambar($g) { if ($g) @unlink(__DIR__ . '/../uploads/' . basename($g)); }
function upload_gambar($file, &$errors) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Upload gambar gagal.'; return null; }
    if ($file['size'] > 2 * 1024 * 1024) { $errors[] = 'Ukuran gambar maksimal 2 MB.'; return null; }
    $info = @getimagesize($file['tmp_name']);
    $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($ok[$info['mime']])) { $errors[] = 'Gambar harus berformat JPG, PNG, atau WEBP.'; return null; }
    $nama = bin2hex(random_bytes(8)) . '.' . $ok[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $nama)) { $errors[] = 'Gagal menyimpan gambar.'; return null; }
    return $nama;
}

function user() {
    static $dicek = false;
    if (!isset($_SESSION['user'])) return null;
    if (!$dicek) {
        // Sesi lama bisa menunjuk akun yang sudah tidak ada (mis. database di-reset) atau berubah peran.
        $ada = ambil_satu('SELECT id_user, nama, role, jenis, kelas FROM users WHERE id_user = ?', 'i', [(int)($_SESSION['user']['id_user'] ?? 0)]);
        if (!$ada || $ada['role'] !== ($_SESSION['user']['role'] ?? null)) {
            unset($_SESSION['user']);
            return null;
        }
        $_SESSION['user'] = $ada;
        $dicek = true;
    }
    return $_SESSION['user'];
}
function wajib_login($roles = []) {
    if (!user()) pindah($roles && !in_array('peminjam', $roles, true) ? '/login_staf.php' : '/login.php');
    if ($roles && !in_array(user()['role'], $roles, true)) {
        pesan('danger', 'Anda tidak punya akses ke halaman tersebut.');
        pindah('/index.php');
    }
}
function catat($aktivitas) {
    jalankan('INSERT INTO log_aktivitas (id_user, aktivitas) VALUES (?, ?)', 'is', [user()['id_user'], mb_substr($aktivitas, 0, 255)]);
}
function ringkas_pinjam($id) {
    $d = ambil_satu('SELECT p.id_peminjaman, p.jumlah, a.nama_alat FROM peminjaman p
        JOIN alat a ON a.id_alat = p.id_alat WHERE p.id_peminjaman = ?', 'i', [(int)$id]);
    if (!$d) return '#' . (int)$id;
    return kode_pinjam($d['id_peminjaman']) . ' - ' . $d['nama_alat'] . ' x' . $d['jumlah'];
}
function ringkas_kembali($id_pengembalian) {
    $r = ambil_satu('SELECT id_peminjaman FROM pengembalian WHERE id_pengembalian = ?', 'i', [(int)$id_pengembalian]);
    return $r ? ringkas_pinjam($r['id_peminjaman']) : '#' . (int)$id_pengembalian;
}
function ket_telat($id_peminjaman) {
    $r = ambil_satu('SELECT terlambat_hari FROM pengembalian WHERE id_peminjaman = ?', 'i', [(int)$id_peminjaman]);
    if (!$r || (int)$r['terlambat_hari'] === 0) return '';
    return ' (terlambat ' . (int)$r['terlambat_hari'] . ' hari)';
}

function tgl_valid($s) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$s);
    return $d && $d->format('Y-m-d') === $s;
}
function validasi_peminjaman($p, &$errors) {
    $alat = ambil_satu('SELECT id_alat, stok_tersedia FROM alat WHERE id_alat = ?', 'i', [(int)$p['id_alat']]);
    if (!$alat) $errors[] = 'Pilih alat yang valid.';
    if (!ctype_digit($p['jumlah']) || (int)$p['jumlah'] < 1) $errors[] = 'Jumlah harus angka minimal 1.';
    elseif ($alat && (int)$p['jumlah'] > $alat['stok_tersedia']) $errors[] = 'Jumlah melebihi stok tersedia (' . $alat['stok_tersedia'] . ').';
    if (!tgl_valid($p['tanggal_pinjam'])) $errors[] = 'Tanggal pinjam tidak valid.';
    elseif ($p['tanggal_pinjam'] < date('Y-m-d')) $errors[] = 'Tanggal pinjam tidak boleh sebelum hari ini.';
    if (!tgl_valid($p['tanggal_jatuh_tempo'])) $errors[] = 'Tanggal jatuh tempo tidak valid.';
    elseif (tgl_valid($p['tanggal_pinjam'])) {
        $selisih = (strtotime($p['tanggal_jatuh_tempo']) - strtotime($p['tanggal_pinjam'])) / 86400;
        if ($selisih < 0) $errors[] = 'Jatuh tempo tidak boleh sebelum tanggal pinjam.';
        elseif ($selisih > MAKS_HARI) $errors[] = 'Lama peminjaman maksimal ' . MAKS_HARI . ' hari.';
    }
    $n = mb_strlen($p['keperluan']);
    if ($n < 5 || $n > 255) $errors[] = 'Keperluan harus 5-255 karakter.';
}

function hari_telat($tempo) { return max(0, (int)floor((strtotime(date('Y-m-d')) - strtotime($tempo)) / 86400)); }
function denda_berjalan($tempo, $jumlah) { return hari_telat($tempo) * TARIF_DENDA * (int)$jumlah; }
function daftar_pengembalian() {
    $t = tabel_param(['prioritas' => "(r.kondisi = 'belum_diperiksa') DESC, (r.status_ganti = 'belum') DESC, r.id_pengembalian",
        'alat' => 'a.nama_alat', 'peminjam' => 'u.nama', 'kembali' => 'r.tanggal_kembali', 'denda' => 'r.total_denda'], 'DESC', 10);
    $daftar = tabel_data($t, 'FROM pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman
        JOIN users u ON u.id_user = p.id_user JOIN alat a ON a.id_alat = p.id_alat',
        'r.*, u.nama AS peminjam, a.nama_alat, a.gambar, p.jumlah, p.tanggal_jatuh_tempo', ['a.nama_alat', 'u.nama', 'r.kondisi']);
    return [$daftar, $t];
}

function panggil($sql, $types = '', $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
}

function maks_aktif($jenis) { return $jenis === 'guru' ? MAKS_AKTIF_GURU : MAKS_AKTIF_SISWA; }
function blokir_peminjaman($id_user) {
    $masalah = [];
    $pinjam = ambil_satu("SELECT COUNT(*) AS n, COALESCE(SUM(tanggal_jatuh_tempo < CURDATE()), 0) AS telat FROM peminjaman WHERE id_user = ? AND status = 'dipinjam'", 'i', [$id_user]);
    if ((int)$pinjam['n'] > 0) {
        $masalah[] = 'Anda belum mengembalikan ' . (int)$pinjam['n'] . ' peminjaman sebelumnya' . ((int)$pinjam['telat'] > 0 ? ' (' . (int)$pinjam['telat'] . ' di antaranya sudah terlambat)' : '') . '. Kembalikan dulu sebelum meminjam lagi.';
    }
    $ganti = ambil_satu("SELECT COUNT(*) AS n, COALESCE(SUM(r.jumlah_bermasalah - r.jumlah_diganti), 0) AS unit FROM pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman WHERE p.id_user = ? AND r.status_ganti = 'belum'", 'i', [$id_user]);
    if ((int)$ganti['n'] > 0) {
        $masalah[] = 'Ada ' . (int)$ganti['unit'] . ' unit alat rusak/hilang yang belum Anda ganti. Ganti dan serahkan ke petugas lab sebelum meminjam lagi.';
    }
    $denda = (float)hitung("SELECT COALESCE(SUM(r.total_denda), 0) FROM pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman WHERE p.id_user = ? AND r.total_denda > 0 AND r.denda_dibayar IS NULL", 'i', [$id_user]);
    if ($denda > 0) {
        $masalah[] = 'Ada denda keterlambatan ' . rp($denda) . ' yang belum dibayar. Bayar ke petugas lab sebelum meminjam lagi.';
    }
    return $masalah;
}
function cek_boleh_meminjam($id_user, $jenis, $jumlah_baru) {
    $masalah = blokir_peminjaman($id_user);
    $aktif = hitung("SELECT COUNT(*) FROM peminjaman WHERE id_user = ? AND status IN ('menunggu', 'dipinjam')", 'i', [$id_user]);
    $batas = maks_aktif($jenis);
    if ($aktif + $jumlah_baru > $batas) {
        $masalah[] = 'Batas peminjaman aktif ' . ($jenis === 'guru' ? 'guru' : 'siswa') . ' adalah ' . $batas . ' alat. Saat ini Anda punya ' . $aktif . ' yang masih menunggu atau dipinjam.';
    }
    return $masalah;
}

function badge_ketepatan($hari) {
    return (int)$hari > 0 ? badge('Terlambat ' . (int)$hari . ' hari', 'danger') : badge('Tepat waktu', 'success');
}
function badge_denda($d) {
    if ((float)$d['total_denda'] <= 0) return '<span class="text-secondary">-</span>';
    return $d['denda_dibayar'] ? badge('Lunas', 'success') : badge('Belum dibayar', 'danger');
}
function tandai_denda_lunas($id) {
    $st = jalankan('UPDATE pengembalian SET denda_dibayar = NOW() WHERE id_pengembalian = ? AND total_denda > 0 AND denda_dibayar IS NULL', 'i', [$id]);
    if ($st->affected_rows < 1) { pesan('warning', 'Denda tidak ditemukan atau sudah lunas.'); return; }
    $r = ambil_satu('SELECT p.id_user, r.total_denda FROM pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman WHERE r.id_pengembalian = ?', 'i', [$id]);
    if ($r) jalankan("INSERT INTO notifikasi (id_user, jenis, pesan) VALUES (?, 'info', ?)", 'is', [$r['id_user'], 'Pembayaran denda ' . rp($r['total_denda']) . ' untuk ' . ringkas_kembali($id) . ' sudah diterima. Terima kasih.']);
    catat('Denda lunas ' . ringkas_kembali($id));
    pesan('success', 'Denda ditandai sudah dibayar.');
}

function info_ganti($d) {
    $m = (int)($d['jumlah_bermasalah'] ?? 0);
    if ($m < 1) return '';
    return (int)$d['jumlah_diganti'] . ' dari ' . $m . ' unit sudah diganti';
}

function notifikasi_peminjam() {
    $u = user();
    if (!$u || $u['role'] !== 'peminjam') return;
    $id = (int)$u['id_user'];

    $aktif = ambil_semua("SELECT p.id_peminjaman, p.jumlah, p.tanggal_jatuh_tempo, a.nama_alat
        FROM peminjaman p JOIN alat a ON a.id_alat = p.id_alat
        WHERE p.id_user = ? AND p.status = 'dipinjam' AND p.tanggal_jatuh_tempo <= CURDATE() ORDER BY p.tanggal_jatuh_tempo", 'i', [$id]);
    foreach ($aktif as $d) {
        $nama = kode_pinjam($d['id_peminjaman']) . ' (' . $d['nama_alat'] . ' x' . (int)$d['jumlah'] . ')';
        if ($d['tanggal_jatuh_tempo'] < date('Y-m-d')) {
            echo '<div class="alert alert-danger py-2 px-3 small" role="alert"><b>Terlambat ' . hari_telat($d['tanggal_jatuh_tempo']) . ' hari:</b> '
               . e($nama) . ' seharusnya dikembalikan ' . tgl($d['tanggal_jatuh_tempo']) . '. Denda berjalan '
               . rp(denda_berjalan($d['tanggal_jatuh_tempo'], $d['jumlah'])) . ' dan bertambah ' . rp(TARIF_DENDA * (int)$d['jumlah'])
               . ' setiap hari. Segera kembalikan alat ke petugas lab.</div>';
        } else {
            echo '<div class="alert alert-warning py-2 px-3 small" role="alert"><b>Jatuh tempo hari ini:</b> ' . e($nama)
               . ' harus dikembalikan hari ini agar tidak kena denda.</div>';
        }
    }

    $jumlah = hitung('SELECT COUNT(*) FROM notifikasi WHERE id_user = ? AND dibaca = 0', 'i', [$id]);
    if ($jumlah < 1) return;
    $daftar = ambil_semua('SELECT id_notif, jenis, pesan, created_at FROM notifikasi WHERE id_user = ? AND dibaca = 0 ORDER BY id_notif DESC LIMIT 5', 'i', [$id]);
    $kembali = basename($_SERVER['SCRIPT_NAME']);
    $warna = ['terlambat' => 'warning', 'ganti' => 'danger', 'info' => 'info'];
    foreach ($daftar as $n) {
        echo '<div class="alert alert-' . ($warna[$n['jenis']] ?? 'info') . ' py-2 px-3 small d-flex justify-content-between align-items-start gap-3" role="alert">'
           . '<div><b>' . ($n['jenis'] === 'terlambat' ? 'Informasi keterlambatan' : ($n['jenis'] === 'ganti' ? 'Penggantian alat' : 'Informasi')) . ':</b> '
           . e($n['pesan']) . ' <span class="text-secondary">(' . date('d-m-Y H:i', strtotime($n['created_at'])) . ')</span></div>'
           . '<form method="post" action="' . BASE . '/peminjam/notif.php" class="m-0 flex-shrink-0">'
           . '<input type="hidden" name="aksi" value="baca"><input type="hidden" name="id" value="' . (int)$n['id_notif'] . '">'
           . '<input type="hidden" name="kembali" value="' . e($kembali) . '"><button class="btn btn-sm btn-outline-secondary py-0">Tandai dibaca</button></form></div>';
    }
    if ($jumlah > 1) {
        echo '<form method="post" action="' . BASE . '/peminjam/notif.php" class="mb-3 text-end"><input type="hidden" name="aksi" value="semua">'
           . '<input type="hidden" name="kembali" value="' . e($kembali) . '"><button class="btn btn-sm btn-link text-secondary">Tandai semua dibaca (' . (int)$jumlah . ')</button></form>';
    }
}

require_once __DIR__ . '/../includes/tabel_fungsi.php';
