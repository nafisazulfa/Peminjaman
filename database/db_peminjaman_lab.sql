-- =====================================================
-- Database Aplikasi Peminjaman Alat Lab (BARU, data contoh sedikit)
-- PERHATIAN: file ini MENGHAPUS database db_peminjaman_lab yang lama lalu membuatnya ulang.
-- Import lewat phpMyAdmin (tab Import). Butuh MySQL 8.0.16+ / MariaDB 10.2.1+ agar CHECK aktif.
-- =====================================================
DROP DATABASE IF EXISTS db_peminjaman_lab;
CREATE DATABASE db_peminjaman_lab CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_peminjaman_lab;

-- ---------------- TABEL ----------------
CREATE TABLE users (
  id_user INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NULL,            -- semua akun login pakai password (min. 5 karakter)
  role ENUM('admin','petugas','peminjam') NOT NULL,
  no_hp VARCHAR(20),
  jenis ENUM('siswa','guru') NULL,
  nomor_induk VARCHAR(25) NULL UNIQUE,
  kelas VARCHAR(30) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE kategori (
  id_kategori INT AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(100) NOT NULL UNIQUE,
  deskripsi TEXT
);

CREATE TABLE alat (
  id_alat INT AUTO_INCREMENT PRIMARY KEY,
  id_kategori INT NOT NULL,
  kode_alat VARCHAR(20) NOT NULL UNIQUE,
  nama_alat VARCHAR(100) NOT NULL,
  stok_total INT NOT NULL DEFAULT 0,
  stok_tersedia INT NOT NULL DEFAULT 0,
  lokasi VARCHAR(100),
  deskripsi TEXT,
  gambar VARCHAR(255) NULL,
  FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori),
  CHECK (stok_tersedia >= 0 AND stok_tersedia <= stok_total)
);

CREATE TABLE peminjaman (
  id_peminjaman INT AUTO_INCREMENT PRIMARY KEY,
  id_user INT NOT NULL,
  id_alat INT NOT NULL,
  jumlah INT NOT NULL,
  tanggal_pinjam DATE NOT NULL,
  tanggal_jatuh_tempo DATE NOT NULL,
  keperluan TEXT,
  status ENUM('menunggu','dipinjam','ditolak','dikembalikan') NOT NULL DEFAULT 'menunggu',
  id_petugas INT NULL,
  alasan_tolak VARCHAR(255) NULL,
  FOREIGN KEY (id_user) REFERENCES users(id_user),
  FOREIGN KEY (id_alat) REFERENCES alat(id_alat),
  FOREIGN KEY (id_petugas) REFERENCES users(id_user),
  CHECK (jumlah > 0),
  CHECK (tanggal_jatuh_tempo >= tanggal_pinjam)
);

CREATE TABLE pengembalian (
  id_pengembalian INT AUTO_INCREMENT PRIMARY KEY,
  id_peminjaman INT NOT NULL UNIQUE,
  tanggal_kembali DATE NOT NULL,
  terlambat_hari INT NOT NULL DEFAULT 0,
  total_denda DECIMAL(12,2) NOT NULL DEFAULT 0,
  kondisi ENUM('belum_diperiksa','baik','rusak','hilang') NOT NULL DEFAULT 'belum_diperiksa',
  status_ganti ENUM('tidak_perlu','belum','sudah') NOT NULL DEFAULT 'tidak_perlu',
  jumlah_bermasalah INT NOT NULL DEFAULT 0,   -- unit yang rusak/hilang
  jumlah_diganti INT NOT NULL DEFAULT 0,      -- unit yang sudah diganti peminjam
  tanggal_ganti DATE NULL,                    -- terisi saat semua unit sudah diganti
  denda_dibayar DATETIME NULL,                -- terisi saat denda sudah dibayar (NULL + total_denda > 0 = belum bayar)
  id_petugas INT NULL,
  catatan TEXT,
  FOREIGN KEY (id_peminjaman) REFERENCES peminjaman(id_peminjaman),
  FOREIGN KEY (id_petugas) REFERENCES users(id_user)
);

CREATE TABLE log_aktivitas (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  id_user INT NULL,
  aktivitas VARCHAR(255) NOT NULL,
  waktu DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE SET NULL
);

CREATE TABLE notifikasi (
  id_notif INT AUTO_INCREMENT PRIMARY KEY,
  id_user INT NOT NULL,
  jenis ENUM('terlambat','ganti','info') NOT NULL DEFAULT 'info',
  pesan VARCHAR(255) NOT NULL,
  dibaca TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
);

