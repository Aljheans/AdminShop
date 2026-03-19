<?php
session_start();
header('Content-Type: application/json');

require_once("../config/db.php");
require_once("../config/activity.php");

// Auth guard
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$adminName = $_SESSION['username'] ?? 'admin';

$body = json_decode(file_get_contents('php://input'), true);

$userId = (int)($body['user_id'] ?? 0);
$coins  = max(0, (int)($body['coins']  ?? 0));
$shards = max(0, (int)($body['shards'] ?? 0));
$level  = max(1, min(9999, (int)($body['level'] ?? 1)));

if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

// Confirm target is a regular user
$target = $conn->prepare("SELECT username, role FROM users WHERE id = :id");
$target->execute([':id' => $userId]);
$target = $target->fetch(PDO::FETCH_ASSOC);

if (!$target || $target['role'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'User not found or not a regular user']);
    exit;
}

try {
    // Upsert — create stats row if it doesn't exist yet
    $conn->prepare("
        INSERT INTO user_stats (user_id, coins, shards, level)
        VALUES (:uid, :coins, :shards, :level)
        ON CONFLICT(user_id) DO UPDATE SET
            coins  = :coins,
            shards = :shards,
            level  = :level
    ")->execute([
        ':uid'    => $userId,
        ':coins'  => $coins,
        ':shards' => $shards,
        ':level'  => $level,
    ]);

    log_activity($conn, $adminName, 'Updated user stats', $target['username'],
        "coins=$coins, shards=$shards, level=$level");

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>