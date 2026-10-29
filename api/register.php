<?php
/**
 * API: Register
 * POST - Create new user account
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

initSession();

requirePOST();

$nama       = trim($_POST['nama'] ?? '');
$email      = trim($_POST['email'] ?? '');
$role       = $_POST['role'] ?? 'keluarga';
$strNumber  = trim($_POST['str_number'] ?? '');
$password   = $_POST['password'] ?? '';
$confirmPwd = $_POST['confirm_password'] ?? '';

// Validation
$errors = [];

if (empty($nama)) {
    $errors[] = 'Nama lengkap harus diisi.';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email tidak valid.';
}
if (!in_array($role, ['keluarga', 'tenaga_medis'])) {
    $errors[] = 'Role tidak valid.';
}
if ($role === 'tenaga_medis' && empty($strNumber)) {
    $errors[] = 'Nomor STR harus diisi untuk tenaga medis.';
}
if (strlen($password) < 6) {
    $errors[] = 'Password minimal 6 karakter.';
}
if ($password !== $confirmPwd) {
    $errors[] = 'Password tidak cocok.';
}

if (!empty($errors)) {
    $errorMsg = implode(' ', $errors);
    header('Location: ../register.php?error=' . urlencode($errorMsg) .
           '&nama=' . urlencode($nama) .
           '&email=' . urlencode($email) .
           '&role=' . urlencode($role) .
           '&str_number=' . urlencode($strNumber));
    exit;
}

try {
    $db = getDB();

    // Check if email already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        header('Location: ../register.php?error=' . urlencode('Email sudah terdaftar.') .
               '&nama=' . urlencode($nama) .
               '&role=' . urlencode($role));
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Determine status: keluarga → approved, tenaga_medis → pending
    $status = ($role === 'keluarga') ? 'approved' : 'pending';

    // Insert user
    $stmt = $db->prepare(
        'INSERT INTO users (nama, email, password, role, status_verifikasi, str_number) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        sanitize($nama),
        $email,
        $hashedPassword,
        $role,
        $status,
        $role === 'tenaga_medis' ? sanitize($strNumber) : null,
    ]);

    $userId = $db->lastInsertId();

    // Auto-login
    setUserSession([
        'id'                => $userId,
        'nama'              => $nama,
        'email'             => $email,
        'role'              => $role,
        'status_verifikasi' => $status,
    ]);

    // Redirect
    if ($status === 'pending') {
        header('Location: ../pending.php');
    } else {
        header('Location: ../register.php?success=1&role=' . urlencode($role));
    }
    exit;

} catch (PDOException $e) {
    header('Location: ../register.php?error=' . urlencode('Terjadi kesalahan server. Silakan coba lagi.'));
    exit;
}
