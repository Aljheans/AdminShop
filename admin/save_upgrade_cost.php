<?php
session_start();
header('Content-Type: application/json');
require_once("../config/db.php");
require_once("../config/activity.php");
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit;
}
$adminName = $_SESSION['username'] ?? 'admin';
$b = json_decode(file_get_contents('php://input'), true);
$factoryId     = (int)($b['factory_id']           ?? 0);
$level         = (int)($b['level']                ?? 0);
$coinsCost     = max(0, (int)($b['coins_cost']     ?? 0));
$upgradeSecs   = max(0, (int)($b['upgrade_time_seconds'] ?? 0));
$resourceCosts = $b['resource_costs']             ?? [];

if (!$factoryId || !$level) { echo json_encode(['status'=>'error','message'=>'Invalid params']); exit; }

$fac = $conn->prepare("SELECT name FROM factories WHERE id=:id");
$fac->execute([':id'=>$factoryId]);
$fac = $fac->fetch(PDO::FETCH_ASSOC);
if (!$fac) { echo json_encode(['status'=>'error','message'=>'Factory not found']); exit; }

try {
    $conn->prepare("INSERT INTO factory_upgrade_costs (factory_id,level,coins_cost,upgrade_time_seconds) VALUES(:fid,:lvl,:cc,:ut) ON CONFLICT(factory_id,level) DO UPDATE SET coins_cost=:cc, upgrade_time_seconds=:ut")
         ->execute([':fid'=>$factoryId,':lvl'=>$level,':cc'=>$coinsCost,':ut'=>$upgradeSecs]);

    $conn->prepare("DELETE FROM factory_upgrade_resource_costs WHERE factory_id=:fid AND level=:lvl")
         ->execute([':fid'=>$factoryId,':lvl'=>$level]);

    foreach ($resourceCosts as $rc) {
        $rid = (int)($rc['resource_id'] ?? 0);
        $amt = max(0, (int)($rc['amount'] ?? 0));
        if ($rid && $amt > 0) {
            $conn->prepare("INSERT OR IGNORE INTO factory_upgrade_resource_costs (factory_id,level,resource_id,amount) VALUES(:fid,:lvl,:rid,:amt)")
                 ->execute([':fid'=>$factoryId,':lvl'=>$level,':rid'=>$rid,':amt'=>$amt]);
        }
    }

    log_activity($conn,$adminName,'Updated upgrade cost',$fac['name'],"level=$level, coins=$coinsCost, time={$upgradeSecs}s, resources=".count($resourceCosts));
    echo json_encode(['status'=>'success']);
} catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
?>