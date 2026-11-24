-- =====================================================================
-- database/mhs.sql  --  Database untuk API GraphQL (Pertemuan 9)
-- Cara pakai: phpMyAdmin -> tab Import -> pilih file ini -> Go
--
-- AMAN diimpor berulang kali: file ini TIDAK menghapus tabel yang sudah ada.
-- Jika Anda sudah punya database kampus_db dari Pertemuan 7, datanya tetap.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS kampus_db CHARACTER SET utf8mb4;
USE kampus_db;

-- Tabel data mahasiswa (sama seperti Pertemuan 6-7)
CREATE TABLE IF NOT EXISTS mhs (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nim        VARCHAR(12)  NOT NULL UNIQUE,
  name       VARCHAR(100) NOT NULL,
  major      VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel akun untuk login (sama seperti Pertemuan 7); password berupa HASH
CREATE TABLE IF NOT EXISTS users (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  username  VARCHAR(50)  NOT NULL UNIQUE,
  password  VARCHAR(255) NOT NULL,
  role      VARCHAR(10)  NOT NULL DEFAULT 'user'
);

-- Tabel log (sama seperti Pertemuan 7)
CREATE TABLE IF NOT EXISTS log_api (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  waktu    DATETIME     NOT NULL,
  method   VARCHAR(10)  NOT NULL,
  path     VARCHAR(255) NOT NULL,
  status   INT          NOT NULL,
  ip       VARCHAR(45)  NOT NULL,
  username VARCHAR(50)  NULL,
  pesan    VARCHAR(255) NULL
);

-- Data contoh. INSERT IGNORE = lewati baris yang NIM / username-nya sudah ada.
INSERT IGNORE INTO mhs (nim, name, major) VALUES
  ('23670001', 'Budi Santoso',   'Informatika'),
  ('23670002', 'Siti Aminah',    'Informatika'),
  ('23670003', 'Andi Pratama',   'Sistem Informasi'),
  ('23670004', 'Dewi Lestari',   'Sistem Informasi'),
  ('23670005', 'Rizky Maulana',  'Informatika');

-- Akun: admin / admin123 (role admin)  dan  budi / budi123 (role user)
INSERT IGNORE INTO users (username, password, role) VALUES
  ('admin', '$2y$10$pWQGUDarcWM74IF4ZYNR5u3kAqKJWaPrMjc1rXXq4w6/bsSZ7kzDK', 'admin'),
  ('budi',  '$2y$10$Zw8rAKVFTx5wmo5Pzg5nkuEg667G.E70mo.ep5e1WR8i67B8XLiLy', 'user');
