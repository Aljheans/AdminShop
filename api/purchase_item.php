<?php
require_once __DIR__ . "/../config/gateway_guard.php";
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/activity.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$userId     = (int)($data['user_id']    ?? 0);
$itemId     = (int)($data['item_id']    ?? 0);
$variantId  = (int)($data['variant_id'] ?? 0);
$suboption  = trim($data['suboption']   ?? '');

if (!$userId || !$itemId || !$variantId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
    exit;
}

try {
    $conn->beginTransaction();

    // Lock & fetch the variant
    $vStmt = $conn->prepare("SELECT * FROM inventory_item_variants WHERE id=:id AND item_id=:iid");
    $vStmt->execute([':id' => $variantId, ':iid' => $itemId]);
    $variant = $vStmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Variant not found.']);
        exit;
    }

    $available = (int)$variant['max_slots'] - (int)$variant['slots_used'];
    if ($available <= 0) {
        $conn->rollBack();
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'No slots available for this variant.']);
        exit;
    }

    // Decrement slots_used
    $conn->prepare("UPDATE inventory_item_variants SET slots_used = slots_used + 1 WHERE id=:id")
         ->execute([':id' => $variantId]);

    // Record purchase
    $conn->prepare("
        INSERT INTO purchases (user_id, item_id, variant_id, suboption, price_paid, status)
        VALUES (:uid, :iid, :vid, :sub, :price, 'active')
    ")->execute([
        ':uid'   => $userId,
        ':iid'   => $itemId,
        ':vid'   => $variantId,
        ':sub'   => $suboption,
        ':price' => $variant['price'],
    ]);

    $purchaseId = $conn->lastInsertId();

    // Fetch user info for activity log
    $uStmt = $conn->prepare("SELECT username FROM users WHERE id=:id");
    $uStmt->execute([':id' => $userId]);
    $username = $uStmt->fetchColumn() ?: "user#$userId";

    // Fetch item title for log
    $iStmt = $conn->prepare("SELECT title FROM inventory_items WHERE id=:id");
    $iStmt->execute([':id' => $itemId]);
    $itemTitle = $iStmt->fetchColumn() ?: "item#$itemId";

    log_activity($conn, $username, 'Purchased item',
        "$itemTitle — {$variant['label']}" . ($suboption ? " ($suboption)" : ''),
        "purchase_id=$purchaseId price=₱{$variant['price']}"
    );

    $conn->commit();

    echo json_encode([
        'status'      => 'success',
        'purchase_id' => (int)$purchaseId,
        'item'        => $itemTitle,
        'variant'     => $variant['label'],
        'suboption'   => $suboption,
        'price_paid'  => (float)$variant['price'],
        'slots_left'  => $available - 1,
        'message'     => 'Purchase successful!',
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Purchase failed. Please try again.']);
}
?>