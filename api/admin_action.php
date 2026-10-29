<?php
/**
 * API: Admin Action
 * POST - Approve or reject a user account
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPIAdmin();
requirePOST();

$body   = getJsonBody();
$action = $body['action'] ?? '';
$userId = intval($body['user_id'] ?? 0);

if (!in_array($action, ['approve', 'reject'])) {
    jsonError('Aksi tidak valid. Gunakan "approve" atau "reject".');
}

if ($userId <= 0) {
    jsonError('User ID tidak valid.');
}

try {
    $db = getDB();

    // Check user exists and is pending
    $stmt = $db->prepare("SELECT id, nama, email FROM users WHERE id = ? AND status_verifikasi = 'pending' LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonError('User tidak ditemukan atau sudah diproses.', 404);
    }

    // Update status
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    $stmt = $db->prepare('UPDATE users SET status_verifikasi = ? WHERE id = ?');
    $stmt->execute([$newStatus, $userId]);

    $actionLabel = ($action === 'approve') ? 'disetujui' : 'ditolak';

    jsonResponse([
        'success' => true,
        'message' => "Akun {$user['nama']} berhasil {$actionLabel}.",
    ]);

} catch (PDOException $e) {
    jsonError('Gagal memproses aksi admin: ' . $e->getMessage(), 500);
}
