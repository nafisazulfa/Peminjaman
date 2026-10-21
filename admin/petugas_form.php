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
    pesan('danger', 'Data petugas tidak valid.');
    pindah('/admin/petugas.php');
}

if ($id > 0) {
    $edit = ambil_satu("SELECT id_user, nama, username, no_hp FROM users WHERE id_user = ? AND role = 'petugas'", 'i', [$id]);
    if (!$edit) {
        pesan('danger', 'Data petugas tidak ditemukan.');
        pindah('/admin/petugas.php');
    }
}

$errors = [];
$nama = $edit['nama'] ?? '';
$username = $edit['username'] ?? '';
$noHp = $edit['no_hp'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    $nama = is_string($_POST['nama'] ?? null) ? trim($_POST['nama']) : '';
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $noHp = is_string($_POST['no_hp'] ?? null) ? trim($_POST['no_hp']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $konfirmasi = is_string($_POST['konfirmasi'] ?? null) ? $_POST['konfirmasi'] : '';

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

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username 3-50 karakter: huruf, angka, atau underscore.';
    } elseif (ambil_satu('SELECT id_user FROM users WHERE username = ? AND id_user <> ?', 'si', [$username, $id])) {
        $errors[] = 'Username sudah dipakai.';
    }

    if ($noHp === '') {
        $errors[] = 'No HP wajib diisi.';
    } elseif (!preg_match('/^08[0-9]{8,12}$/', $noHp)) {
        $errors[] = 'No HP harus diawali 08 dan berisi 10-14 angka.';
    }

    $gantiPassword = ($id === 0) || $password !== '' || $konfirmasi !== '';
    if ($gantiPassword) {
        if ($err = error_password($password)) {
            $errors[] = $err;
        } elseif ($password !== $konfirmasi) {
            $errors[] = 'Konfirmasi password tidak sama.';
        }
    }

    if (!$errors) {
        try {
            if ($id > 0) {
                if ($gantiPassword) {
                    jalankan("UPDATE users SET nama = ?, username = ?, no_hp = ?, password = ? WHERE id_user = ? AND role = 'petugas'", 'ssssi', [$nama, $username, $noHp, buat_hash($password), $id]);
                } else {
                    jalankan("UPDATE users SET nama = ?, username = ?, no_hp = ? WHERE id_user = ? AND role = 'petugas'", 'sssi', [$nama, $username, $noHp, $id]);
                }
                catat('Ubah petugas ' . $username);
                pesan('success', 'Data petugas berhasil diperbarui.');
            } else {
                jalankan("INSERT INTO users (nama, username, password, role, no_hp) VALUES (?, ?, ?, 'petugas', ?)", 'ssss', [$nama, $username, buat_hash($password), $noHp]);
                catat('Tambah petugas ' . $username);
                pesan('success', 'Petugas baru berhasil ditambahkan.');
            }
            pindah('/admin/petugas.php');
        } catch (mysqli_sql_exception $ex) {
            $errors[] = pesan_error($ex);
        }
    }
}

$judul = $id > 0 ? 'Edit Petugas' : 'Tambah Petugas';
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
            <label class="form-label" for="nama">Nama Lengkap</label>
            <input id="nama" name="nama" class="form-control" placeholder="Nama lengkap petugas" minlength="3" maxlength="100" value="<?= e($nama) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="username">Username</label>
            <input id="username" name="username" class="form-control" placeholder="Contoh: petugas01" minlength="3" maxlength="50"
                   pattern="[A-Za-z0-9_]{3,50}" title="3-50 karakter: huruf, angka, atau underscore"
                   value="<?= e($username) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="no_hp">No HP</label>
            <input id="no_hp" name="no_hp" class="form-control" inputmode="numeric" maxlength="14"
                   pattern="08[0-9]{8,12}" title="Diawali 08, 10-14 angka" placeholder="08xxxxxxxxxx"
                   value="<?= e($noHp) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input id="password" type="password" name="password" class="form-control" placeholder="Minimal 5 karakter" minlength="5" maxlength="50"
                   <?= $id === 0 ? 'required' : '' ?>>
            <div class="form-text">
              <?= $id > 0 ? 'Kosongkan jika tidak ingin mengganti password. ' : '' ?>5-50 karakter, boleh huruf saja, angka saja, atau campuran.
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label" for="konfirmasi">Konfirmasi Password</label>
            <input id="konfirmasi" type="password" name="konfirmasi" class="form-control" placeholder="Ulangi password" maxlength="50"
                   <?= $id === 0 ? 'required' : '' ?>>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="<?= BASE ?>/admin/petugas.php" class="btn btn-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer_admin.php'; ?>
