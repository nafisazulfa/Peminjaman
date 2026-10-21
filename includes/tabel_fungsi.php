<?php

function tabel_link($ubah = []) {
    return basename($_SERVER['PHP_SELF']) . '?' . http_build_query(array_merge($_GET, $ubah));
}

function tabel_param($urutan, $arahAwal = 'DESC', $perHalaman = 10) {
    $kunci = array_key_first($urutan);
    $arah = strtolower($arahAwal);
    if (is_string($_GET['sort'] ?? null) && isset($urutan[$_GET['sort']])) {
        $kunci = $_GET['sort'];
        $arah = ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc';
    }
    $q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 50) : '';
    $hal = max(1, (int)($_GET['hal'] ?? 1));
    return ['q' => $q, 'kunci' => $kunci, 'arah' => $arah, 'urut' => $urutan[$kunci] . ' ' . strtoupper($arah),
            'hal' => $hal, 'per' => $perHalaman, 'offset' => ($hal - 1) * $perHalaman, 'total' => 0];
}

function tabel_data(&$t, $dari, $pilih, $cari, $filter = ['', '', []]) {
    [$where, $tipe, $par] = $filter;
    if ($t['q'] !== '') {
        $sama = [];
        foreach ($cari as $kolom) { $sama[] = "$kolom LIKE ?"; $tipe .= 's'; $par[] = '%' . $t['q'] . '%'; }
        $where .= ($where !== '' ? ' AND ' : '') . '(' . implode(' OR ', $sama) . ')';
    }
    if ($where !== '') $where = 'WHERE ' . $where;
    $t['total'] = hitung("SELECT COUNT(*) $dari $where", $tipe, $par);
    $halTerakhir = max(1, (int)ceil($t['total'] / $t['per']));
    if ($t['hal'] > $halTerakhir) {
        $t['hal'] = $halTerakhir;
        $t['offset'] = ($halTerakhir - 1) * $t['per'];
    }
    return ambil_semua("SELECT $pilih $dari $where ORDER BY {$t['urut']} LIMIT ? OFFSET ?",
        $tipe . 'ii', array_merge($par, [$t['per'], $t['offset']]));
}

function th_urut($t, $kunci, $label) {
    $aktif = $t['kunci'] === $kunci;
    $baru = ($aktif && $t['arah'] === 'asc') ? 'desc' : 'asc';
    $ikon = $aktif ? ($t['arah'] === 'asc' ? '&#9650;' : '&#9660;') : '<span class="opacity-25">&#8645;</span>';
    return '<a href="' . e(tabel_link(['sort' => $kunci, 'dir' => $baru, 'hal' => 1]) . ($GLOBALS['tabel_jangkar'] ?? '')) . '" class="text-decoration-none text-dark">' . e($label) . ' ' . $ikon . '</a>';
}

function bar_tabel($t, $placeholder, $urlTambah = '', $labelTambah = '', $tambahan = '') {
    echo '<div class="d-flex flex-wrap gap-2 align-items-start mb-3">';
    if ($urlTambah !== '') echo '<a href="' . BASE . $urlTambah . '" class="btn btn-primary btn-sm">+ ' . e($labelTambah) . '</a>';
    echo $tambahan;
    echo '<div class="col-12 col-md-6 col-xl-4 ms-auto"><form method="get" class="input-group input-group-sm">';
    foreach ($_GET as $k => $v) {
        if (is_string($v) && !in_array($k, ['q', 'hal'], true)) echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    echo '<input name="q" class="form-control" placeholder="' . e($placeholder) . '" maxlength="50" value="' . e($t['q']) . '">'
       . '<button class="btn btn-primary">Cari</button>';
    if ($t['q'] !== '') echo '<a href="?' . e(http_build_query(array_diff_key($_GET, ['q' => 1, 'hal' => 1]))) . '" class="btn btn-secondary">Reset</a>';
    echo '</form>';
    if ($t['q'] !== '') echo '<div class="form-text">' . (int)$t['total'] . ' hasil untuk "' . e($t['q']) . '"</div>';
    echo '</div></div>';
}

function tabel_buka($kolom, $t = null) {
    $GLOBALS['jumlah_kolom'] = count($kolom);
    echo '<div class="table-responsive"><table class="table table-bordered table-hover align-middle text-center mb-0"><thead><tr>';
    foreach ($kolom as $kunci => $label) {
        echo '<th class="table-light text-uppercase small' . ($label === 'Aksi' ? ' d-print-none' : '') . '">' . (is_string($kunci) && $t ? th_urut($t, $kunci, $label) : e($label)) . '</th>';
    }
    echo '</tr></thead><tbody>';
}
function tabel_tutup($ada = true, $pesanKosong = 'Belum ada data.') {
    if (!$ada) echo '<tr><td colspan="' . $GLOBALS['jumlah_kolom'] . '" class="text-secondary py-4">' . e($pesanKosong) . '</td></tr>';
    echo '</tbody></table></div>';
}

function pecah_log($teks) {
    if (strpos($teks, 'Peminjaman #') === 0) return ['Status', $teks];
    $kata = explode(' ', $teks, 2);
    return [$kata[0], $kata[1] ?? '-'];
}
function badge_aksi($aksi) {
    if (in_array($aksi, ['Login', 'Tambah', 'Mengajukan', 'Catat', 'Menyetujui'], true)) $warna = 'success';
    elseif (in_array($aksi, ['Edit', 'Mengubah', 'Periksa', 'Konfirmasi', 'Ubah'], true)) $warna = 'warning';
    elseif (in_array($aksi, ['Hapus', 'Logout', 'Membatalkan', 'Menolak'], true)) $warna = 'danger';
    else $warna = 'info';
    return badge($aksi, $warna);
}
