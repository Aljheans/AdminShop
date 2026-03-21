<?php
require_once __DIR__ . "/../config/gateway_guard.php";
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    $groups = $conn->query("SELECT id, title, image_url FROM item_groups ORDER BY title ASC")
                   ->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($groups as $g) {
        $iStmt = $conn->prepare("SELECT id, title, stock FROM inventory_items WHERE group_id=:gid ORDER BY title ASC");
        $iStmt->execute([':gid' => $g['id']]);
        $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            // Fetch variants with slots
            $vStmt = $conn->prepare("SELECT id, label, max_slots FROM inventory_item_variants WHERE item_id=:id ORDER BY id ASC");
            $vStmt->execute([':id' => $item['id']]);
            $variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch sub-options for each variant
            foreach ($variants as &$v) {
                $sStmt = $conn->prepare("SELECT label FROM variant_suboptions WHERE variant_id=:vid ORDER BY id ASC");
                $sStmt->execute([':vid' => $v['id']]);
                $v['suboptions'] = $sStmt->fetchAll(PDO::FETCH_COLUMN);
                $v['max_slots']  = (int)$v['max_slots'];
                unset($v['id']); // don't expose internal IDs
            }
            unset($v);

            $item['variants'] = $variants;
            $item['stock']    = (int)$item['stock'];
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