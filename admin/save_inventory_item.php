<?php
session_start();
require_once("../config/db.php");
require_once("../config/activity.php");

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php"); exit;
}
$adminName = $_SESSION['username'] ?? 'superadmin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['id'] ?? 0);
    $gid   = (int)($_POST['group_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc1 = trim($_POST['description1'] ?? '');
    $desc2 = trim($_POST['description2'] ?? '');
    $stock = (int)($_POST['stock'] ?? 0);

    if (!$title || !$gid) {
        header("Location: index.php?section=inv-stocks&error=Title+and+Group+required"); exit;
    }

    if ($id > 0) {
        $conn->prepare("UPDATE inventory_items SET group_id=:g, title=:t, description1=:d1, description2=:d2, stock=:s WHERE id=:id")
             ->execute([':g'=>$gid,':t'=>$title,':d1'=>$desc1,':d2'=>$desc2,':s'=>$stock,':id'=>$id]);
        log_activity($conn, $adminName, 'Edited inventory item', $title, "id=$id");
        header("Location: index.php?section=inv-stocks&success=Item+updated"); exit;
    } else {
        $conn->prepare("INSERT INTO inventory_items (group_id, title, description1, description2, stock) VALUES (:g,:t,:d1,:d2,:s)")
             ->execute([':g'=>$gid,':t'=>$title,':d1'=>$desc1,':d2'=>$desc2,':s'=>$stock]);
        log_activity($conn, $adminName, 'Added inventory item', $title, "group_id=$gid");
        header("Location: index.php?section=inv-stocks&success=Item+added"); exit;
    }
}
header("Location: index.php?section=inv-stocks"); exit;
?>