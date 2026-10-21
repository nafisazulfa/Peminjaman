<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$idRaw = $_POST['id'] ?? $_GET['id'] ?? '';
$id = (is_string($idRaw) && ctype_digit($idRaw)) ? (int)$idRaw : 0;
$edit = null;

if ($idRaw !== '' && $id < 1) {
    pesan('danger', 'Data anggota tidak valid.');
    pindah('/admin/peminjam.php');
}

if ($id > 0) {
    $edit = ambil_satu("SELECT id_user, nama, no_hp, jenis, nomor_induk, kelas FROM users WHERE id_user = ? AND role = 'peminjam'", 'i', [$id]);
    if (!$edit) {
        pesan('danger', 'Data anggota tidak ditemukan.');
        pindah('/admin/peminjam.php');
    }
}

$jenisAwal = (isset($_GET['jenis']) && $_GET['jenis'] === 'guru') ? 'guru' : 'siswa';
$errors = [];
$nama = $edit['nama'] ?? '';
$noHp = $edit['no_hp'] ?? '';
$jenis = $edit['jenis'] ?? $jenisAwal;
$nomorInduk = $edit['nomor_induk'] ?? '';
$kelas = $edit['kelas'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    $nama = is_string($_POST['nama'] ?? null) ? trim($_POST['nama']) : '';
    $noHp = is_string($_POST['no_hp'] ?? null) ? trim($_POST['no_hp']) : '';
    $jenis = is_string($_POST['jenis'] ?? null) ? $_POST['jenis'] : '';
    $nomorInduk = is_string($_POST['nomor_induk'] ?? null) ? trim($_POST['nomor_induk']) : '';
    $kelas = is_string($_POST['kelas'] ?? null) ? trim($_POST['kelas']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!hash_equals($_SESSION['csrf'], $token)) {
        $errors[] = 'Formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    if ($nama === '') {
        $errors[] = 'Nama wajib diisi.';
    } elseif (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
        $errors[] = 'Nama harus 3-100 karakter.';
    } elseif (!preg_match('/^[\p{L}\s.\'-]+$/u', $nama)) {
        $errors[] = 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.';
    }

    if (!in_array($jenis, ['siswa', 'guru'], true)) {
        $errors[] = 'Pilih kategori siswa atau guru.';
    }

    if ($nomorInduk === '') {
        $errors[] = 'NISN / NIP wajib diisi.';
    } elseif (!preg_match('/^[0-9]{4,25}$/', $nomorInduk)) {
        $errors[] = 'NISN / NIP hanya angka, 4-25 digit.';
    } elseif (ambil_satu('SELECT id_user FROM users WHERE (nomor_induk = ? OR username = ?) AND id_user <> ?', 'ssi', [$nomorInduk, $nomorInduk, $id])) {
        $errors[] = 'NISN / NIP sudah dipakai akun lain.';
    }

    if ($jenis === 'siswa') {
        if ($kelas === '') {
            $errors[] = 'Kelas wajib diisi untuk siswa.';
        } elseif (!preg_match('/^[A-Za-z0-9 .-]{2,30}$/', $kelas)) {
            $errors[] = 'Kelas 2-30 karakter: huruf, angka, spasi, titik, atau tanda hubung.';
        }
    } else {
        $kelas = '';
    }

    if ($noHp !== '' && !preg_match('/^08[0-9]{8,12}$/', $noHp)) {
        $errors[] = 'No HP harus diawali 08 dan berisi 10-14 angka.';
    }

    $gantiPassword = ($id === 0) || $password !== '';
    if ($gantiPassword && ($err = error_password($password))) {
        $errors[] = $err;
    }

    if (!$errors) {
        $kelasDb = $kelas === '' ? null : $kelas;
        $hpDb = $noHp === '' ? null : $noHp;

        try {
            if ($id > 0) {
                jalankan("UPDATE users SET nama = ?, username = ?, no_hp = ?, jenis = ?, nomor_induk = ?, kelas = ? WHERE id_user = ? AND role = 'peminjam'", 'ssssssi', [$nama, $nomorInduk, $hpDb, $jenis, $nomorInduk, $kelasDb, $id]);
                if ($gantiPassword) {
                    jalankan("UPDATE users SET password = ? WHERE id_user = ? AND role = 'peminjam'", 'si', [buat_hash($password), $id]);
                }
                catat('Ubah anggota ' . $nomorInduk);
                pesan('success', 'Data anggota berhasil diperbarui.');
            } else {
                jalankan("INSERT INTO users (nama, username, password, role, no_hp, jenis, nomor_induk, kelas) VALUES (?, ?, ?, 'peminjam', ?, ?, ?, ?)", 'sssssss', [$nama, $nomorInduk, buat_hash($password), $hpDb, $jenis, $nomorInduk, $kelasDb]);
                catat('Tambah anggota ' . $nomorInduk);
                pesan('success', 'Anggota baru berhasil ditambahkan.');
            }
            pindah('/admin/peminjam.php?jenis=' . $jenis);
        } catch (mysqli_sql_exception $ex) {
            $errors[] = pesan_error($ex);
        }
    }
}

