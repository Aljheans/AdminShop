<?php
require_once __DIR__ . "/../config/gateway_guard.php";
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$requesterId = (int)($_GET['requester_id'] ?? 0);
$role        = trim($_GET['role'] ?? 'admin');

if (!$requesterId) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Missing requester_id']);
    exit;
}

try {
    if ($role === 'superadmin') {
        // Superadmin sees all orders
        $stmt = $conn->query("
            SELECT o.id, o.receipt_id, o.status, o.suboption, o.price, o.created_at,
                   o.item_title, o.variant_label, o.screenshot,
                   u.username AS buyer_username,
                   u.uid      AS buyer_uid,
                   a.username AS admin_username
            FROM orders o
            JOIN users u ON u.id = o.user_id
            JOIN users a ON a.id = o.admin_id
            ORDER BY o.created_at DESC
        ");
    } else {
        // Admin sees only their assigned orders
        $stmt = $conn->prepare("
            SELECT o.id, o.receipt_id, o.status, o.suboption, o.price, o.created_at,
                   o.item_title, o.variant_label, o.screenshot,
                   u.username AS buyer_username,
                   u.uid      AS buyer_uid,
                   a.username AS admin_username
            FROM orders o
            JOIN users u ON u.id = o.user_id
            JOIN users a ON a.id = o.admin_id
            WHERE o.admin_id = :aid
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([':aid' => $requesterId]);
    }

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orders as &$o) {
        $o['id']    = (int)$o['id'];
        $o['price'] = (float)$o['price'];
        // Don't send screenshot in list view — only when requested by ID
        unset($o['screenshot']);
    }

    echo json_encode(['status'=>'success','orders'=>$orders]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Server error']);
}
?>