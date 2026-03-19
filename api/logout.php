<?php
session_start();
require_once("../config/db.php");
require_once("../config/activity.php");

if (!empty($_SESSION['username'])) {
    log_activity($conn, $_SESSION['username'], 'Logged out', $_SESSION['username'], '');
    $conn->prepare("UPDATE users SET is_online = 0 WHERE username = :u")
         ->execute([':u' => $_SESSION['username']]);
}

session_destroy();
header("Location: ../index.php");
exit;
?>