CREATE INDEX idx_pinjam_status ON peminjaman(status, tanggal_jatuh_tempo);
CREATE INDEX idx_pinjam_user ON peminjaman(id_user);
CREATE INDEX idx_notif_user ON notifikasi(id_user, dibaca);

-- ---------------- DATA AWAL (sedikit) ----------------
-- Semua akun login pakai password (min. 5 karakter). Hash MD5 di data awal ini otomatis
-- diganti ke hash yang lebih aman saat pertama kali login.
--   Admin   : username admin    / password admin123
--   Petugas : username petugas1 / password petugas123
--   Guru    : NIP  198507122010012005 / password guru123
--   Siswa   : NISN 2324001 (Budi) dan 2526003 (Andi) / password siswa123
INSERT INTO users (nama, username, password, role, no_hp, jenis, nomor_induk, kelas) VALUES
('Administrator Sekolah', 'admin',               MD5('admin123'),   'admin',    '081234567801', NULL,    NULL,                 NULL),
('Pak Hendra',            'petugas1',            MD5('petugas123'), 'petugas',  '081234567802', NULL,    NULL,                 NULL),
('Bu Rina Wulandari',     '198507122010012005',  MD5('guru123'),    'peminjam', '081234567804', 'guru',  '198507122010012005', NULL),
('Budi Santoso',          '2324001',             MD5('siswa123'),   'peminjam', '081234567806', 'siswa', '2324001',            'XII RPL 1'),
('Andi Pratama',          '2526003',             MD5('siswa123'),   'peminjam', '081234567808', 'siswa', '2526003',            'X RPL 1');

INSERT INTO kategori (nama_kategori, deskripsi) VALUES
('Komputer', 'Laptop dan komputer'),
('Periferal', 'Perangkat pendukung komputer'),
('Jaringan', 'Peralatan praktik jaringan'),
('Mikrokontroler', 'Papan mikrokontroler dan elektronika');

INSERT INTO alat (id_kategori, kode_alat, nama_alat, stok_total, stok_tersedia, lokasi, deskripsi, gambar) VALUES
(1, 'ALT001', 'Laptop',      5,  5, 'Lab Komputer',    'Laptop praktik pemrograman',       'laptop.svg'),
(2, 'ALT002', 'Mouse',      10, 10, 'Lab Komputer',    'Mouse USB',                        'mouse.svg'),
(3, 'ALT003', 'Router',      3,  3, 'Lab Informatika', 'Router praktik jaringan',          'router.svg'),
(4, 'ALT004', 'Arduino Uno', 4,  4, 'Lab Informatika', 'Papan mikrokontroler Arduino Uno', 'arduino.svg');

-- ---------------- FUNCTION, TRIGGER, PROCEDURE ----------------
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

-- ---------------- DATA CONTOH TRANSAKSI (boleh dihapus) ----------------
-- id user: 1 admin, 2 petugas, 3 guru, 4-5 siswa. id alat: 1 Laptop, 2 Mouse, 3 Router, 4 Arduino.
INSERT INTO peminjaman (id_peminjaman, id_user, id_alat, jumlah, tanggal_pinjam, tanggal_jatuh_tempo, keperluan, status, id_petugas, alasan_tolak) VALUES
(1, 4, 1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Praktik pemrograman web kelas XII RPL 1', 'dipinjam', 2, NULL),
(2, 5, 3, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Praktik konfigurasi jaringan', 'dipinjam', 2, NULL),
(3, 4, 4, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Proyek mikrokontroler kelompok', 'menunggu', NULL, NULL),
(4, 3, 2, 2, DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Praktik perakitan komputer', 'dikembalikan', 2, NULL);

INSERT INTO pengembalian (id_peminjaman, tanggal_kembali, terlambat_hari, total_denda, kondisi, status_ganti, jumlah_bermasalah, jumlah_diganti, tanggal_ganti, denda_dibayar, id_petugas, catatan) VALUES
(4, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 0, 0, 'baik', 'tidak_perlu', 0, 0, NULL, NULL, 2, 'Semua mouse lengkap');

-- stok menyesuaikan transaksi yang sedang dipinjam
UPDATE alat SET stok_tersedia = 4 WHERE id_alat = 1;
UPDATE alat SET stok_tersedia = 2 WHERE id_alat = 3;

INSERT INTO log_aktivitas (id_user, aktivitas, waktu) VALUES
(2, 'Login', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 'Mengajukan peminjaman PJM-0001 - Laptop x1', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 'Menyetujui peminjaman PJM-0001 - Laptop x1', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 'Mengajukan peminjaman PJM-0003 - Arduino Uno x2', NOW());
