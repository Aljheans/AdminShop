<?php
require_once __DIR__ . "/../config/gateway_guard.php";
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

try {
    $groups = $conn->query("SELECT id, title, image_url FROM item_groups ORDER BY title ASC")
                   ->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($groups as $g) {
        $iStmt = $conn->prepare("
            SELECT i.id, i.title, i.stock
            FROM inventory_items i
            WHERE i.group_id = :gid
            ORDER BY i.title ASC
        ");
        $iStmt->execute([':gid' => $g['id']]);
        $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $vStmt = $conn->prepare("
                SELECT label FROM inventory_item_variants
                WHERE item_id = :id ORDER BY id ASC
            ");
            $vStmt->execute([':id' => $item['id']]);
            $item['variants'] = $vStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($item);

        $result[] = [
            'id'        => (int)$g['id'],
            'title'     => $g['title'],
            'image_url' => $g['image_url'],
            'items'     => $items,
        ];
    }

    echo json_encode(['status' => 'success', 'groups' => $result]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
?>