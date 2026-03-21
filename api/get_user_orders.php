<?php
require_once __DIR__ . "/../config/gateway_guard.php";
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$userId = (int)($_GET['user_id'] ?? 0);
if (!$userId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT p.id, p.purchased_at, p.price_paid, p.suboption, p.status,
               i.title AS item_title,
               g.title AS group_title,
               v.label AS variant_label
        FROM purchases p
        JOIN inventory_items i ON i.id = p.item_id
        JOIN item_groups g ON g.id = i.group_id
        JOIN inventory_item_variants v ON v.id = p.variant_id
        WHERE p.user_id = :uid
        ORDER BY p.purchased_at DESC
    ");
    $stmt->execute([':uid' => $userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as &$o) {
        $o['price_paid'] = (float)$o['price_paid'];
        $o['id'] = (int)$o['id'];
    }

    echo json_encode(['status' => 'success', 'orders' => $orders]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
?>