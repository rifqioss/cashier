<?php
// =============================================
//  api/laporan.php
// =============================================
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// ---------------------------
// GET: Ringkasan dashboard
// ---------------------------
if ($action === 'ringkasan') {
    requireAdmin();
    $db = getDB();

    $total_trx  = $db->query('SELECT COUNT(*) FROM transaksi')->fetchColumn();
    $total_pend = $db->query('SELECT COALESCE(SUM(total),0) FROM transaksi')->fetchColumn();
    $total_item = $db->query('SELECT COALESCE(SUM(qty),0) FROM transaksi_detail')->fetchColumn();
    $total_ppn  = $db->query('SELECT COALESCE(SUM(ppn),0) FROM transaksi')->fetchColumn();
    $stok_habis = $db->query('SELECT COUNT(*) FROM produk WHERE stok=0 AND aktif=1')->fetchColumn();
    $stok_tipis = $db->query('SELECT COUNT(*) FROM produk WHERE stok<=stok_min AND stok>0 AND aktif=1')->fetchColumn();

    jsonOut(['ok'=>true,'data'=>[
        'total_trx'  => (int)$total_trx,
        'total_pend' => (int)$total_pend,
        'total_item' => (int)$total_item,
        'total_ppn'  => (int)$total_ppn,
        'stok_habis' => (int)$stok_habis,
        'stok_tipis' => (int)$stok_tipis,
    ]]);
}

// ---------------------------
// GET: Produk terlaris
// ---------------------------
if ($action === 'terlaris') {
    requireAdmin();
    $limit = (int)($_GET['limit'] ?? 10);
    $db    = getDB();
    $stmt  = $db->prepare(
        'SELECT d.nama_produk, SUM(d.qty) AS total_qty, SUM(d.subtotal) AS total_penjualan
         FROM transaksi_detail d
         GROUP BY d.nama_produk
         ORDER BY total_qty DESC
         LIMIT ?'
    );
    $stmt->execute([$limit]);
    jsonOut(['ok'=>true,'data'=>$stmt->fetchAll()]);
}

// ---------------------------
// GET: Penjualan per metode bayar
// ---------------------------
if ($action === 'per_metode') {
    requireAdmin();
    $db   = getDB();
    $rows = $db->query(
        'SELECT metode_bayar, COUNT(*) AS jumlah, SUM(total) AS total
         FROM transaksi GROUP BY metode_bayar ORDER BY total DESC'
    )->fetchAll();
    jsonOut(['ok'=>true,'data'=>$rows]);
}

// ---------------------------
// GET: Penjualan per hari (30 hari terakhir)
// ---------------------------
if ($action === 'per_hari') {
    requireAdmin();
    $db   = getDB();
    $rows = $db->query(
        'SELECT DATE(created_at) AS tgl, COUNT(*) AS jumlah_trx, SUM(total) AS total
         FROM transaksi
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at)
         ORDER BY tgl ASC'
    )->fetchAll();
    jsonOut(['ok'=>true,'data'=>$rows]);
}

jsonOut(['ok'=>false,'msg'=>'Action tidak dikenali'],400);
