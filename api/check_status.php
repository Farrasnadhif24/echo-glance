<?php
/**
 * API: Check User Status
 * GET - Check verification status of the logged-in user
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPILogin();

try {
    $db = getDB();
    $userId = $_SESSION['user_id'];

    $stmt = $db->prepare('SELECT status_verifikasi, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonError('User tidak ditemukan.', 404);
    }

    // Update session if status changed
    if ($user['status_verifikasi'] !== $_SESSION['status_verifikasi']) {
        $_SESSION['status_verifikasi'] = $user['status_verifikasi'];
    }

    // Determine redirect URL if approved
    $redirect = '';
    if ($user['status_verifikasi'] === 'approved') {
        switch ($user['role']) {
            case 'keluarga':
                $redirect = 'dashboard-keluarga.php';
                break;
            case 'tenaga_medis':
            case 'admin':
                $redirect = 'dashboard-medis.php';
                break;
        }
    }

    jsonResponse([
        'success'  => true,
        'status'   => $user['status_verifikasi'],
        'redirect' => $redirect,
    ]);

} catch (PDOException $e) {
    jsonError('Gagal cek status: ' . $e->getMessage(), 500);
}
