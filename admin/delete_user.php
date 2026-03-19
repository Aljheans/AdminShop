<?php
session_start();
// Auth guard
if (empty($_SESSION["role"]) || !in_array($_SESSION["role"], ["admin", "superadmin"])) {
    header("Location: index.php?error=Unauthorized");
    exit;
}
require_once("../config/db.php");
require_once("../config/activity.php");
$adminName = $_SESSION['username'] ?? 'admin';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header("Location: index.php?error=Invalid+user+ID"); exit;
}

// Fetch user before deleting so we can log their details
$stmt = $conn->prepare("SELECT username, role, email FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php?error=User+not+found"); exit;
}

// Protect superadmin from deletion
if ($user['role'] === 'superadmin') {
    header("Location: index.php?error=Cannot+delete+superadmin"); exit;
}

// Prevent admin from deleting themselves
if ($user['username'] === $adminName) {
    header("Location: index.php?error=You+cannot+delete+your+own+account"); exit;
}

try {
    $conn->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);

    log_activity($conn, $adminName, 'Deleted user', $user['username'],
        "id=$id, role={$user['role']}, email={$user['email']}");

    header("Location: index.php?success=User+deleted+successfully"); exit;

} catch (PDOException $e) {
    header("Location: index.php?error=Failed+to+delete+user"); exit;
}
?>