$judul = $id > 0 ? 'Edit Anggota' : 'Tambah Anggota';
$batalJenis = in_array($jenis, ['siswa', 'guru'], true) ? $jenis : 'siswa';

$skrip = <<<'JS'
<script>
var pilihJenis = document.getElementById('jenis');
var kolomKelas = document.getElementById('kolom-kelas');
var inputKelas = document.getElementById('kelas');
var labelInduk = document.getElementById('label-induk');

function aturForm() {
    var siswa = pilihJenis.value === 'siswa';
    kolomKelas.classList.toggle('d-none', !siswa);
    inputKelas.required = siswa;
    labelInduk.textContent = siswa ? 'NISN' : 'NIP';
    document.getElementById('nomor_induk').placeholder = siswa ? 'Contoh: 0012345678' : 'Contoh: 198001012005011001';
}

pilihJenis.addEventListener('change', aturForm);
aturForm();
</script>
JS;

include __DIR__ . '/../includes/header_admin.php';
?>

<div class="row">
  <div class="col-lg-7 col-xl-6">
    <div class="card">
      <div class="card-body p-4">
        <?php tampil_error($errors); ?>

        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <input type="hidden" name="id" value="<?= $id > 0 ? $id : '' ?>">

          <div class="mb-3">
            <label class="form-label" for="jenis">Kategori</label>
            <select id="jenis" name="jenis" class="form-select" required>
              <option value="siswa"<?= $jenis === 'siswa' ? ' selected' : '' ?>>Siswa</option>
              <option value="guru"<?= $jenis === 'guru' ? ' selected' : '' ?>>Guru</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="nama">Nama Lengkap</label>
            <input id="nama" name="nama" class="form-control" placeholder="Nama lengkap" minlength="3" maxlength="100" value="<?= e($nama) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="nomor_induk" id="label-induk">NISN</label>
            <input id="nomor_induk" name="nomor_induk" class="form-control" placeholder="Contoh: 0012345678" inputmode="numeric" minlength="4" maxlength="25"
                   pattern="[0-9]{4,25}" title="Hanya angka, 4-25 digit" value="<?= e($nomorInduk) ?>" required>
          </div>

          <div class="mb-3" id="kolom-kelas">
            <label class="form-label" for="kelas">Kelas</label>
            <input id="kelas" name="kelas" class="form-control" minlength="2" maxlength="30" placeholder="Contoh: XII RPL 1"
                   pattern="[A-Za-z0-9 .\-]{2,30}" title="2-30 karakter: huruf, angka, spasi, titik, atau tanda hubung"
                   value="<?= e($kelas) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label" for="no_hp">No HP <span class="text-secondary">(opsional)</span></label>
            <input id="no_hp" name="no_hp" class="form-control" inputmode="numeric" maxlength="14"
                   pattern="08[0-9]{8,12}" title="Diawali 08, 10-14 angka" placeholder="08xxxxxxxxxx"
                   value="<?= e($noHp) ?>">
          </div>

          <div class="mb-4">
            <label class="form-label" for="password">Password</label>
            <input id="password" type="password" name="password" class="form-control" placeholder="Minimal 5 karakter" minlength="5" maxlength="50" autocomplete="new-password"
                   <?= $id === 0 ? 'required' : '' ?>>
            <div class="form-text">
              <?= $id > 0 ? 'Kosongkan jika tidak ingin mengganti password. ' : '' ?>5-50 karakter, boleh huruf saja, angka saja, atau campuran.
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="<?= BASE ?>/admin/peminjam.php?jenis=<?= $batalJenis ?>" class="btn btn-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer_admin.php'; ?>
