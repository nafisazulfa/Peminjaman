<?php
require_once __DIR__ . '/../config/fungsi.php';
wajib_login(['admin']);

$dari = is_string($_GET['dari'] ?? null) ? $_GET['dari'] : date('Y-m-d', strtotime('-7 days'));
$sampai = is_string($_GET['sampai'] ?? null) ? $_GET['sampai'] : date('Y-m-d');
if (!tgl_valid($dari) || !tgl_valid($sampai) || $dari > $sampai) {
    $dari = date('Y-m-d', strtotime('-7 days'));
    $sampai = date('Y-m-d');
}

$pengguna = (isset($_GET['pengguna']) && is_string($_GET['pengguna']) && ctype_digit($_GET['pengguna'])) ? (int)$_GET['pengguna'] : 0;
$daftarPengguna = ambil_semua('SELECT DISTINCT u.id_user, u.nama, u.role FROM log_aktivitas l JOIN users u ON u.id_user = l.id_user ORDER BY u.role, u.nama');

$t = tabel_param(['waktu' => 'l.waktu', 'pengguna' => 'u.nama', 'aksi' => 'l.aktivitas'], 'DESC', 20);
$daftar = tabel_data($t, 'FROM log_aktivitas l LEFT JOIN users u ON u.id_user = l.id_user', 'l.waktu, l.aktivitas, u.nama, u.role',
    ['u.nama', 'l.aktivitas'],
    $pengguna > 0 ? ['DATE(l.waktu) BETWEEN ? AND ? AND l.id_user = ?', 'ssi', [$dari, $sampai, $pengguna]] : ['DATE(l.waktu) BETWEEN ? AND ?', 'ss', [$dari, $sampai]]);
$judul = 'Log Aktivitas';
include __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3"><div class="card-body">
  <form method="get" class="row g-2 align-items-end">
    <?= field('Dari tanggal', 'dari', null, ['type' => 'date', 'col' => 'col-sm-3', 'def' => $dari]) ?>
    <?= field('Sampai tanggal', 'sampai', null, ['type' => 'date', 'col' => 'col-sm-3', 'def' => $sampai]) ?>
    <div class="col-sm-4">
      <label class="form-label mb-1" for="pengguna">Pengguna</label>
      <select id="pengguna" name="pengguna" class="form-select form-select-sm">
        <option value="">Semua pengguna</option>
        <?= opsi($daftarPengguna, 'id_user', fn($r) => $r['nama'] . ' (' . $r['role'] . ')', (string)$pengguna) ?>
      </select>
    </div>
    <div class="col-sm-2 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-fill d-inline-flex align-items-center gap-1 justify-content-center"><?= ikon('filter', 16) ?>Filter</button>
      <a href="<?= BASE ?>/admin/log.php" class="btn btn-secondary btn-sm">Reset</a>
    </div>
  </form>
</div></div>

  <?php tabel_buka(['No', 'waktu' => 'Tanggal & Waktu', 'pengguna' => 'Pengguna', 'aksi' => 'Aksi', 'Keterangan'], $t); ?>
    <?php foreach ($daftar as $i => $d): [$aksi, $ket] = pecah_log($d['aktivitas']); ?>
      <tr>
        <td><?= $t['offset'] + $i + 1 ?></td>
        <td class="text-nowrap"><?= date('d-m-Y H:i:s', strtotime($d['waktu'])) ?></td>
        <td class="text-start"><?= e($d['nama'] ?? 'Pengguna dihapus') ?><?= $d['role'] ? ' <span class="text-secondary">(' . e($d['role']) . ')</span>' : '' ?></td>
        <td><?= badge_aksi($aksi) ?></td>
        <td class="text-start"><?= e($ket) ?></td>
      </tr>
    <?php endforeach; ?>
  <?php tabel_tutup($daftar, 'Tidak ada log pada periode ini.'); ?>
  <?= paginasi($t['total'], $t['per'], $t['hal']) ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
