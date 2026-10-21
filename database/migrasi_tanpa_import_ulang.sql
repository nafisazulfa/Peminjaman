-- =====================================================================
-- MIGRASI DATABASE (data lama TIDAK dihapus)
-- Pakai file ini kalau database kamu sudah berisi data dan muncul error seperti:
--   "Unknown column 'jenis' in 'field list'"
-- Cara: phpMyAdmin > pilih database db_peminjaman_lab > tab SQL > tempel isi file ini > Kirim.
-- Aman dijalankan berulang kali. Untuk XAMPP (MariaDB). Setelah itu buka cek_database.php.
-- JANGAN pakai db_peminjaman_lab.sql kalau tidak mau data lama hilang (file itu menghapus database).
-- =====================================================================
USE db_peminjaman_lab;

-- 1. Kolom baru pada tabel users
ALTER TABLE users ADD COLUMN IF NOT EXISTS jenis ENUM('siswa','guru') NULL AFTER no_hp;
ALTER TABLE users ADD COLUMN IF NOT EXISTS nomor_induk VARCHAR(25) NULL AFTER jenis;
ALTER TABLE users ADD COLUMN IF NOT EXISTS kelas VARCHAR(30) NULL AFTER nomor_induk;
ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uk_nomor_induk (nomor_induk);

-- 2. Peminjam lama dianggap siswa
UPDATE users SET jenis = 'siswa' WHERE role = 'peminjam' AND jenis IS NULL;

-- 2b. LOGIN PEMINJAM PAKAI NISN/NIP + PASSWORD
--     Username peminjam disamakan dengan NISN/NIP. Password awal = NISN/NIP (hanya jika masih kosong)
--     agar akun lama tetap bisa masuk; segera ubah lewat menu Data Peminjam > Edit.
--     Peminjam yang nomor induknya masih kosong TIDAK bisa login: isi dulu lewat menu Data Peminjam.
ALTER TABLE users MODIFY password VARCHAR(255) NULL;
UPDATE IGNORE users SET username = nomor_induk, password = COALESCE(password, MD5(nomor_induk))
 WHERE role = 'peminjam' AND nomor_induk IS NOT NULL AND nomor_induk <> '';

-- 2c. Penggantian alat rusak/hilang dihitung per unit, plus pesan untuk peminjam
ALTER TABLE pengembalian ADD COLUMN IF NOT EXISTS jumlah_bermasalah INT NOT NULL DEFAULT 0 AFTER status_ganti;
ALTER TABLE pengembalian ADD COLUMN IF NOT EXISTS jumlah_diganti INT NOT NULL DEFAULT 0 AFTER jumlah_bermasalah;
ALTER TABLE pengembalian ADD COLUMN IF NOT EXISTS tanggal_ganti DATE NULL AFTER jumlah_diganti;
-- data lama: seluruh unit pada transaksi rusak/hilang dianggap bermasalah; yang sudah diganti dianggap lunas
UPDATE pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman
   SET r.jumlah_bermasalah = p.jumlah
 WHERE r.kondisi IN ('rusak','hilang') AND r.jumlah_bermasalah = 0;
UPDATE pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman
   SET r.jumlah_diganti = p.jumlah, r.tanggal_ganti = COALESCE(r.tanggal_ganti, r.tanggal_kembali)
 WHERE r.status_ganti = 'sudah' AND r.jumlah_diganti = 0;

-- 2d. Pembayaran denda. Denda lama (sebelum migrasi) dianggap SUDAH dibayar agar peminjam lama tidak mendadak terblokir.
--     Kalau ada yang sebenarnya belum bayar, kosongkan lewat: UPDATE pengembalian SET denda_dibayar = NULL WHERE id_pengembalian = ...;
ALTER TABLE pengembalian ADD COLUMN IF NOT EXISTS denda_dibayar DATETIME NULL AFTER tanggal_ganti;
UPDATE pengembalian SET denda_dibayar = CONCAT(tanggal_kembali, ' 00:00:00')
 WHERE total_denda > 0 AND denda_dibayar IS NULL AND tanggal_kembali < CURDATE();

CREATE TABLE IF NOT EXISTS notifikasi (
  id_notif INT AUTO_INCREMENT PRIMARY KEY,
  id_user INT NOT NULL,
  jenis ENUM('terlambat','ganti','info') NOT NULL DEFAULT 'info',
  pesan VARCHAR(255) NOT NULL,
  dibaca TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
);
ALTER TABLE notifikasi ADD INDEX IF NOT EXISTS idx_notif_user (id_user, dibaca);

-- 3. Lokasi alat harus salah satu dari dua lab
UPDATE alat SET lokasi = 'Lab Informatika' WHERE nama_alat IN ('Kabel LAN', 'LAN Tester', 'Router', 'Switch', 'Arduino Uno');
UPDATE alat SET lokasi = 'Lab Komputer' WHERE lokasi IS NULL OR lokasi NOT IN ('Lab Komputer', 'Lab Informatika');

