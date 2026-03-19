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

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? '');
    $email    = trim($_POST["email"]    ?? '');
    $password = $_POST["password"]      ?? '';
    $role     = $_POST["role"]          ?? 'user';

    if (empty($username) || empty($email) || empty($password)) {
        header("Location: index.php?error=All+fields+are+required"); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?error=Invalid+email+address"); exit;
    }
    if (strlen($password) < 8) {
        header("Location: index.php?error=Password+must+be+at+least+8+characters"); exit;
    }

    // ── Admin limit: max 5 (excluding superadmin) ──
    if ($role === 'admin') {
        $adminCount = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        if ($adminCount >= 5) {
            header("Location: index.php?error=Admin+limit+reached+(max+5+admins)"); exit;
        }
    }

    // ── Assign UID ──
    if ($role === 'admin') {
        // Next available 4-digit admin code (0002–0005)
        $usedUids = $conn->query("SELECT uid FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN);
        $uid = '0001';
        for ($n = 1; $n <= 9999; $n++) {
            $candidate = str_pad($n, 4, '0', STR_PAD_LEFT);
            if (!in_array($candidate, $usedUids)) { $uid = $candidate; break; }
        }
    } else {
        // Random unique 4-digit code for users (1000–9999)
        do {
            $uid = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $exists = $conn->prepare("SELECT COUNT(*) FROM users WHERE uid = :uid");
            $exists->execute([':uid' => $uid]);
        } while ($exists->fetchColumn() > 0);
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password, role, uid, is_online)
            VALUES (:username, :email, :password, :role, :uid, 0)
        ");
        $stmt->execute([
            ':username' => $username,
            ':email'    => $email,
            ':password' => $hashed,
            ':role'     => $role,
            ':uid'      => $uid,
        ]);

        $newId = $conn->lastInsertId();

        // ── Create user_stats row for regular users ──
        if ($role === 'user') {
            $conn->prepare("INSERT OR IGNORE INTO user_stats (user_id) VALUES (:uid)")
                 ->execute([':uid' => $newId]);
        }

        // ── Create default admin_permissions row for admins ──
        if ($role === 'admin') {
            $conn->prepare("INSERT OR IGNORE INTO admin_permissions (user_id) VALUES (:uid)")
                 ->execute([':uid' => $newId]);
        }

        log_activity($conn, $adminName, 'Added user', $username, "role=$role, uid=$uid, email=$email");
        header("Location: index.php?success=User+created+successfully+(UID:+$uid)"); exit;

    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'users.username')) {
            header("Location: index.php?error=Username+already+exists");
        } elseif (str_contains($e->getMessage(), 'users.email')) {
            header("Location: index.php?error=Email+already+in+use");
        } else {
            header("Location: index.php?error=Failed+to+create+user");
        }
        exit;
    }
}

header("Location: index.php");
exit;
?>