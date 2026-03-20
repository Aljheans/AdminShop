<?php
session_start();
require_once("../config/db.php");
require_once("../config/activity.php");

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php"); exit;
}
$adminName = $_SESSION['username'] ?? 'superadmin';
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $row = $conn->prepare("SELECT title FROM item_groups WHERE id=:id");
    $row->execute([':id' => $id]);
    $row = $row->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $conn->prepare("DELETE FROM item_groups WHERE id=:id")->execute([':id' => $id]);
        log_activity($conn, $adminName, 'Deleted item group', $row['title'], "id=$id");
        header("Location: index.php?section=inv-item-group&success=Group+deleted"); exit;
    }
}
header("Location: index.php?section=inv-item-group&error=Group+not+found"); exit;
?>