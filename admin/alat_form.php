<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);
$errors = [];
$kategori = ambil_semua('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori');
$edit = isset($_GET['id']) ? ambil_satu('SELECT * FROM alat WHERE id_alat = ?', 'i', [(int)$_GET['id']]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $id_kat   = (int)($_POST['id_kategori'] ?? 0);
    $kode     = strtoupper(trim($_POST['kode_alat'] ?? ''));
    $nama     = trim($_POST['nama_alat'] ?? '');
    $total    = trim($_POST['stok_total'] ?? '');
    $tersedia = trim($_POST['stok_tersedia'] ?? '');
    $lokasi   = trim($_POST['lokasi'] ?? '');
    $des      = trim($_POST['deskripsi'] ?? '');

    if (!ambil_satu('SELECT id_kategori FROM kategori WHERE id_kategori = ?', 'i', [$id_kat])) $errors[] = 'Pilih kategori yang valid.';
    if (!preg_match('/^[A-Z0-9-]{3,20}$/', $kode)) $errors[] = 'Kode alat 3-20 karakter: huruf, angka, atau tanda minus.';
    elseif (ambil_satu('SELECT id_alat FROM alat WHERE kode_alat = ? AND id_alat <> ?', 'si', [$kode, $id])) $errors[] = 'Kode alat sudah dipakai.';
    if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) $errors[] = 'Nama alat harus 3-100 karakter.';
    if (!ctype_digit($total) || (int)$total > 1000) $errors[] = 'Stok total harus angka 0-1000.';
    if ($tersedia === '' && $id === 0 && ctype_digit($total)) $tersedia = $total;
    if (!ctype_digit($tersedia)) $errors[] = 'Stok tersedia harus angka.';
    elseif (ctype_digit($total) && (int)$tersedia > (int)$total) $errors[] = 'Stok tersedia tidak boleh lebih besar dari stok total.';
    if (!in_array($lokasi, DAFTAR_LAB, true)) $errors[] = 'Pilih lab yang valid.';
    if (mb_strlen($des) > 255) $errors[] = 'Deskripsi maksimal 255 karakter.';

    $gambar = null;
    if (!$errors) $gambar = upload_gambar($_FILES['gambar'] ?? null, $errors);

    if (!$errors) {
        try {
            if ($id) {
                $lama = ambil_satu('SELECT gambar FROM alat WHERE id_alat = ?', 'i', [$id]);
                if ($gambar) {
                    jalankan('UPDATE alat SET id_kategori=?, kode_alat=?, nama_alat=?, stok_total=?, stok_tersedia=?, lokasi=?, deskripsi=?, gambar=? WHERE id_alat=?',
                        'issiisssi', [$id_kat, $kode, $nama, (int)$total, (int)$tersedia, $lokasi, $des, $gambar, $id]);
                    if ($lama) hapus_gambar($lama['gambar']);
                } else {
                    jalankan('UPDATE alat SET id_kategori=?, kode_alat=?, nama_alat=?, stok_total=?, stok_tersedia=?, lokasi=?, deskripsi=? WHERE id_alat=?',
                        'issiissi', [$id_kat, $kode, $nama, (int)$total, (int)$tersedia, $lokasi, $des, $id]);
                }
                catat("Edit alat #$id");
            } else {
                jalankan('INSERT INTO alat (id_kategori, kode_alat, nama_alat, stok_total, stok_tersedia, lokasi, deskripsi, gambar) VALUES (?,?,?,?,?,?,?,?)',
                    'issiisss', [$id_kat, $kode, $nama, (int)$total, (int)$tersedia, $lokasi, $des, $gambar]);
                catat("Tambah alat $kode");
            }
            pesan('success', 'Data alat berhasil disimpan.');
            pindah('/admin/alat.php');
        } catch (mysqli_sql_exception $ex) {
            if ($gambar) hapus_gambar($gambar);
            $errors[] = pesan_error($ex);
        }
    }}

$judul = $edit ? 'Edit Alat' : 'Tambah Alat';
include __DIR__ . '/../includes/header.php';
tampil_error($errors);
?>
<div class="card"><div class="card-body">
  <form method="post" enctype="multipart/form-data" class="row g-3">
    
    <input type="hidden" name="id" value="<?= (int)($edit['id_alat'] ?? 0) ?>">
    <?= field('Kode alat', 'kode_alat', $edit, ['ph' => 'Contoh: ALT007', 'col' => 'col-md-3', 'extra' => 'required maxlength="20"']) ?>
    <?= field('Nama alat', 'nama_alat', $edit, ['ph' => 'Contoh: Headset', 'col' => 'col-md-5', 'extra' => 'required maxlength="100"']) ?>
    <div class="col-md-4">
      <label class="form-label mb-1" for="id_kategori">Kategori</label>
      <select id="id_kategori" name="id_kategori" class="form-select form-select-sm" required>
        <option value="">Pilih kategori</option>
        <?= opsi($kategori, 'id_kategori', fn($r) => $r['nama_kategori'], $_POST['id_kategori'] ?? ($edit['id_kategori'] ?? '')) ?>
      </select>
    </div>
    <?= field('Stok total', 'stok_total', $edit, ['type' => 'number', 'ph' => 'Jumlah seluruh unit', 'col' => 'col-md-3', 'extra' => 'required min="0" max="1000"']) ?>
    <?= field('Stok tersedia', 'stok_tersedia', $edit, ['type' => 'number', 'ph' => $edit ? 'Unit siap dipinjam' : 'Kosong = sama dengan stok total', 'col' => 'col-md-3', 'extra' => 'min="0" max="1000"' . ($edit ? ' required' : '')]) ?>
    <div class="col-md-6">
      <label class="form-label mb-1" for="lokasi">Lab</label>
      <select id="lokasi" name="lokasi" class="form-select form-select-sm" required>
        <option value="">Pilih lab</option>
        <?php foreach (DAFTAR_LAB as $l): ?>
          <option value="<?= e($l) ?>" <?= nilai('lokasi', $edit) === e($l) ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?= field('Deskripsi', 'deskripsi', $edit, ['ph' => 'Keterangan singkat (opsional)', 'col' => 'col-md-8', 'extra' => 'maxlength="255"']) ?>
    <div class="col-md-4">
      <label class="form-label mb-1" for="gambar">Gambar alat</label>
      <input id="gambar" type="file" name="gambar" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
      <div class="form-text">JPG, PNG, atau WEBP. Maksimal 2 MB.<?= $edit && $edit['gambar'] ? ' Kosongkan jika tidak diganti.' : '' ?></div>
    </div>
    <?php if ($edit && !empty($edit['gambar'])): ?>
      <div class="col-12"><div class="d-flex align-items-center gap-2"><?= thumb($edit['gambar']) ?><span class="text-secondary small">Gambar saat ini</span></div></div>
    <?php endif; ?>
    <div class="col-12">
      <button class="btn btn-primary btn-sm">Simpan</button>
      <a href="<?= BASE ?>/admin/alat.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
  </form>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
