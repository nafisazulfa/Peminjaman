-- Jalankan SEKALI di database yang sudah berjalan (phpMyAdmin > SQL).
-- Akun siswa/guru lama belum punya password. Password awal diset = NISN/NIP agar masih bisa login,
-- lalu admin sebaiknya mengganti lewat Data Peminjam > Edit (kolom Password).
UPDATE users SET password = MD5(nomor_induk)
 WHERE role = 'peminjam' AND password IS NULL AND nomor_induk IS NOT NULL AND nomor_induk <> '';
