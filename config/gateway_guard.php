<?php
/**
 * Gateway Guard
 * =============
 * Every PHP API endpoint must include this file first.
 * Rejects any request that does not carry the correct X-Internal-Key header.
 * This ensures only the FastAPI gateway can reach the PHP backend.
 */

// Load env so INTERNAL_SYNC_KEY is available
if (!function_exists('getenv') || !getenv('INTERNAL_SYNC_KEY')) {
    require_once __DIR__ . '/env.php';
}

$_expectedKey = getenv('INTERNAL_SYNC_KEY') ?: '';

// Reject if key is not configured (misconfigured server)
if ($_expectedKey === '') {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Gateway key not configured on server.']);
    exit;
}

// Read header — PHP converts it to HTTP_X_INTERNAL_KEY
$_providedKey = $_SERVER['HTTP_X_INTERNAL_KEY'] ?? '';

// Constant-time comparison to prevent timing attacks
if (!hash_equals($_expectedKey, $_providedKey)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Forbidden. Direct access is not allowed.']);
    exit;
}

// All good — clean up temp vars to avoid polluting global scope
unset($_expectedKey, $_providedKey);
?>