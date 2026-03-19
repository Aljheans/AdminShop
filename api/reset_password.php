<?php
require_once __DIR__ . '/../config/rate_limit.php';
rate_limit('reset_password', 10, 60);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/activity.php';
session_start();
$adminName = $_SESSION['username'] ?? 'admin';

// Ensure the log table exists (safety net in case db.php wasn't redeployed)
$conn->exec("
    CREATE TABLE IF NOT EXISTS password_reset_log (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        username   TEXT NOT NULL,
        code       TEXT NOT NULL,
        reset_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

$data   = json_decode(file_get_contents('php://input'), true);
$userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;

if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'user_id is required.']);
    exit;
}

// Fetch user
$stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}

// Generate 6-digit code
$code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$hashed  = password_hash($code, PASSWORD_BCRYPT);
$resetAt = date('Y-m-d H:i:s');

// Set as the user's new password
try {
    $stmt = $conn->prepare("
        UPDATE users
        SET password   = :password,
            updated_at = :updated_at
        WHERE id = :id
    ");
    $stmt->execute([
        ':password'   => $hashed,
        ':updated_at' => $resetAt,
        ':id'         => $userId,
    ]);

    // Log the reset (plain code stored so admin can see it on dashboard)
    $stmt = $conn->prepare("
        INSERT INTO password_reset_log (user_id, username, code, reset_at)
        VALUES (:user_id, :username, :code, :reset_at)
    ");
    $stmt->execute([
        ':user_id'  => $user['id'],
        ':username' => $user['username'],
        ':code'     => $code,
        ':reset_at' => $resetAt,
    ]);
    log_activity($conn, $adminName, 'Reset password', $user['username'], 'Temporary code generated');
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'status'   => 'success',
    'user_id'  => $user['id'],
    'username' => $user['username'],
    'code'     => $code,
    'reset_at' => $resetAt,
]);