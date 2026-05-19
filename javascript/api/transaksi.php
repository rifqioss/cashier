<?php
// =============================================
//  api/transaksi.php
// =============================================
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// ---------------------------
// POST: Simpan transaksi baru
// ---------------------------
if ($action === 'simpan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = requireLogin();
    $body = getBody();
    $items       = $body['items'] ?? [];
    $metode_bayar= $body['metode_bayar'] ?? 'cash';
    if (empty($items)) jsonOut(['ok'=>false,'msg'=>'Keranjang kosong']);

    $db = getDB();
    $db->beginTransaction();
    try {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (int)$item['harga'] * (int)$item['qty'];
        }
        $ppn   = round($subtotal * 0.12);
        $total = $subtotal + $ppn;
        $no_txn = 'TXN' . date('Ymd') . strtoupper(substr(uniqid(), -5));

        $stmt = $db->prepare('INSERT INTO transaksi (no_txn, user_id, subtotal, ppn, total, metode_bayar) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$no_txn, $user['id'], $subtotal, $ppn, $total, $metode_bayar]);
        $txn_id = $db->lastInsertId();

        foreach ($items as $item) {
            $produk_id  = (int)$item['produk_id'];
            $qty        = (int)$item['qty'];
            $harga      = (int)$item['harga'];
            $nama_produk= $item['nama'];
            $sub        = $harga * $qty;

            $db->prepare('INSERT INTO transaksi_detail (transaksi_id, produk_id, nama_produk, harga, qty, subtotal) VALUES (?,?,?,?,?,?)')
               ->execute([$txn_id, $produk_id, $nama_produk, $harga, $qty, $sub]);

            // kurangi stok
            $db->prepare('UPDATE produk SET stok = GREATEST(0, stok - ?) WHERE id=?')
               ->execute([$qty, $produk_id]);
        }

        $db->commit();
        jsonOut(['ok'=>true, 'msg'=>'Transaksi berhasil', 'no_txn'=>$no_txn, 'subtotal'=>$subtotal, 'ppn'=>$ppn, 'total'=>$total]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonOut(['ok'=>false,'msg'=>'Gagal simpan: '.$e->getMessage()], 500);
    }
}

// ---------------------------
// GET: Riwayat transaksi
// ---------------------------
if ($action === 'list') {
    requireAdmin();
    $db   = getDB();
    $limit= (int)($_GET['limit'] ?? 50);
    $rows = $db->prepare(
        'SELECT t.*, u.nama AS kasir FROM transaksi t
         LEFT JOIN users u ON u.id = t.user_id
         ORDER BY t.created_at DESC LIMIT ?'
    );
    $rows->execute([$limit]);
    jsonOut(['ok'=>true,'data'=>$rows->fetchAll()]);
}

// ---------------------------
// GET: Detail transaksi
// ---------------------------
if ($action === 'detail') {
    requireLogin();
    $id  = (int)($_GET['id'] ?? 0);
    $db  = getDB();
    $txn = $db->prepare('SELECT t.*, u.nama AS kasir FROM transaksi t LEFT JOIN users u ON u.id=t.user_id WHERE t.id=?');
    $txn->execute([$id]);
    $header = $txn->fetch();
    if (!$header) jsonOut(['ok'=>false,'msg'=>'Transaksi tidak ditemukan'],404);
    $det = $db->prepare('SELECT * FROM transaksi_detail WHERE transaksi_id=?');
    $det->execute([$id]);
    jsonOut(['ok'=>true,'data'=>['header'=>$header,'items'=>$det->fetchAll()]]);
}

jsonOut(['ok'=>false,'msg'=>'Action tidak dikenali'],400);
