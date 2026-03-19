<?php
require_once __DIR__ . '/../config/rate_limit.php';
rate_limit('heartbeat', 30, 60);
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(["status" => "error"]);
    exit;
}

require_once __DIR__ . "/../config/db.php";

$stmt = $conn->prepare("
    UPDATE users
    SET last_activity = CURRENT_TIMESTAMP,
        is_online = 1
    WHERE id = :id
");

$stmt->execute([':id' => $user_id]);

echo json_encode(["status" => "alive"]);