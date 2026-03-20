<?php
/**
 * TEMPORARY DEBUG — DELETE AFTER CONFIRMING FIX
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/env.php';

$expected = getenv('INTERNAL_SYNC_KEY') ?: '';

$authHeader   = $_SERVER['HTTP_AUTHORIZATION'] ?? '(none)';
$xInternalKey = $_SERVER['HTTP_X_INTERNAL_KEY'] ?? '(none)';

// Extract Bearer token
$providedViaAuth = '';
if (str_starts_with($authHeader, 'Bearer ')) {
    $providedViaAuth = substr($authHeader, 7);
}

echo json_encode([
    'php_key_configured'        => $expected !== '',
    'php_key_length'            => strlen($expected),
    'php_key_preview'           => substr($expected, 0, 4) . '...' . substr($expected, -4),

    'auth_header_present'       => $authHeader !== '(none)',
    'auth_header_value'         => substr($authHeader, 0, 15) . (strlen($authHeader) > 15 ? '...' : ''),
    'bearer_key_length'         => strlen($providedViaAuth),
    'bearer_key_matches'        => $expected !== '' && hash_equals($expected, $providedViaAuth),

    'x_internal_key_present'    => $xInternalKey !== '(none)',
    'x_internal_key_length'     => strlen($xInternalKey === '(none)' ? '' : $xInternalKey),
    'x_internal_key_matches'    => $expected !== '' && hash_equals($expected, $xInternalKey),

    'all_server_headers'        => array_filter(
        array_keys($_SERVER),
        fn($k) => str_starts_with($k, 'HTTP_')
    ),
]);
?>