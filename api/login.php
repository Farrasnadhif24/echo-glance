<?php
/**
 * API: Login
 * POST - Authenticate user with email & password
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

initSession();

// If already logged in, redirect
if (isLoggedIn()) {
    $user = getCurrentUser();
    $redirect = getRedirectForRole($user['role'], $user['status_verifikasi']);
    header('Location: ' . $redirect);
    exit;
}

requirePOST();

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    // Redirect back with error
    header('Location: ../index.php?error=' . urlencode('Email dan password harus diisi.'));
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        header('Location: ../index.php?error=' . urlencode('Email atau password salah.'));
        exit;
    }

    // Set session
    setUserSession($user);

    // Redirect based on role & status
    $redirect = getRedirectForRole($user['role'], $user['status_verifikasi']);
    session_write_close();
    header('Location: ' . $redirect);
    exit;

} catch (PDOException $e) {
    header('Location: ../index.php?error=' . urlencode('Terjadi kesalahan server.'));
    exit;
}

/**
 * Determine redirect URL based on role and verification status
 */
function getRedirectForRole(string $role, string $status): string {
    if ($status === 'pending') {
        return '../pending.php';
    }
    if ($status === 'rejected') {
        return '../index.php?error=' . urlencode('Akun Anda telah ditolak. Hubungi admin.');
    }

    switch ($role) {
        case 'keluarga':
            return '../dashboard-keluarga.php';
        case 'tenaga_medis':
        case 'admin':
            return '../dashboard-medis.php';
        default:
            return '../index.php';
    }
}
