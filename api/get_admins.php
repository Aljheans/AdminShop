<?php
require_once __DIR__ . "/../config/gateway_guard.php";
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $conn->query("SELECT id, username, uid FROM users WHERE role IN ('admin','superadmin') ORDER BY uid ASC");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($admins as &$a) $a['id'] = (int)$a['id'];
    echo json_encode(['status'=>'success','admins'=>$admins]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Server error']);
}
?>