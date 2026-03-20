<?php
session_start();
require_once("../config/db.php");
require_once("../config/activity.php");

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php"); exit;
}
$adminName = $_SESSION['username'] ?? 'superadmin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $gid      = (int)($_POST['group_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $desc1    = trim($_POST['description1'] ?? '');
    $desc2    = trim($_POST['description2'] ?? '');
    $stock    = (int)($_POST['stock'] ?? 0);
    // Variants: array of non-empty labels
    $variants = array_values(array_filter(array_map('trim', (array)($_POST['variants'] ?? [])), fn($v) => $v !== ''));

    if (!$title || !$gid) {
        header("Location: index.php?section=inv-stocks&error=Title+and+Group+required"); exit;
    }

    if ($id > 0) {
        // Update item
        $conn->prepare("UPDATE inventory_items SET group_id=:g, title=:t, description1=:d1, description2=:d2, stock=:s WHERE id=:id")
             ->execute([':g'=>$gid,':t'=>$title,':d1'=>$desc1,':d2'=>$desc2,':s'=>$stock,':id'=>$id]);
        // Replace variants
        $conn->prepare("DELETE FROM inventory_item_variants WHERE item_id=:id")->execute([':id'=>$id]);
        foreach ($variants as $v) {
            $conn->prepare("INSERT INTO inventory_item_variants (item_id, label) VALUES (:iid,:l)")
                 ->execute([':iid'=>$id,':l'=>$v]);
        }
        log_activity($conn, $adminName, 'Edited inventory item', $title, "id=$id, variants=".count($variants));
        header("Location: index.php?section=inv-stocks&success=Item+updated"); exit;
    } else {
        // Insert item
        $conn->prepare("INSERT INTO inventory_items (group_id, title, description1, description2, stock) VALUES (:g,:t,:d1,:d2,:s)")
             ->execute([':g'=>$gid,':t'=>$title,':d1'=>$desc1,':d2'=>$desc2,':s'=>$stock]);
        $newId = (int)$conn->lastInsertId();
        foreach ($variants as $v) {
            $conn->prepare("INSERT INTO inventory_item_variants (item_id, label) VALUES (:iid,:l)")
                 ->execute([':iid'=>$newId,':l'=>$v]);
        }
        log_activity($conn, $adminName, 'Added inventory item', $title, "group_id=$gid, variants=".count($variants));
        header("Location: index.php?section=inv-stocks&success=Item+added"); exit;
    }
}
header("Location: index.php?section=inv-stocks"); exit;
?>