-- 4. Function, trigger, dan procedure dibuat ulang (denda Rp10.000 per hari per alat)
DROP FUNCTION IF EXISTS fn_hitung_denda;
DROP TRIGGER IF EXISTS trg_cek_stok;
DROP TRIGGER IF EXISTS trg_log_status;
DROP PROCEDURE IF EXISTS sp_setujui_peminjaman;
DROP PROCEDURE IF EXISTS sp_kembalikan_alat;
DROP PROCEDURE IF EXISTS sp_periksa_pengembalian;
DROP PROCEDURE IF EXISTS sp_konfirmasi_ganti;

DELIMITER //

-- FUNCTION: denda = hari terlambat x Rp10.000 x jumlah alat
CREATE FUNCTION fn_hitung_denda(p_tempo DATE, p_kembali DATE, p_jumlah INT)
RETURNS DECIMAL(12,2) DETERMINISTIC
BEGIN
  RETURN GREATEST(DATEDIFF(p_kembali, p_tempo), 0) * 10000 * p_jumlah;
END//

-- TRIGGER: pengajuan tidak boleh melebihi stok tersedia
CREATE TRIGGER trg_cek_stok BEFORE INSERT ON peminjaman
FOR EACH ROW
BEGIN
  IF NEW.jumlah > (SELECT stok_tersedia FROM alat WHERE id_alat = NEW.id_alat) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stok tidak mencukupi';
  END IF;
END//

-- Log status peminjaman dicatat lewat PHP (fungsi catat), bukan trigger, supaya pelaku dan keterangannya lengkap

-- PROCEDURE 1 (petugas): setujui peminjaman, stok dikurangi
CREATE PROCEDURE sp_setujui_peminjaman(IN p_id INT, IN p_petugas INT)
BEGIN
  DECLARE v_alat INT; DECLARE v_jml INT; DECLARE v_stok INT; DECLARE v_status VARCHAR(20);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
  SELECT id_alat, jumlah, status INTO v_alat, v_jml, v_status
    FROM peminjaman WHERE id_peminjaman = p_id FOR UPDATE;
  IF v_status IS NULL OR v_status <> 'menunggu' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pengajuan sudah diproses atau tidak ditemukan';
  END IF;
  SELECT stok_tersedia INTO v_stok FROM alat WHERE id_alat = v_alat FOR UPDATE;
  IF v_stok < v_jml THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stok tidak mencukupi';
  END IF;
  UPDATE alat SET stok_tersedia = stok_tersedia - v_jml WHERE id_alat = v_alat;
  UPDATE peminjaman SET status = 'dipinjam', id_petugas = p_petugas WHERE id_peminjaman = p_id;
  COMMIT;
END//

-- PROCEDURE 2 (peminjam/admin): kembalikan alat, hitung denda, kirim pesan ke peminjam jika terlambat
CREATE PROCEDURE sp_kembalikan_alat(IN p_id INT, IN p_user INT)
BEGIN
  DECLARE v_jml INT; DECLARE v_tempo DATE; DECLARE v_status VARCHAR(20); DECLARE v_pemilik INT;
  DECLARE v_nama VARCHAR(100); DECLARE v_telat INT; DECLARE v_denda DECIMAL(12,2);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
  SELECT p.jumlah, p.tanggal_jatuh_tempo, p.status, p.id_user, a.nama_alat
    INTO v_jml, v_tempo, v_status, v_pemilik, v_nama
    FROM peminjaman p JOIN alat a ON a.id_alat = p.id_alat
    WHERE p.id_peminjaman = p_id FOR UPDATE;
  IF v_pemilik IS NULL OR v_pemilik <> p_user THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Peminjaman tidak ditemukan atau bukan milik Anda';
  END IF;
  IF v_status <> 'dipinjam' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transaksi tidak aktif';
  END IF;
  SET v_telat = GREATEST(DATEDIFF(CURDATE(), v_tempo), 0);
  SET v_denda = fn_hitung_denda(v_tempo, CURDATE(), v_jml);
  INSERT INTO pengembalian (id_peminjaman, tanggal_kembali, terlambat_hari, total_denda)
  VALUES (p_id, CURDATE(), v_telat, v_denda);
  UPDATE peminjaman SET status = 'dikembalikan' WHERE id_peminjaman = p_id;
  IF v_telat > 0 THEN
    INSERT INTO notifikasi (id_user, jenis, pesan) VALUES (v_pemilik, 'terlambat', LEFT(CONCAT(
      'Pengembalian PJM-', LPAD(p_id, 4, '0'), ' (', v_nama, ' x', v_jml, ') terlambat ', v_telat,
      ' hari dari jatuh tempo. Denda keterlambatan Rp', REPLACE(FORMAT(v_denda, 0), ',', '.'),
      '. Mohon mengembalikan tepat waktu pada peminjaman berikutnya.'), 255));
  END IF;
  COMMIT;
END//

