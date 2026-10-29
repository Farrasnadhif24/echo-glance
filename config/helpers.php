<?php
/**
 * ECHO-GLANCE Helper Functions
 */

/**
 * Send JSON response and exit
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error JSON response
 */
function jsonError(string $message, int $statusCode = 400): void {
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}

/**
 * Sanitize string input
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get request body as JSON
 */
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Format datetime for Indonesian locale
 */
function formatDateID(string $datetime): string {
    $d = new DateTime($datetime);
    return $d->format('d/m/Y H:i:s');
}

/**
 * Require POST method
 */
function requirePOST(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed', 405);
    }
}

/**
 * Require GET method
 */
function requireGET(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('Method not allowed', 405);
    }
}

/**
 * Require user to be logged in for API endpoints
 */
function requireAPILogin(): void {
    require_once __DIR__ . '/session.php';
    initSession();
    if (!isLoggedIn()) {
        jsonError('Unauthorized - silakan login terlebih dahulu', 401);
    }
}

/**
 * Require admin role for API endpoints
 */
function requireAPIAdmin(): void {
    requireAPILogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        jsonError('Forbidden - hanya admin yang dapat mengakses', 403);
    }
}
