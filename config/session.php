<?php
/**
 * ECHO-GLANCE Session Management & Auth Helpers
 */

// Start session with security settings
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400,     // 24 hours
            'path'     => '/',
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    initSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user to be logged in, redirect to login if not
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Require specific role(s). Accepts multiple roles.
 * Example: requireRole('tenaga_medis', 'admin')
 */
function requireRole(string ...$roles): void {
    requireLogin();
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, $roles)) {
        header('Location: 403.html');
        exit;
    }
}

/**
 * Require user status to be 'approved'
 */
function requireApproved(): void {
    requireLogin();
    $status = $_SESSION['status_verifikasi'] ?? '';
    if ($status === 'pending') {
        header('Location: pending.php');
        exit;
    }
    if ($status === 'rejected') {
        session_destroy();
        header('Location: index.php?error=rejected');
        exit;
    }
}

/**
 * Get current user data from session
 */
function getCurrentUser(): array {
    initSession();
    return [
        'id'                => $_SESSION['user_id'] ?? null,
        'nama'              => $_SESSION['nama'] ?? '',
        'email'             => $_SESSION['email'] ?? '',
        'role'              => $_SESSION['role'] ?? '',
        'status_verifikasi' => $_SESSION['status_verifikasi'] ?? '',
    ];
}

/**
 * Set user session after login
 */
function setUserSession(array $user): void {
    initSession();
    $_SESSION['user_id']            = $user['id'];
    $_SESSION['nama']               = $user['nama'];
    $_SESSION['email']              = $user['email'];
    $_SESSION['role']               = $user['role'];
    $_SESSION['status_verifikasi']  = $user['status_verifikasi'];
}

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken(string $token): bool {
    initSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get user avatar initials (first letters of each word)
 */
function getInitials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: '?';
}

/**
 * Get role display name
 */
function getRoleDisplayName(string $role): string {
    $map = [
        'keluarga'     => 'Keluarga',
        'tenaga_medis' => 'Tenaga Medis',
        'admin'        => 'Administrator',
    ];
    return $map[$role] ?? ucfirst($role);
}
