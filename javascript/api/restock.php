<?php
// =============================================
//  api/restock.php
// =============================================
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// ---------------------------
// POST: Proses restock
// ---------------------------
if ($action === 'proses' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = requireAdmin();
    $body = getBody();
    $produk_id = (int)($body['produk_id'] ?? 0);
    $qty       = (int)($body['qty'] ?? 0);
    $supplier  = trim($body['supplier'] ?? '');
    $catatan   = trim($body['catatan'] ?? '');

    if (!$produk_id || $qty < 1) jsonOut(['ok'=>false,'msg'=>'Data tidak lengkap']);

    $db   = getDB();
    $prod = $db->prepare('SELECT * FROM produk WHERE id=? AND aktif=1');
    $prod->execute([$produk_id]);
    $p = $prod->fetch();
    if (!$p) jsonOut(['ok'=>false,'msg'=>'Produk tidak ditemukan'],404);

    $stok_before = (int)$p['stok'];
    $stok_after  = $stok_before + $qty;

    $db->prepare('UPDATE produk SET stok=? WHERE id=?')->execute([$stok_after, $produk_id]);
    $db->prepare('INSERT INTO restock_log (produk_id, user_id, qty_tambah, stok_before, stok_after, supplier, catatan) VALUES (?,?,?,?,?,?,?)')
       ->execute([$produk_id, $user['id'], $qty, $stok_before, $stok_after, $supplier, $catatan]);

    jsonOut(['ok'=>true,'msg'=>"Stok {$p['nama']} +{$qty} → {$stok_after}",'stok_after'=>$stok_after]);
}

// ---------------------------
// GET: Riwayat restock
// ---------------------------
if ($action === 'log') {
    requireAdmin();
    $db   = getDB();
    $limit= (int)($_GET['limit'] ?? 50);
    $stmt = $db->prepare(
        'SELECT r.*, p.nama AS produk_nama, u.nama AS admin_nama
         FROM restock_log r
         LEFT JOIN produk p ON p.id = r.produk_id
         LEFT JOIN users  u ON u.id = r.user_id
         ORDER BY r.created_at DESC LIMIT ?'
    );
    $stmt->execute([$limit]);
    jsonOut(['ok'=>true,'data'=>$stmt->fetchAll()]);
}

jsonOut(['ok'=>false,'msg'=>'Action tidak dikenali'],400);
