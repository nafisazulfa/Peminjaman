<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['peminjam']);
$errors = [];
$alatList = ambil_semua('SELECT id_alat, nama_alat, lokasi, stok_tersedia FROM alat WHERE stok_tersedia > 0 ORDER BY lokasi, nama_alat');

$baris = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['id_alat'] ?? [];
    $jml = $_POST['jumlah'] ?? [];
    foreach ((array)$ids as $i => $idAlat) $baris[] = ['id_alat' => (int)$idAlat, 'jumlah' => trim((string)($jml[$i] ?? ''))];
} else {
    $baris[] = ['id_alat' => (int)($_GET['alat'] ?? 0), 'jumlah' => '1'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_pinjam = trim($_POST['tanggal_pinjam'] ?? '');
    $tanggal_tempo  = trim($_POST['tanggal_jatuh_tempo'] ?? '');
    $keperluan      = trim($_POST['keperluan'] ?? '');

    if (!$baris) $errors[] = 'Pilih minimal satu alat.';
    $sudah = [];
    foreach ($baris as $n => $b) {
        $no = $n + 1;
        if ($b['id_alat'] === 0) { $errors[] = "Alat ke-$no belum dipilih."; continue; }
        if (in_array($b['id_alat'], $sudah, true)) { $errors[] = "Alat ke-$no dipilih lebih dari sekali. Gabungkan jumlahnya di satu baris."; continue; }
        $sudah[] = $b['id_alat'];
        $err = [];
        validasi_peminjaman(['id_alat' => $b['id_alat'], 'jumlah' => $b['jumlah'], 'tanggal_pinjam' => $tanggal_pinjam,
            'tanggal_jatuh_tempo' => $tanggal_tempo, 'keperluan' => $keperluan], $err);
        foreach ($err as $x) {
            $errors[] = (strpos($x, 'Jumlah') === 0 || strpos($x, 'Pilih') === 0) ? "Alat ke-$no: $x" : $x;
        }
    }
    $errors = array_merge($errors, cek_boleh_meminjam(user()['id_user'], user()['jenis'] ?? '', count($baris)));
    $errors = array_values(array_unique($errors));

    if (!$errors) {
        try {
            $idBaru = [];
            $db->begin_transaction();
            foreach ($baris as $b) {
                $st = jalankan('INSERT INTO peminjaman (id_user, id_alat, jumlah, tanggal_pinjam, tanggal_jatuh_tempo, keperluan) VALUES (?,?,?,?,?,?)',
                    'iiisss', [user()['id_user'], $b['id_alat'], (int)$b['jumlah'], $tanggal_pinjam, $tanggal_tempo, $keperluan]);
                $idBaru[] = $st->insert_id;
            }
            $db->commit();
            foreach ($idBaru as $idp) {
                catat('Mengajukan peminjaman ' . ringkas_pinjam($idp));
            }
            pesan('success', 'Pengajuan ' . count($baris) . ' alat terkirim. Tunggu persetujuan petugas.');
            pindah('/index.php');
        } catch (mysqli_sql_exception $ex) {
            $db->rollback();
            $errors[] = pesan_error($ex);
        }
    }
}

function baris_alat($alatList, $idPilih, $jumlah) {
    $h = '<tr class="baris-alat"><td><select name="id_alat[]" class="form-select form-select-sm" required><option value="">Pilih alat</option>'
       . opsi($alatList, 'id_alat', fn($r) => $r['nama_alat'], $idPilih)
       . '</select></td>'
       . '<td><input type="number" name="jumlah[]" class="form-control form-control-sm text-center" min="1" placeholder="Jumlah" value="' . e($jumlah) . '" required></td>'
       . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="hapusBaris(this)">Hapus</button></td></tr>';
    return $h;
}

$judul = 'Ajukan Peminjaman';
include __DIR__ . '/../includes/header.php';
$blokir = blokir_peminjaman(user()['id_user']);
if ($blokir) {
    echo '<div class="alert alert-danger py-2 px-3 small"><b>Anda belum bisa meminjam alat.</b>';
    foreach ($blokir as $m) echo '<div>&bull; ' . e($m) . '</div>';
    echo '</div><a href="' . BASE . '/peminjam/pengembalian.php" class="btn btn-primary btn-sm">Lihat peminjaman saya</a>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    foreach (cek_boleh_meminjam(user()['id_user'], user()['jenis'] ?? '', 0) as $m) echo '<div class="alert alert-warning py-2 px-3 small">' . e($m) . '</div>';
}
tampil_error($errors);
?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card"><div class="card-body">
      <form method="post" class="row g-3">

        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label mb-0 fw-semibold">Alat yang dipinjam</label>
            <button type="button" class="btn btn-success btn-sm" onclick="tambahBaris()">+ Tambah alat</button>
          </div>
          <table class="table table-bordered align-middle text-center mb-0">
            <thead><tr><th class="table-light text-uppercase small">Alat</th><th class="table-light text-uppercase small kolom-130px">Jumlah</th><th class="table-light text-uppercase small kolom-90px">Aksi</th></tr></thead>
            <tbody id="daftar-baris">
              <?php foreach ($baris as $b) echo baris_alat($alatList, $b['id_alat'], $b['jumlah']); ?>
            </tbody>
          </table>
        </div>

        <?= field('Tanggal pinjam', 'tanggal_pinjam', null, ['type' => 'date', 'def' => date('Y-m-d'), 'extra' => 'required min="' . date('Y-m-d') . '"']) ?>
        <?= field('Jatuh tempo (maks. ' . MAKS_HARI . ' hari)', 'tanggal_jatuh_tempo', null, ['type' => 'date', 'def' => date('Y-m-d', strtotime('+3 days')), 'extra' => 'required min="' . date('Y-m-d') . '"']) ?>
        <div class="col-12">
          <label class="form-label mb-1" for="keperluan">Keperluan</label>
          <textarea id="keperluan" name="keperluan" class="form-control form-control-sm" rows="3" placeholder="Contoh: Praktik pemrograman web kelas XII RPL" minlength="5" maxlength="255" required><?= nilai('keperluan') ?></textarea>
        </div>
        <div class="col-12">
          <button class="btn btn-primary btn-sm">Ajukan</button>
          <a href="<?= BASE ?>/peminjam/dashboard.php#daftar-alat" class="btn btn-secondary btn-sm">Lihat daftar alat</a>
        </div>
      </form>
    </div></div>
  </div>

</div>

<template id="cetakan-baris"><?= baris_alat($alatList, 0, '1') ?></template>
<script>
function tambahBaris() {
  var cetakan = document.getElementById('cetakan-baris').content.cloneNode(true);
  document.getElementById('daftar-baris').appendChild(cetakan);
}
function hapusBaris(tombol) {
  var tabel = document.getElementById('daftar-baris');
  if (tabel.rows.length > 1) {
    tabel.removeChild(tombol.closest('tr'));
  } else {
    alert('Minimal harus ada satu alat.');
  }
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
