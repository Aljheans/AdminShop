<?php

function log_activity(PDO $conn, string $admin, string $action, string $target = '', string $detail = ''): void {
    try {
        $stmt = $conn->prepare("
            INSERT INTO activity_log (admin, action, target, detail)
            VALUES (:admin, :action, :target, :detail)
        ");
        $stmt->execute([
            ':admin'  => $admin,
            ':action' => $action,
            ':target' => $target,
            ':detail' => $detail,
        ]);
    } catch (Exception $e) { /* never let logging break the main flow */ }
}
?>