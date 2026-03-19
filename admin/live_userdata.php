<?php
/**
 * admin/live_userdata.php
 * Returns live users stats, resources, and factories as JSON.
 * Called by the admin dashboard every few seconds to update the UI without a full page reload.
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Forbidden']);
    exit;
}

// ── Users stats ──
$usersData = $conn->query("
    SELECT u.id, u.username, u.email, u.uid,
           COALESCE(s.coins,0)  AS coins,
           COALESCE(s.shards,0) AS shards,
           COALESCE(s.level,1)  AS level
    FROM users u
    LEFT JOIN user_stats s ON s.user_id = u.id
    WHERE u.role = 'user'
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($usersData as &$u) {
    $u['id']     = (int)$u['id'];
    $u['coins']  = (int)$u['coins'];
    $u['shards'] = (int)$u['shards'];
    $u['level']  = (int)$u['level'];
}
unset($u);

// ── User resources ──
$resRows = $conn->query("
    SELECT u.id AS user_id,
           r.id AS resource_id,
           COALESCE(ur.amount, 0) AS amount
    FROM users u
    CROSS JOIN resources r
    LEFT JOIN user_resources ur ON ur.user_id = u.id AND ur.resource_id = r.id
    WHERE u.role = 'user'
    ORDER BY u.id, r.name
")->fetchAll(PDO::FETCH_ASSOC);

$resources = [];
foreach ($resRows as $r) {
    $resources[(int)$r['user_id']][(int)$r['resource_id']] = (int)$r['amount'];
}

$facRows = $conn->query("
    SELECT u.id AS user_id,
           f.id AS factory_id,
           COALESCE(uf.level, 0) AS level
    FROM users u
    CROSS JOIN factories f
    LEFT JOIN user_factories uf ON uf.user_id = u.id AND uf.factory_id = f.id
    WHERE u.role = 'user'
    ORDER BY u.id, f.name
")->fetchAll(PDO::FETCH_ASSOC);

$factories = [];
foreach ($facRows as $f) {
    $factories[(int)$f['user_id']][(int)$f['factory_id']] = (int)$f['level'];
}

echo json_encode([
    'status'    => 'success',
    'ts'        => time(),
    'users'     => $usersData,
    'resources' => $resources,
    'factories' => $factories,
]);
?>