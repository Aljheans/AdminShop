<?php
require_once __DIR__ . '/../config/rate_limit.php';
rate_limit('save_refresh_token', 30, 60);
header('Content-Type: application/json');
require_once('../config/db.php');

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? 0;
$token   = $data['token']   ?? '';
$expiry  = $data['expiry']  ?? '';

if (!$user_id || !$token || !$expiry) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Missing fields']);
    exit;
}

$stmt = $conn->prepare(
  'INSERT INTO refresh_tokens (user_id, token, expiry) VALUES (:uid, :tok, :exp)'
);
$stmt->execute([':uid'=>$user_id, ':tok'=>$token, ':exp'=>$expiry]);
echo json_encode(['status'=>'success']);
?>