<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Internal-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Internal key guard — only the FastAPI gateway may call this
require_once __DIR__ . '/../config/env.php';
$expectedKey = getenv('INTERNAL_SYNC_KEY') ?: '';
$providedKey = $_SERVER['HTTP_X_INTERNAL_KEY'] ?? '';

if ($expectedKey && !hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    // Fetch all groups
    $groups = $conn->query("SELECT id, title, image_url FROM item_groups ORDER BY title ASC")
                   ->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($groups as $g) {
        // Fetch items for this group
        $iStmt = $conn->prepare("
            SELECT i.id, i.title, i.description1, i.description2
            FROM inventory_items i
            WHERE i.group_id = :gid
            ORDER BY i.title ASC
        ");
        $iStmt->execute([':gid' => $g['id']]);
        $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach variants to each item
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