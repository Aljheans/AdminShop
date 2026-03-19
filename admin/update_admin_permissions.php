<?php
session_start();
require_once("../config/db.php");
require_once("../config/activity.php");
$adminName = $_SESSION['username'] ?? 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId      = (int)($_POST['user_id'] ?? 0);
    $canUserdata = isset($_POST['can_userdata']) ? 1 : 0;
    $canActivity = isset($_POST['can_activity']) ? 1 : 0;
    $canSettings = isset($_POST['can_settings']) ? 1 : 0;

    if (!$userId) {
        header("Location: index.php?section=admins&error=Invalid+user"); exit;
    }

    $target = $conn->prepare("SELECT username, role FROM users WHERE id = :id");
    $target->execute([':id' => $userId]);
    $target = $target->fetch(PDO::FETCH_ASSOC);

    if (!$target || $target['role'] === 'superadmin') {
        header("Location: index.php?section=admins&error=Cannot+modify+superadmin"); exit;
    }

    $conn->prepare("
        INSERT INTO admin_permissions (user_id, can_userdata, can_activity, can_settings)
        VALUES (:uid, :cu, :ca, :cs)
        ON CONFLICT(user_id) DO UPDATE SET
            can_userdata = :cu,
            can_activity = :ca,
            can_settings = :cs
    ")->execute([
        ':uid' => $userId,
        ':cu'  => $canUserdata,
        ':ca'  => $canActivity,
        ':cs'  => $canSettings,
    ]);

    log_activity($conn, $adminName, 'Updated admin permissions', $target['username'],
        "userdata=$canUserdata, activity=$canActivity, settings=$canSettings");

    header("Location: index.php?section=admins&success=Permissions+updated"); exit;
}

header("Location: index.php?section=admins"); exit;
?>