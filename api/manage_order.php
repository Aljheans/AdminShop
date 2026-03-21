<?php
require_once __DIR__ . "/../config/gateway_guard.php";
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/activity.php';

// GET ?id=xxx → return order including screenshot
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $orderId = (int)($_GET['id'] ?? 0);
    if (!$orderId) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Missing id']); exit; }
    $stmt = $conn->prepare("SELECT o.*, u.username AS buyer_username, a.username AS admin_username FROM orders o JOIN users u ON u.id=o.user_id JOIN users a ON a.id=o.admin_id WHERE o.id=:id");
    $stmt->execute([':id'=>$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Order not found']); exit; }
    $order['price'] = (float)$order['price'];
    echo json_encode(['status'=>'success','order'=>$order]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['status'=>'error','message'=>'Method not allowed']); exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$orderId = (int)($data['order_id'] ?? 0);
$status  = trim($data['status'] ?? '');
$adminId = (int)($data['admin_id'] ?? 0);

$allowed = ['reviewing', 'approved', 'cancelled'];
if (!$orderId || !in_array($status, $allowed)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid input.']);
    exit;
}

try {
    // Fetch order to verify admin has rights
    $oStmt = $conn->prepare("SELECT * FROM orders WHERE id=:id");
    $oStmt->execute([':id'=>$orderId]);
    $order = $oStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Order not found']); exit; }

    // If slots need to be freed when cancelled
    if ($status === 'cancelled' && $order['status'] !== 'cancelled') {
        $conn->prepare("UPDATE inventory_item_variants SET slots_used = MAX(0, slots_used - 1) WHERE id=:id")
             ->execute([':id' => $order['variant_id']]);
    }
    // If re-activating a cancelled order, re-claim the slot
    if ($order['status'] === 'cancelled' && $status !== 'cancelled') {
        $conn->prepare("UPDATE inventory_item_variants SET slots_used = slots_used + 1 WHERE id=:id")
             ->execute([':id' => $order['variant_id']]);
    }

    $conn->prepare("UPDATE orders SET status=:s WHERE id=:id")->execute([':s'=>$status,':id'=>$orderId]);

    // Fetch admin username for log
    $aStmt = $conn->prepare("SELECT username FROM users WHERE id=:id");
    $aStmt->execute([':id'=>$adminId]);
    $adminName = $aStmt->fetchColumn() ?: "admin#$adminId";

    log_activity($conn, $adminName, "Order status → $status", $order['receipt_id'], "order_id=$orderId");

    echo json_encode(['status'=>'success','new_status'=>$status]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Update failed.']);
}
?>