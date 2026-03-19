<?php
require_once __DIR__ . '/../config/rate_limit.php';
rate_limit('sync_userdata', 120, 60);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/activity.php';

// ── Simple shared-secret auth ──
// Trim and read env key
$syncKey = trim(getenv('INTERNAL_SYNC_KEY') ?: 'sync-secret-key');

// Read Authorization header from all possible sources
$auth = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
    }
}
$auth = trim($auth);

// Check Bearer token
if ($auth !== 'Bearer ' . $syncKey) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized', 'received_auth' => $auth]);
    exit;
}

// ── Parse request body ──
$body   = json_decode(file_get_contents('php://input'), true);
$userId = (int)($body['user_id']  ?? 0);
$actor  = $body['username']       ?? "user_$userId";

if (!$userId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
    exit;
}

// Confirm user exists and is a regular user
$userRow = $conn->prepare("SELECT id, username, role FROM users WHERE id = :id");
$userRow->execute([':id' => $userId]);
$userRow = $userRow->fetch(PDO::FETCH_ASSOC);
if (!$userRow || $userRow['role'] !== 'user') {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$changes = [];

try {
    // ── Stats (coins, shards, level) ──
    if (isset($body['stats'])) {
        $s      = $body['stats'];
        $coins  = max(0, (int)($s['coins']  ?? 0));
        $shards = max(0, (int)($s['shards'] ?? 0));
        $level  = max(1, (int)($s['level']  ?? 1));

        $conn->prepare("
            INSERT INTO user_stats (user_id, coins, shards, level)
            VALUES (:uid, :coins, :shards, :level)
            ON CONFLICT(user_id) DO UPDATE SET
                coins  = :coins,
                shards = :shards,
                level  = :level
        ")->execute([':uid' => $userId, ':coins' => $coins, ':shards' => $shards, ':level' => $level]);

        $changes[] = "stats: coins=$coins shards=$shards level=$level";
    }

    // ── Resources ──
    if (isset($body['resources']) && is_array($body['resources'])) {
        foreach ($body['resources'] as $r) {
            $rid    = (int)($r['resource_id'] ?? 0);
            $amount = max(0, (int)($r['amount'] ?? 0));
            if (!$rid) continue;

            $conn->prepare("
                INSERT INTO user_resources (user_id, resource_id, amount)
                VALUES (:uid, :rid, :amt)
                ON CONFLICT(user_id, resource_id) DO UPDATE SET amount = :amt
            ")->execute([':uid' => $userId, ':rid' => $rid, ':amt' => $amount]);
        }
        $changes[] = count($body['resources']) . " resources";
    }

    // ── Factories ──
    if (isset($body['factories']) && is_array($body['factories'])) {
        foreach ($body['factories'] as $f) {
            $fid   = (int)($f['factory_id'] ?? 0);
            $level = max(0, (int)($f['level'] ?? 0));
            if (!$fid) continue;

            // Validate level <= max_level
            $maxLvl = $conn->prepare("SELECT max_level FROM factories WHERE id = :id");
            $maxLvl->execute([':id' => $fid]);
            $maxLvl = (int)($maxLvl->fetchColumn() ?: 999);
            $level  = min($level, $maxLvl);

            if ($level === 0) {
                $conn->prepare("DELETE FROM user_factories WHERE user_id=:uid AND factory_id=:fid")
                     ->execute([':uid' => $userId, ':fid' => $fid]);
            } else {
                $conn->prepare("
                    INSERT INTO user_factories (user_id, factory_id, level)
                    VALUES (:uid, :fid, :lvl)
                    ON CONFLICT(user_id, factory_id) DO UPDATE SET level = :lvl
                ")->execute([':uid' => $userId, ':fid' => $fid, ':lvl' => $level]);
            }
        }
        $changes[] = count($body['factories']) . " factories";
    }

    if ($changes) {
        log_activity($conn, $actor, 'User synced game data', $userRow['username'],
            implode(', ', $changes));
    }

    echo json_encode(['status' => 'success', 'synced' => $changes]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>