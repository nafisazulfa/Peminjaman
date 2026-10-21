<?php
require_once __DIR__ . '/config/fungsi.php';
if (user()) pindah('/index.php');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($detik = batas_login_cek()) {
        $errors[] = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . $detik . ' detik.';
    } elseif ($username === '') {
        $errors[] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username 3-50 karakter: huruf, angka, atau underscore.';
    }

    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    } elseif (mb_strlen($password) > PASSWORD_MAKS) {
        $errors[] = 'Password maksimal ' . PASSWORD_MAKS . ' karakter.';
    }

    if (!$errors) {
        $u = ambil_satu('SELECT id_user, nama, password, role, jenis, kelas FROM users WHERE username = ?', 's', [$username]);
        if (!$u || !cek_password($password, $u['password'], (int)$u['id_user'])) {
            batas_login_gagal();
            $errors[] = 'Username atau password salah.';
        } elseif ($u['role'] === 'peminjam') {
            $errors[] = 'Ini akun siswa atau guru. Silakan login lewat halaman Login Pengguna.';
        } else {
            unset($_SESSION['gagal_login']);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id_user' => $u['id_user'], 'nama' => $u['nama'], 'role' => $u['role'], 'jenis' => $u['jenis'], 'kelas' => $u['kelas']];
            catat('Login');
            pindah('/index.php');
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Petugas</title>
  <link href="<?= BASE ?>/assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-secondary-subtle">

<div class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
  <div class="row w-100 justify-content-center">
    <div class="col-12 col-md-11 col-lg-9 col-xl-8">

      <div class="card border-0 shadow overflow-hidden">
        <div class="row g-0">

          <div class="col-md-5 bg-dark text-white d-flex flex-column justify-content-center align-items-center text-center p-4 p-md-5">
            <svg width="64" height="64" fill="currentColor" viewBox="0 0 16 16"><path d="M13.5 3a.5.5 0 0 1 .5.5V11H2V3.5a.5.5 0 0 1 .5-.5zm-11-1A1.5 1.5 0 0 0 1 3.5V12h14V3.5A1.5 1.5 0 0 0 13.5 2zM0 12.5h16a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 12.5"/></svg>
            <h1 class="h4 fw-bold text-white mt-3 mb-2">Inventaris Lab Komputer</h1>
            <p class="small text-white-50 mb-0">Sistem Peminjaman Alat Laboratorium</p>
          </div>

          <div class="col-md-7 px-4 pt-4 pb-5">
            <div class="mb-4">
              <h2 class="h3 fw-bold text-dark mb-1">Login Petugas</h2>
              <p class="small text-body-secondary mb-0">Halaman khusus admin dan petugas laboratorium.</p>
            </div>

            <?php tampil_pesan(); ?>
            <?php tampil_error($errors); ?>

            <form method="post">
              <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input id="username" name="username" class="form-control" placeholder="Masukkan username" maxlength="50" required autofocus value="<?= nilai('username') ?>" pattern="[A-Za-z0-9_]{3,50}" title="3-50 karakter: huruf, angka, atau underscore">
              </div>
              <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input id="password" type="password" name="password" class="form-control" placeholder="Masukkan password" maxlength="50" required>
              </div>
              <button class="btn bg-dark text-white w-100">Login</button>
            </form>
            <div class="text-center small text-body-secondary mt-4">Siswa atau guru? <a href="<?= BASE ?>/login.php" class="link-dark">Login di sini</a></div>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

</body>
</html>