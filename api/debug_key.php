<?php
/**
 * TEMPORARY DEBUG FILE — DELETE AFTER FIXING
 * Hit this URL directly to see what PHP thinks its key is.
 * Never leave this in production.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/env.php';

$key = getenv('INTERNAL_SYNC_KEY') ?: '';
$received = $_SERVER['HTTP_X_INTERNAL_KEY'] ?? '(none)';

echo json_encode([
    'php_key_length'      => strlen($key),
    'php_key_first4'      => substr($key, 0, 4) ?: '(empty)',
    'php_key_last4'       => substr($key, -4) ?: '(empty)',
    'received_key_length' => strlen($received),
    'received_first4'     => substr($received, 0, 4),
    'keys_match'          => hash_equals($key, $received),
    'key_is_empty'        => $key === '',
]);
?>