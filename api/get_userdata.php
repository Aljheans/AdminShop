<?php
require_once __DIR__ . '/../config/rate_limit.php';
rate_limit('get_userdata', 120, 60);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

// ── Shared-secret auth ──
$syncKey = getenv('INTERNAL_SYNC_KEY') ?: 'sync-secret-key';
$headers = getallheaders();

$auth = $headers['Authorization']
    ?? $headers['authorization']
    ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if ($auth !== 'Bearer ' . $syncKey) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)($_GET['user_id'] ?? 0);
if (!$userId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
    exit;
}

// ── Fetch user — no role restriction, any valid user can load game data ──
$userStmt = $conn->prepare("SELECT id, username, uid, role FROM users WHERE id = :id");
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => "User #$userId not found in database"]);
    exit;
}

// ── Seed user_stats row if it doesn't exist yet ──
$conn->prepare("
    INSERT OR IGNORE INTO user_stats (user_id, coins, shards, level)
    VALUES (:uid, 0, 0, 1)
")->execute([':uid' => $userId]);

// ── Stats ──
$statsStmt = $conn->prepare("SELECT coins, shards, level FROM user_stats WHERE user_id = :id");
$statsStmt->execute([':id' => $userId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['coins' => 0, 'shards' => 0, 'level' => 1];

// ── Resources — all defined resources, with this user's amounts ──
$resStmt = $conn->prepare("
    SELECT r.id AS resource_id, r.name,
           COALESCE(r.image_url,'') AS image_url,
           r.description,
           COALESCE(ur.amount, 0) AS amount
    FROM resources r
    LEFT JOIN user_resources ur ON ur.resource_id = r.id AND ur.user_id = :uid
    ORDER BY r.name
");
$resStmt->execute([':uid' => $userId]);
$resources = $resStmt->fetchAll(PDO::FETCH_ASSOC);

$websiteBase = getenv('WEBSITE_BASE_URL') ?: '';

// If no env var set, derive base URL from the incoming request itself
if (empty($websiteBase)) {
    $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host        = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $scriptDir   = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $websiteBase = $scheme . '://' . $host . $scriptDir;
}
$websiteBase = rtrim($websiteBase, '/');

foreach ($resources as &$res) {
    $res['resource_id'] = (int)$res['resource_id'];
    $res['amount']      = (int)$res['amount'];
    // Build image URL: data URIs are used as-is; file paths get the absolute base prepended
    $iu = $res['image_url'];
    $res['image_url'] = ($iu !== '')
        ? (str_starts_with($iu, 'data:') ? $iu : rtrim($websiteBase, '/') . '/' . ltrim($iu, '/'))
        : '';
}
unset($res);

// ── Factories — all defined factories, with this user's level ──
$facStmt = $conn->prepare("
    SELECT f.id AS factory_id, f.name,
           COALESCE(f.image_url,'') AS image_url,
           f.description,
           f.resource_id,
           r.name AS resource_name,
           COALESCE(r.image_url,'') AS resource_image_url,
           f.base_production_rate, f.production_per_level, f.max_level,
           COALESCE(f.cost_multiplier, 1.5)         AS cost_multiplier,
           COALESCE(f.base_upgrade_time, 60)         AS base_upgrade_time,
           COALESCE(f.upgrade_time_multiplier, 1.5)  AS upgrade_time_multiplier,
           COALESCE(uf.level, 0) AS level
    FROM factories f
    JOIN resources r ON r.id = f.resource_id
    LEFT JOIN user_factories uf ON uf.factory_id = f.id AND uf.user_id = :uid
    ORDER BY f.name
");
$facStmt->execute([':uid' => $userId]);
$factories = $facStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Attach upgrade costs per factory using prepared statements ──
foreach ($factories as &$fac) {
    $fid = (int)$fac['factory_id'];

    // Build image URLs: data URIs used as-is; file paths get absolute base prepended
    $iu = $fac['image_url'];
    $fac['image_url'] = ($iu !== '')
        ? (str_starts_with($iu, 'data:') ? $iu : rtrim($websiteBase, '/') . '/' . ltrim($iu, '/'))
        : '';
    $riu = $fac['resource_image_url'];
    $fac['resource_image_url'] = ($riu !== '')
        ? (str_starts_with($riu, 'data:') ? $riu : rtrim($websiteBase, '/') . '/' . ltrim($riu, '/'))
        : '';

    $costStmt = $conn->prepare("
        SELECT uc.level, uc.coins_cost,
               COALESCE(uc.upgrade_time_seconds, 0) AS upgrade_time_seconds,
               GROUP_CONCAT(rc.resource_id || ':' || rc.amount) AS res_costs
        FROM factory_upgrade_costs uc
        LEFT JOIN factory_upgrade_resource_costs rc
               ON rc.factory_id = uc.factory_id AND rc.level = uc.level
        WHERE uc.factory_id = :fid
        GROUP BY uc.level
        ORDER BY uc.level
    ");
    $costStmt->execute([':fid' => $fid]);
    $costRows = $costStmt->fetchAll(PDO::FETCH_ASSOC);

    $costs = [];
    foreach ($costRows as $row) {
        $resCosts = [];
        if (!empty($row['res_costs'])) {
            foreach (explode(',', $row['res_costs']) as $rc) {
                [$rid, $amt] = explode(':', $rc);
                $resCosts[] = ['resource_id' => (int)$rid, 'amount' => (int)$amt];
            }
        }
        $costs[(int)$row['level']] = [
            'coins_cost'           => (int)$row['coins_cost'],
            'upgrade_time_seconds' => (int)$row['upgrade_time_seconds'],
            'resource_costs'       => $resCosts,
        ];
    }

    $fac['upgrade_costs']           = $costs;
    $fac['factory_id']              = (int)$fac['factory_id'];
    $fac['resource_id']             = (int)$fac['resource_id'];
    $fac['level']                   = (int)$fac['level'];
    $fac['max_level']               = (int)$fac['max_level'];
    $fac['base_production_rate']    = (float)$fac['base_production_rate'];
    $fac['production_per_level']    = (float)$fac['production_per_level'];
    $fac['cost_multiplier']         = (float)$fac['cost_multiplier'];
    $fac['base_upgrade_time']       = (int)$fac['base_upgrade_time'];
    $fac['upgrade_time_multiplier'] = (float)$fac['upgrade_time_multiplier'];
}
unset($fac);

echo json_encode([
    'status'    => 'success',
    'user'      => [
        'id'       => (int)$user['id'],
        'username' => $user['username'],
        'uid'      => $user['uid'],
        'role'     => $user['role'],
    ],
    'stats'     => [
        'coins'  => (int)$stats['coins'],
        'shards' => (int)$stats['shards'],
        'level'  => (int)$stats['level'],
    ],
    'resources' => $resources,
    'factories' => $factories,
]);
?>