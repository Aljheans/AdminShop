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

    // Variants sent as JSON from the form
    // Structure: [{"label":"Shared Profile","slots":2,"suboptions":["sub1","sub2"]}, ...]
    $variantsJson = $_POST['variants_json'] ?? '[]';
    $variants = json_decode($variantsJson, true) ?: [];
    // Filter out variants with no label
    $variants = array_values(array_filter($variants, fn($v) => trim($v['label'] ?? '') !== ''));

    if (!$title || !$gid) {
        header("Location: index.php?section=inv-stocks&error=Title+and+Group+required"); exit;
    }

    $conn->beginTransaction();
    try {
        if ($id > 0) {
            $conn->prepare("UPDATE inventory_items SET group_id=:g, title=:t, description1=:d1, description2=:d2, stock=:s WHERE id=:id")
                 ->execute([':g'=>$gid,':t'=>$title,':d1'=>$desc1,':d2'=>$desc2,':s'=>$stock,':id'=>$id]);
            // Delete old variants + suboptions (cascade handles suboptions)
            $conn->prepare("DELETE FROM inventory_item_variants WHERE item_id=:id")->execute([':id'=>$id]);
            $itemId = $id;
        } else {
            $conn->prepare("INSERT INTO inventory_items (group_id, title, description1, description2, stock) VALUES (:g,:t,:d1,:d2,:s)")
                 ->execute([':g'=>$gid,':t'=>$title,':d1'=>$desc1,':d2'=>$desc2,':s'=>$stock]);
            $itemId = (int)$conn->lastInsertId();
        }

        // Insert variants + their suboptions
        foreach ($variants as $v) {
            $label    = trim($v['label'] ?? '');
            $slots    = max(1, (int)($v['slots'] ?? 1));
            $subopts  = array_values(array_filter(array_map('trim', (array)($v['suboptions'] ?? [])), fn($s) => $s !== ''));

            $price   = round((float)($v['price']   ?? 0), 2);
            $capital = round((float)($v['capital']  ?? 0), 2);
            $conn->prepare("INSERT INTO inventory_item_variants (item_id, label, max_slots, price, capital_price) VALUES (:iid,:l,:s,:p,:c)")
                 ->execute([':iid'=>$itemId,':l'=>$label,':s'=>$slots,':p'=>$price,':c'=>$capital]);
            $variantId = (int)$conn->lastInsertId();

            foreach ($subopts as $sub) {
                $conn->prepare("INSERT INTO variant_suboptions (variant_id, label) VALUES (:vid,:l)")
                     ->execute([':vid'=>$variantId,':l'=>$sub]);
            }
        }

        $conn->commit();
        $action = $id > 0 ? 'Edited inventory item' : 'Added inventory item';
        log_activity($conn, $adminName, $action, $title, "variants=".count($variants));
        header("Location: index.php?section=inv-stocks&success=Item+".($id>0?'updated':'added'));
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: index.php?section=inv-stocks&error=Save+failed");
        exit;
    }
}
header("Location: index.php?section=inv-stocks"); exit;
?>