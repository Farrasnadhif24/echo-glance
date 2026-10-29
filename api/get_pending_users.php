<?php
/**
 * API: Get Pending Users
 * GET - Fetch users with status 'pending' for admin verification
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPIAdmin();

try {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT id, nama, email, role, str_number, status_verifikasi, created_at 
         FROM users 
         WHERE status_verifikasi = 'pending' 
         ORDER BY created_at DESC"
    );
    $stmt->execute();
    $users = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data'    => $users,
        'count'   => count($users),
    ]);

} catch (PDOException $e) {
    jsonError('Gagal mengambil daftar user pending: ' . $e->getMessage(), 500);
}
