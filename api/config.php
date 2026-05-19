<?php
// =============================================
//  api/config.php — Konfigurasi Database
// =============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // user MySQL XAMPP default
define('DB_PASS', '');           // password MySQL XAMPP default (kosong)
define('DB_NAME', 'rifqicashier');

// Buat koneksi PDO
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'msg' => 'Koneksi DB gagal: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Helper: kirim JSON response
function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    echo json_encode($data);
    exit;
}

// Handle preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    exit;
}

// Helper: ambil body JSON dari request
function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

// Helper: cek session login
session_start();
function requireLogin(): array {
    if (empty($_SESSION['user'])) {
        jsonOut(['ok' => false, 'msg' => 'Belum login'], 401);
    }
    return $_SESSION['user'];
}
function requireAdmin(): array {
    $u = requireLogin();
    if ($u['role'] !== 'admin') {
        jsonOut(['ok' => false, 'msg' => 'Hanya admin yang bisa akses'], 403);
    }
    return $u;
}
