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
    $title    = trim($_POST['title'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');

    if (!$title) {
        header("Location: index.php?section=inv-item-group&error=Title+required"); exit;
    }

    if ($id > 0) {
        $existing = $conn->prepare("SELECT image_url FROM item_groups WHERE id=:id");
        $existing->execute([':id' => $id]);
        $existing = $existing->fetch(PDO::FETCH_ASSOC);
        $finalImage = ($imageUrl !== '') ? $imageUrl : ($existing['image_url'] ?? '');
        $conn->prepare("UPDATE item_groups SET title=:t, image_url=:i WHERE id=:id")
             ->execute([':t' => $title, ':i' => $finalImage, ':id' => $id]);
        log_activity($conn, $adminName, 'Edited item group', $title, "id=$id");
        header("Location: index.php?section=inv-item-group&success=Group+updated"); exit;
    } else {
        $conn->prepare("INSERT INTO item_groups (title, image_url) VALUES (:t, :i)")
             ->execute([':t' => $title, ':i' => $imageUrl]);
        log_activity($conn, $adminName, 'Added item group', $title, '');
        header("Location: index.php?section=inv-item-group&success=Group+added"); exit;
    }
}
header("Location: index.php?section=inv-item-group"); exit;
?>