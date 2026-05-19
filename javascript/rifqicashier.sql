-- =============================================
--  RifqiCashier - Database Schema
--  Import file ini di phpMyAdmin XAMPP
-- =============================================

CREATE DATABASE IF NOT EXISTS rifqicashier CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rifqicashier;

-- =============================================
-- TABEL USERS
-- =============================================
CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,        -- bcrypt hash
  nama       VARCHAR(100) NOT NULL,
  role       ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
  aktif      TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- TABEL KATEGORI
-- =============================================
CREATE TABLE IF NOT EXISTS kategori (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  nama  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- =============================================
-- TABEL PRODUK
-- =============================================
CREATE TABLE IF NOT EXISTS produk (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  barcode      VARCHAR(30)    NOT NULL UNIQUE,
  nama         VARCHAR(150)   NOT NULL,
  harga        DECIMAL(12,0)  NOT NULL DEFAULT 0,
  stok         INT            NOT NULL DEFAULT 0,
  stok_min     INT            NOT NULL DEFAULT 5,
  kategori_id  INT            NOT NULL DEFAULT 1,
  aktif        TINYINT(1)     NOT NULL DEFAULT 1,
  created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kategori_id) REFERENCES kategori(id)
) ENGINE=InnoDB;

-- =============================================
-- TABEL TRANSAKSI (HEADER)
-- =============================================
CREATE TABLE IF NOT EXISTS transaksi (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  no_txn       VARCHAR(30)    NOT NULL UNIQUE,
  user_id      INT            NOT NULL,
  subtotal     DECIMAL(14,0)  NOT NULL DEFAULT 0,
  ppn          DECIMAL(14,0)  NOT NULL DEFAULT 0,
  total        DECIMAL(14,0)  NOT NULL DEFAULT 0,
  metode_bayar ENUM('cash','debit','gopay','dana','shopeepay') NOT NULL DEFAULT 'cash',
  created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- =============================================
-- TABEL DETAIL TRANSAKSI
-- =============================================
CREATE TABLE IF NOT EXISTS transaksi_detail (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  transaksi_id  INT           NOT NULL,
  produk_id     INT           NOT NULL,
  nama_produk   VARCHAR(150)  NOT NULL,
  harga         DECIMAL(12,0) NOT NULL,
  qty           INT           NOT NULL,
  subtotal      DECIMAL(14,0) NOT NULL,
  FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
  FOREIGN KEY (produk_id)    REFERENCES produk(id)
) ENGINE=InnoDB;

-- =============================================
-- TABEL RESTOCK LOG
-- =============================================
CREATE TABLE IF NOT EXISTS restock_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  produk_id   INT           NOT NULL,
  user_id     INT           NOT NULL,
  qty_tambah  INT           NOT NULL,
  stok_before INT           NOT NULL,
  stok_after  INT           NOT NULL,
  supplier    VARCHAR(200),
  catatan     TEXT,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (produk_id) REFERENCES produk(id),
  FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB;

-- =============================================
-- DATA AWAL: USERS
-- password = bcrypt dari: admin123 / kasir123 / rifqi123
-- =============================================
INSERT INTO users (username, password, nama, role) VALUES
('admin',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator',     'admin'),
('rifqi',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rifqi Al Muzhaky',  'admin'),
('kasir',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kasir Utama',       'kasir');
-- PENTING: password hash di atas = "password" (Laravel default hash)
-- Ganti dengan hash baru setelah install, atau jalankan script hash_password.php

-- =============================================
-- DATA AWAL: KATEGORI
-- =============================================
INSERT INTO kategori (nama) VALUES
('Alat Tulis'),
('Buku & Kertas'),
('Makanan & Minuman'),
('Elektronik'),
('Pakaian'),
('Lainnya');

-- =============================================
-- DATA AWAL: PRODUK
-- =============================================
INSERT INTO produk (barcode, nama, harga, stok, stok_min, kategori_id) VALUES
('8990000000001', 'Pensil 2B Faber',        3500,  48, 10, 1),
('8990000000002', 'Buku Tulis 38 Lbr',      5500,  60, 15, 2),
('8990000000003', 'Bolpoin Hitam',           4000,   7, 10, 1),
('8990000000004', 'Penggaris 30cm',          7500,  22,  8, 1),
('8990000000005', 'Tipe-X Koreksi',          6000,   3,  8, 1),
('8990000000006', 'Spidol Marker Hitam',     8500,  15,  5, 1),
('8990000000007', 'Kertas HVS A4 (rim)',    52000,  20,  5, 2),
('8990000000008', 'Air Mineral 600ml',       4000,   0, 12, 3);