-- PROCEDURE 3 (petugas): periksa kondisi alat.
-- Alat yang baik langsung kembali ke stok. Alat rusak/hilang (sebanyak p_jumlah_masalah unit)
-- TIDAK kembali ke stok sampai peminjam menggantinya (lihat sp_konfirmasi_ganti).
CREATE PROCEDURE sp_periksa_pengembalian(IN p_id INT, IN p_kondisi VARCHAR(10), IN p_petugas INT, IN p_catatan TEXT, IN p_jumlah_masalah INT)
BEGIN
  DECLARE v_alat INT; DECLARE v_jml INT; DECLARE v_kondisi VARCHAR(20); DECLARE v_user INT;
  DECLARE v_nama VARCHAR(100); DECLARE v_n INT; DECLARE v_pinjam INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF p_kondisi NOT IN ('baik','rusak','hilang') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kondisi tidak valid';
  END IF;

  START TRANSACTION;
  SELECT p.id_alat, p.jumlah, r.kondisi, p.id_user, a.nama_alat, p.id_peminjaman
    INTO v_alat, v_jml, v_kondisi, v_user, v_nama, v_pinjam
    FROM pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman
    JOIN alat a ON a.id_alat = p.id_alat
    WHERE r.id_pengembalian = p_id FOR UPDATE;
  IF v_kondisi IS NULL OR v_kondisi <> 'belum_diperiksa' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pengembalian sudah diperiksa atau tidak ditemukan';
  END IF;

  IF p_kondisi = 'baik' THEN
    SET v_n = 0;
  ELSE
    SET v_n = IFNULL(p_jumlah_masalah, 0);
    IF v_n < 1 OR v_n > v_jml THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jumlah alat rusak/hilang harus 1 sampai jumlah yang dipinjam';
    END IF;
  END IF;

  -- unit yang baik kembali ke stok, unit rusak/hilang menunggu diganti
  UPDATE alat SET stok_tersedia = stok_tersedia + (v_jml - v_n) WHERE id_alat = v_alat;
  UPDATE pengembalian
     SET kondisi = p_kondisi, id_petugas = p_petugas, catatan = p_catatan,
         jumlah_bermasalah = v_n, jumlah_diganti = 0, tanggal_ganti = NULL,
         status_ganti = IF(v_n = 0, 'tidak_perlu', 'belum')
   WHERE id_pengembalian = p_id;

  IF v_n > 0 THEN
    INSERT INTO notifikasi (id_user, jenis, pesan) VALUES (v_user, 'ganti', LEFT(CONCAT(
      v_nama, ' (PJM-', LPAD(v_pinjam, 4, '0'), ') dinyatakan ',
      p_kondisi, ' sebanyak ', v_n, ' unit. Mohon diganti dengan alat yang sama dan serahkan ke petugas lab.'), 255));
  END IF;
  COMMIT;
END//

-- PROCEDURE 4 (petugas/admin): terima alat pengganti sebanyak p_jumlah unit.
-- Stok bertambah sesuai jumlah yang diganti. Status "sudah diganti" setelah semua unit diganti.
CREATE PROCEDURE sp_konfirmasi_ganti(IN p_id INT, IN p_petugas INT, IN p_jumlah INT)
BEGIN
  DECLARE v_alat INT; DECLARE v_masalah INT; DECLARE v_diganti INT; DECLARE v_ganti VARCHAR(20);
  DECLARE v_user INT; DECLARE v_nama VARCHAR(100); DECLARE v_sisa INT; DECLARE v_pinjam INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
  SELECT p.id_alat, r.jumlah_bermasalah, r.jumlah_diganti, r.status_ganti, p.id_user, a.nama_alat, p.id_peminjaman
    INTO v_alat, v_masalah, v_diganti, v_ganti, v_user, v_nama, v_pinjam
    FROM pengembalian r JOIN peminjaman p ON p.id_peminjaman = r.id_peminjaman
    JOIN alat a ON a.id_alat = p.id_alat
    WHERE r.id_pengembalian = p_id FOR UPDATE;
  IF v_ganti IS NULL OR v_ganti <> 'belum' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tidak ada penggantian yang menunggu';
  END IF;
  SET v_sisa = v_masalah - v_diganti;
  IF p_jumlah IS NULL OR p_jumlah < 1 OR p_jumlah > v_sisa THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jumlah yang diganti harus 1 sampai sisa yang belum diganti';
  END IF;

  UPDATE alat SET stok_tersedia = stok_tersedia + p_jumlah WHERE id_alat = v_alat;
  UPDATE pengembalian
     SET jumlah_diganti = v_diganti + p_jumlah, id_petugas = p_petugas,
         status_ganti = IF(p_jumlah = v_sisa, 'sudah', 'belum'),
         tanggal_ganti = IF(p_jumlah = v_sisa, CURDATE(), NULL)
   WHERE id_pengembalian = p_id;

  INSERT INTO notifikasi (id_user, jenis, pesan) VALUES (v_user, 'info', LEFT(CONCAT(
    'Penggantian ', v_nama, ' (PJM-', LPAD(v_pinjam, 4, '0'), ') diterima ', p_jumlah, ' unit. ',
    IF(p_jumlah = v_sisa, 'Semua alat sudah diganti, terima kasih.', CONCAT('Sisa yang belum diganti: ', v_sisa - p_jumlah, ' unit.'))), 255));
  COMMIT;
END//

DELIMITER ;
