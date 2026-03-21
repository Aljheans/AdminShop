<?php
require_once __DIR__ . "/../config/gateway_guard.php";
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/activity.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status'=>'error','message'=>'Method not allowed']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$userId     = (int)($data['user_id']    ?? 0);
$adminId    = (int)($data['admin_id']   ?? 0);
$itemId     = (int)($data['item_id']    ?? 0);
$variantId  = (int)($data['variant_id'] ?? 0);
$suboption  = trim($data['suboption']   ?? '');
$screenshot = $data['screenshot']        ?? ''; // base64 data URI

if (!$userId || !$adminId || !$itemId || !$variantId) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Missing required fields.']);
    exit;
}
if (!$screenshot) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Screenshot is required.']);
    exit;
}

// Validate screenshot is a data URI
if (!str_starts_with($screenshot, 'data:image/')) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid screenshot format.']);
    exit;
}

try {
    $conn->beginTransaction();

    // Fetch variant and check slots
    $vStmt = $conn->prepare("SELECT * FROM inventory_item_variants WHERE id=:id AND item_id=:iid");
    $vStmt->execute([':id'=>$variantId,':iid'=>$itemId]);
    $variant = $vStmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['status'=>'error','message'=>'Variant not found.']);
        exit;
    }

    $available = (int)$variant['max_slots'] - (int)$variant['slots_used'];
    if ($available <= 0) {
        $conn->rollBack();
        http_response_code(409);
        echo json_encode(['status'=>'error','message'=>'No slots available for this variant.']);
        exit;
    }

    // Fetch item title
    $iStmt = $conn->prepare("SELECT title FROM inventory_items WHERE id=:id");
    $iStmt->execute([':id'=>$itemId]);
    $itemTitle = $iStmt->fetchColumn() ?: '';

    // Generate unique 8-char receipt ID (uppercase alphanumeric)
    $receiptId = '';
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $receiptId = '';
        for ($i = 0; $i < 8; $i++) $receiptId .= $chars[random_int(0, strlen($chars)-1)];
        $check = $conn->prepare("SELECT id FROM orders WHERE receipt_id=:r");
        $check->execute([':r'=>$receiptId]);
    } while ($check->fetch());

    // Decrement slots_used
    $conn->prepare("UPDATE inventory_item_variants SET slots_used = slots_used + 1 WHERE id=:id")
         ->execute([':id'=>$variantId]);

    // Insert order (status = 'reviewing')
    $conn->prepare("
        INSERT INTO orders (receipt_id, user_id, admin_id, item_id, variant_id, suboption,
                            item_title, variant_label, price, screenshot, status)
        VALUES (:r, :uid, :aid, :iid, :vid, :sub, :ititle, :vlabel, :price, :ss, 'reviewing')
    ")->execute([
        ':r'      => $receiptId,
        ':uid'    => $userId,
        ':aid'    => $adminId,
        ':iid'    => $itemId,
        ':vid'    => $variantId,
        ':sub'    => $suboption,
        ':ititle' => $itemTitle,
        ':vlabel' => $variant['label'],
        ':price'  => $variant['price'],
        ':ss'     => $screenshot,
    ]);

    // Fetch username for activity log
    $uStmt = $conn->prepare("SELECT username FROM users WHERE id=:id");
    $uStmt->execute([':id'=>$userId]);
    $username = $uStmt->fetchColumn() ?: "user#$userId";

    log_activity($conn, $username, 'Placed order',
        "$itemTitle — {$variant['label']}" . ($suboption ? " ($suboption)" : ''),
        "receipt=$receiptId"
    );

    $conn->commit();

    echo json_encode([
        'status'       => 'success',
        'receipt_id'   => $receiptId,
        'item'         => $itemTitle,
        'variant'      => $variant['label'],
        'suboption'    => $suboption,
        'price'        => (float)$variant['price'],
        'order_status' => 'reviewing',
        'message'      => 'Order placed! Awaiting admin review.',
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Order failed. Please try again.']);
}
?>