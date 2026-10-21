<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);
$errors = [];
$edit = isset($_GET['id']) ? ambil_satu('SELECT * FROM kategori WHERE id_kategori = ?', 'i', [(int)$_GET['id']]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_kategori'] ?? '');
    $des  = trim($_POST['deskripsi'] ?? '');

    if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) $errors[] = 'Nama kategori harus 3-100 karakter.';
    elseif (ambil_satu('SELECT id_kategori FROM kategori WHERE nama_kategori = ? AND id_kategori <> ?', 'si', [$nama, $id])) $errors[] = 'Nama kategori sudah ada.';
    if (mb_strlen($des) > 255) $errors[] = 'Deskripsi maksimal 255 karakter.';

    if (!$errors) {
        try {
            if ($id) {
                jalankan('UPDATE kategori SET nama_kategori=?, deskripsi=? WHERE id_kategori=?', 'ssi', [$nama, $des, $id]);
                catat("Edit kategori #$id");
            } else {
                jalankan('INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?,?)', 'ss', [$nama, $des]);
                catat("Tambah kategori $nama");
            }
            pesan('success', 'Kategori berhasil disimpan.');
            pindah('/admin/kategori.php');
        } catch (mysqli_sql_exception $ex) {
            $errors[] = pesan_error($ex);
        }
    }}

$judul = $edit ? 'Edit Kategori' : 'Tambah Kategori';
include __DIR__ . '/../includes/header.php';
tampil_error($errors);
?>
<div class="card"><div class="card-body">
  <form method="post" class="row g-3">
    
    <input type="hidden" name="id" value="<?= (int)($edit['id_kategori'] ?? 0) ?>">
    <?= field('Nama kategori', 'nama_kategori', $edit, ['ph' => 'Contoh: Periferal', 'extra' => 'required maxlength="100"']) ?>
    <?= field('Deskripsi', 'deskripsi', $edit, ['ph' => 'Keterangan singkat (opsional)', 'extra' => 'maxlength="255"']) ?>
    <div class="col-12">
      <button class="btn btn-primary btn-sm">Simpan</button>
      <a href="<?= BASE ?>/admin/kategori.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
  </form>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
