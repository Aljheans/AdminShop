<?php
/**
 * Gateway Guard
 * =============
 * Allows requests from two sources:
 *   1. FastAPI gateway  — carries Authorization: Bearer <INTERNAL_SYNC_KEY>
 *                         or X-Internal-Key: <INTERNAL_SYNC_KEY>
 *   2. Admin dashboard  — has a valid PHP session with role admin/superadmin
 *      (the dashboard runs on the same server and calls API files directly)
 */

// Load env
if (!getenv('INTERNAL_SYNC_KEY')) {
    require_once __DIR__ . '/env.php';
}

$_expectedKey = getenv('INTERNAL_SYNC_KEY') ?: '';

// ── Path 1: Session-based (admin dashboard calling its own API) ──
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_sessionRole = $_SESSION['role'] ?? '';
if (in_array($_sessionRole, ['admin', 'superadmin'])) {
    // Valid admin session — allow through
    return;
}
session_write_close(); // don't keep session locked for gateway calls

// ── Path 2: Gateway key ──
if ($_expectedKey === '') {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Gateway key not configured on server.']);
    exit;
}

// Check Authorization: Bearer <key>
$_authHeader  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$_providedKey = '';
if (str_starts_with($_authHeader, 'Bearer ')) {
    $_providedKey = substr($_authHeader, 7);
}
// Fallback: X-Internal-Key header
if ($_providedKey === '') {
    $_providedKey = $_SERVER['HTTP_X_INTERNAL_KEY'] ?? '';
}

if (!hash_equals($_expectedKey, trim($_providedKey))) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Forbidden. Direct access is not allowed.']);
    exit;
}

unset($_expectedKey, $_providedKey, $_authHeader, $_sessionRole);
?>