<?php
// =============================================
//  api/produk.php — CRUD Produk & Kategori
//  Endpoint: api/produk.php?action=...
// =============================================
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// ---------------------------
// GET: Daftar semua produk
// ---------------------------
if ($action === 'list') {
    requireLogin();
    $db = getDB();
    $q  = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt = $db->prepare(
        'SELECT p.*, k.nama AS kategori_nama
         FROM produk p
         LEFT JOIN kategori k ON k.id = p.kategori_id
         WHERE p.aktif = 1
           AND (p.nama LIKE ? OR p.barcode LIKE ? OR k.nama LIKE ?)
         ORDER BY p.nama'
    );
    $stmt->execute([$q, $q, $q]);
    $rows = $stmt->fetchAll();
    jsonOut(['ok' => true, 'data' => $rows]);
}

// ---------------------------
// GET: Cari produk by barcode (untuk scan)
// ---------------------------
if ($action === 'scan') {
    requireLogin();
    $barcode = trim($_GET['barcode'] ?? '');
    if (!$barcode) jsonOut(['ok' => false, 'msg' => 'Barcode kosong']);
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT p.*, k.nama AS kategori_nama
         FROM produk p
         LEFT JOIN kategori k ON k.id = p.kategori_id
         WHERE p.barcode = ? AND p.aktif = 1 LIMIT 1'
    );
    $stmt->execute([$barcode]);
    $row  = $stmt->fetch();
    if (!$row) jsonOut(['ok' => false, 'msg' => 'Produk tidak ditemukan'], 404);
    jsonOut(['ok' => true, 'data' => $row]);
}

// ---------------------------
// GET: Stok menipis
// ---------------------------
if ($action === 'lowstock') {
    requireLogin();
    $db   = getDB();
    $rows = $db->query(
        'SELECT p.*, k.nama AS kategori_nama
         FROM produk p
         LEFT JOIN kategori k ON k.id = p.kategori_id
         WHERE p.aktif = 1 AND p.stok <= p.stok_min
         ORDER BY p.stok ASC'
    )->fetchAll();
    jsonOut(['ok' => true, 'data' => $rows]);
}

// ---------------------------
// POST: Tambah produk (admin)
// ---------------------------
if ($action === 'tambah' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $body       = getBody();
    $nama       = trim($body['nama'] ?? '');
    $harga      = (int)($body['harga'] ?? 0);
    $stok       = (int)($body['stok'] ?? 0);
    $stok_min   = (int)($body['stok_min'] ?? 5);
    $kategori_id= (int)($body['kategori_id'] ?? 1);
    $barcode    = trim($body['barcode'] ?? '');

    if (!$nama || $harga < 0) jsonOut(['ok' => false, 'msg' => 'Nama dan harga wajib diisi']);

    // Generate barcode kalau kosong
    if (!$barcode) {
        $db    = getDB();
        $last  = $db->query('SELECT MAX(id) AS mx FROM produk')->fetch()['mx'] ?? 0;
        $barcode = '8990000' . str_pad((int)$last + 1, 6, '0', STR_PAD_LEFT);
    }

    $db = getDB();
    try {
        $stmt = $db->prepare(
            'INSERT INTO produk (barcode, nama, harga, stok, stok_min, kategori_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$barcode, $nama, $harga, $stok, $stok_min, $kategori_id]);
        jsonOut(['ok' => true, 'msg' => 'Produk ditambahkan', 'id' => $db->lastInsertId(), 'barcode' => $barcode]);
    } catch (PDOException $e) {
        jsonOut(['ok' => false, 'msg' => 'Barcode sudah dipakai'], 409);
    }
}

// ---------------------------
// POST: Edit produk (admin)
// ---------------------------
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $body       = getBody();
    $id         = (int)($body['id'] ?? 0);
    $nama       = trim($body['nama'] ?? '');
    $harga      = (int)($body['harga'] ?? 0);
    $stok_min   = (int)($body['stok_min'] ?? 5);
    $kategori_id= (int)($body['kategori_id'] ?? 1);
    $barcode    = trim($body['barcode'] ?? '');

    if (!$id || !$nama) jsonOut(['ok' => false, 'msg' => 'Data tidak lengkap']);

    $db   = getDB();
    $stmt = $db->prepare(
        'UPDATE produk SET nama=?, harga=?, stok_min=?, kategori_id=?, barcode=? WHERE id=?'
    );
    $stmt->execute([$nama, $harga, $stok_min, $kategori_id, $barcode, $id]);
    jsonOut(['ok' => true, 'msg' => 'Produk diupdate']);
}

// ---------------------------
// POST: Hapus produk (admin)
// ---------------------------
if ($action === 'hapus' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $body = getBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonOut(['ok' => false, 'msg' => 'ID tidak valid']);
    $db   = getDB();
    $db->prepare('UPDATE produk SET aktif=0 WHERE id=?')->execute([$id]);
    jsonOut(['ok' => true, 'msg' => 'Produk dihapus']);
}

// ---------------------------
// GET: Daftar kategori
// ---------------------------
if ($action === 'kategori') {
    requireLogin();
    $db   = getDB();
    $rows = $db->query('SELECT * FROM kategori ORDER BY nama')->fetchAll();
    jsonOut(['ok' => true, 'data' => $rows]);
}

// ---------------------------
// POST: Tambah kategori (admin)
// ---------------------------
if ($action === 'tambah_kategori' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $body = getBody();
    $nama = trim($body['nama'] ?? '');
    if (!$nama) jsonOut(['ok' => false, 'msg' => 'Nama kategori kosong']);
    $db   = getDB();
    try {
        $db->prepare('INSERT INTO kategori (nama) VALUES (?)')->execute([$nama]);
        jsonOut(['ok' => true, 'msg' => 'Kategori ditambahkan', 'id' => $db->lastInsertId()]);
    } catch (PDOException $e) {
        jsonOut(['ok' => false, 'msg' => 'Kategori sudah ada'], 409);
    }
}

jsonOut(['ok' => false, 'msg' => 'Action tidak dikenali'], 400);
