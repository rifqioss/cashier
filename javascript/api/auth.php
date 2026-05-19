<?php
// =============================================
//  api/auth.php — Login, Logout, Session
//  Endpoint: api/auth.php?action=...
// =============================================
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// ---------------------------
// POST: Login
// ---------------------------
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body     = getBody();
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $role     = $body['role'] ?? '';   // 'admin' atau 'kasir'

    if (!$username || !$password) {
        jsonOut(['ok' => false, 'msg' => 'Username dan password wajib diisi']);
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND aktif = 1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonOut(['ok' => false, 'msg' => 'Username atau password salah'], 401);
    }
    if ($role === 'admin' && $user['role'] !== 'admin') {
        jsonOut(['ok' => false, 'msg' => 'Akun ini bukan admin'], 403);
    }

    // Simpan session
    $_SESSION['user'] = [
        'id'       => $user['id'],
        'username' => $user['username'],
        'nama'     => $user['nama'],
        'role'     => $user['role'],
    ];

    jsonOut(['ok' => true, 'user' => $_SESSION['user']]);
}

// ---------------------------
// GET: Cek session
// ---------------------------
if ($action === 'check') {
    if (!empty($_SESSION['user'])) {
        jsonOut(['ok' => true, 'user' => $_SESSION['user']]);
    }
    jsonOut(['ok' => false, 'msg' => 'Belum login']);
}

// ---------------------------
// POST: Logout
// ---------------------------
if ($action === 'logout') {
    session_destroy();
    jsonOut(['ok' => true, 'msg' => 'Logout berhasil']);
}

// ---------------------------
// GET: Daftar semua user (admin only)
// ---------------------------
if ($action === 'list') {
    requireAdmin();
    $db   = getDB();
    $rows = $db->query('SELECT id, username, nama, role, aktif, created_at FROM users ORDER BY id')->fetchAll();
    jsonOut(['ok' => true, 'data' => $rows]);
}

// ---------------------------
// POST: Tambah user (admin only)
// ---------------------------
if ($action === 'tambah' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $body     = getBody();
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $nama     = trim($body['nama'] ?? '');
    $role     = $body['role'] ?? 'kasir';

    if (!$username || !$password || !$nama) {
        jsonOut(['ok' => false, 'msg' => 'Semua field wajib diisi']);
    }
    if (!in_array($role, ['admin', 'kasir'])) {
        jsonOut(['ok' => false, 'msg' => 'Role tidak valid']);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db   = getDB();
    try {
        $stmt = $db->prepare('INSERT INTO users (username, password, nama, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $hash, $nama, $role]);
        jsonOut(['ok' => true, 'msg' => 'User berhasil ditambahkan', 'id' => $db->lastInsertId()]);
    } catch (PDOException $e) {
        jsonOut(['ok' => false, 'msg' => 'Username sudah dipakai'], 409);
    }
}

// ---------------------------
// POST: Edit user (admin only)
// ---------------------------
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $body = getBody();
    $id   = (int)($body['id'] ?? 0);
    $nama = trim($body['nama'] ?? '');
    $role = $body['role'] ?? 'kasir';
    $aktif= isset($body['aktif']) ? (int)$body['aktif'] : 1;

    if (!$id || !$nama) jsonOut(['ok' => false, 'msg' => 'Data tidak lengkap']);

    $db   = getDB();
    $stmt = $db->prepare('UPDATE users SET nama=?, role=?, aktif=? WHERE id=?');
    $stmt->execute([$nama, $role, $aktif, $id]);

    // Ganti password kalau diisi
    if (!empty($body['password'])) {
        $hash = password_hash($body['password'], PASSWORD_DEFAULT);
        $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash, $id]);
    }
    jsonOut(['ok' => true, 'msg' => 'User diupdate']);
}

// ---------------------------
// POST: Hapus user (admin only)
// ---------------------------
if ($action === 'hapus' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $body = getBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonOut(['ok' => false, 'msg' => 'ID tidak valid']);
    $db   = getDB();
    $db->prepare('UPDATE users SET aktif=0 WHERE id=?')->execute([$id]);
    jsonOut(['ok' => true, 'msg' => 'User dinonaktifkan']);
}

jsonOut(['ok' => false, 'msg' => 'Action tidak dikenali'], 400);
