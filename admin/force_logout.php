<?php
/**
 * admin/force_logout.php
 * Force-logout a user, all users, or all admins via the FastAPI WebSocket gateway.
 * The gateway tracks logged-in users in memory; we call DELETE /logout/{user_id}
 * or POST /logout-all with a role filter.
 *
 * Called via AJAX (POST) with JSON body:
 *   { "mode": "single",  "user_id": 123 }
 *   { "mode": "all_users" }
 *   { "mode": "all_admins" }
 */
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/activity.php';

// ── Auth guard ──
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

$adminName = $_SESSION['username'] ?? 'admin';
$body      = json_decode(file_get_contents('php://input'), true);
$mode      = $body['mode'] ?? '';

define('API_BASE', 'https://gatewayv1.onrender.com');

/**
 * POST to FastAPI /admin/force-logout
 * The gateway must expose this endpoint (see main.py additions below).
 */
function apiPost(string $path, array $payload): array {
    $ch = curl_init(API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($body, true);
    return $decoded ?: ['status' => 'error', 'message' => "HTTP $code — $body"];
}

try {
    switch ($mode) {

        // ── Single user logout ──
        case 'single':
            $userId = (int)($body['user_id'] ?? 0);
            if (!$userId) throw new Exception('Missing user_id');

            // Lookup username for logging
            $u = $conn->prepare("SELECT username, role FROM users WHERE id = :id");
            $u->execute([':id' => $userId]);
            $u = $u->fetch(PDO::FETCH_ASSOC);
            if (!$u) throw new Exception("User #$userId not found");

            $result = apiPost('/admin/force-logout', ['user_id' => $userId]);

            if ($result['status'] === 'success' || ($result['logged_out'] ?? 0) >= 0) {
                log_activity($conn, $adminName, 'Force logout user',
                    $u['username'], "user_id=$userId role={$u['role']}");
                echo json_encode(['status' => 'success',
                    'message' => "Logged out {$u['username']}",
                    'logged_out' => $result['logged_out'] ?? 1]);
            } else {
                // User may not be online — that's fine, still report success
                log_activity($conn, $adminName, 'Force logout user (was offline)',
                    $u['username'], "user_id=$userId");
                echo json_encode(['status' => 'success',
                    'message' => "{$u['username']} was not online"]);
            }
            break;

        // ── Logout all users (role=user) ──
        case 'all_users':
            $result = apiPost('/admin/force-logout-all', ['role' => 'user']);
            $count  = $result['logged_out'] ?? 0;
            log_activity($conn, $adminName, 'Force logout ALL users', '',
                "kicked=$count");
            echo json_encode(['status' => 'success',
                'message' => "Logged out $count online user(s)",
                'logged_out' => $count]);
            break;

        // ── Logout all admins (role=admin, but NOT self) ──
        case 'all_admins':
            $myId  = (int)($_SESSION['user_id'] ?? 0);
            $result = apiPost('/admin/force-logout-all', [
                'role'          => 'admin',
                'exclude_user_id' => $myId,   // don't kick yourself
            ]);
            $count = $result['logged_out'] ?? 0;
            log_activity($conn, $adminName, 'Force logout ALL admins', '',
                "kicked=$count exclude_self=true");
            echo json_encode(['status' => 'success',
                'message' => "Logged out $count online admin(s)",
                'logged_out' => $count]);
            break;

        default:
            throw new Exception("Invalid mode: $mode");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>