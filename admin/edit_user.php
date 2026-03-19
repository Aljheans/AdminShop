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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id       = (int)($_POST['id']       ?? 0);
    $username = trim($_POST['username']  ?? '');
    $email    = trim($_POST['email']     ?? '');
    $role     = $_POST['role']           ?? 'user';

    if (!$id || empty($username) || empty($email)) {
        header("Location: index.php?error=Invalid+form+data"); exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?error=Invalid+email+address"); exit;
    }

    // Cannot edit superadmin
    $check = $conn->prepare("SELECT role, username FROM users WHERE id = :id");
    $check->execute([':id' => $id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        header("Location: index.php?error=User+not+found"); exit;
    }
    if ($existing['role'] === 'superadmin') {
        header("Location: index.php?error=Cannot+edit+superadmin"); exit;
    }

    // Admin limit check if role is being changed TO admin
    if ($role === 'admin' && $existing['role'] !== 'admin') {
        $adminCount = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        if ($adminCount >= 5) {
            header("Location: index.php?error=Admin+limit+reached+(max+5)"); exit;
        }
    }

    try {
        $stmt = $conn->prepare("
            UPDATE users
            SET username   = :username,
                email      = :email,
                role       = :role,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute([
            ':username' => $username,
            ':email'    => $email,
            ':role'     => $role,
            ':id'       => $id,
        ]);

        // If role changed to user, ensure user_stats row exists
        if ($role === 'user') {
            $conn->prepare("INSERT OR IGNORE INTO user_stats (user_id) VALUES (:id)")
                 ->execute([':id' => $id]);
        }

        // If role changed to admin, ensure admin_permissions row exists
        if ($role === 'admin') {
            $conn->prepare("INSERT OR IGNORE INTO admin_permissions (user_id) VALUES (:id)")
                 ->execute([':id' => $id]);
        }

        log_activity($conn, $adminName, 'Edited user', $username,
            "id=$id, role=$role, email=$email (was: {$existing['username']}, {$existing['role']})");

        header("Location: index.php?success=User+updated+successfully"); exit;

    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'users.username')) {
            header("Location: index.php?error=Username+already+exists");
        } elseif (str_contains($e->getMessage(), 'users.email')) {
            header("Location: index.php?error=Email+already+in+use");
        } else {
            header("Location: index.php?error=Failed+to+update+user");
        }
        exit;
    }
}

header("Location: index.php");
exit;
?>