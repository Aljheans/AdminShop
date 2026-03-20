<?php
/**
 * Gateway Guard
 * =============
 * Rejects any request that does not carry the correct internal key.
 *
 * The key is checked in three places (in order of priority):
 *   1. Authorization: Bearer <key>   ← standard, never stripped by proxies
 *   2. X-Internal-Key: <key>         ← custom header fallback
 *   3. Request body field "key"      ← body fallback for POST requests
 */

// Load env so INTERNAL_SYNC_KEY is available
if (!getenv('INTERNAL_SYNC_KEY')) {
    require_once __DIR__ . '/env.php';
}

$_expectedKey = getenv('INTERNAL_SYNC_KEY') ?: '';

// Reject if key is not configured on this server
if ($_expectedKey === '') {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Gateway key not configured on server.']);
    exit;
}

// ── Read the provided key from any of the three locations ──

// 1. Authorization: Bearer <key>
$_authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($_authHeader, 'Bearer ')) {
    $_providedKey = substr($_authHeader, 7);
} else {
    $_providedKey = '';
}

// 2. X-Internal-Key header (fallback)
if ($_providedKey === '') {
    $_providedKey = $_SERVER['HTTP_X_INTERNAL_KEY'] ?? '';
}

// 3. Request body field (POST fallback)
if ($_providedKey === '') {
    $_body = json_decode(file_get_contents('php://input'), true);
    $_providedKey = $_body['_key'] ?? '';
}

// ── Constant-time comparison ──
if (!hash_equals($_expectedKey, trim($_providedKey))) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Forbidden. Direct access is not allowed.']);
    exit;
}

// All good — clean up
unset($_expectedKey, $_providedKey, $_authHeader, $_body);
?>