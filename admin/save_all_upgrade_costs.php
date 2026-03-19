<?php
session_start();
header('Content-Type: application/json');
require_once("../config/db.php");
require_once("../config/activity.php");

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit;
}

$adminName = $_SESSION['username'] ?? 'admin';
$b         = json_decode(file_get_contents('php://input'), true);
$factoryId = (int)($b['factory_id'] ?? 0);
$levels    = $b['levels'] ?? [];   // array of {level, coins_cost, upgrade_time_seconds, resource_costs[]}

if (!$factoryId || empty($levels)) {
    echo json_encode(['status'=>'error','message'=>'Missing factory_id or levels']); exit;
}

$fac = $conn->prepare("SELECT name FROM factories WHERE id=:id");
$fac->execute([':id'=>$factoryId]);
$fac = $fac->fetch(PDO::FETCH_ASSOC);
if (!$fac) { echo json_encode(['status'=>'error','message'=>'Factory not found']); exit; }

try {
    $conn->beginTransaction();

    $upsertCost = $conn->prepare("
        INSERT INTO factory_upgrade_costs (factory_id, level, coins_cost, upgrade_time_seconds)
        VALUES (:fid, :lvl, :cc, :ut)
        ON CONFLICT(factory_id, level) DO UPDATE
        SET coins_cost=:cc, upgrade_time_seconds=:ut
    ");
    $delRes  = $conn->prepare("DELETE FROM factory_upgrade_resource_costs WHERE factory_id=:fid AND level=:lvl");
    $insRes  = $conn->prepare("INSERT OR IGNORE INTO factory_upgrade_resource_costs (factory_id,level,resource_id,amount) VALUES(:fid,:lvl,:rid,:amt)");

    foreach ($levels as $row) {
        $lvl      = (int)($row['level']                ?? 0);
        $coins    = max(0, (int)($row['coins_cost']         ?? 0));
        $secs     = max(0, (int)($row['upgrade_time_seconds'] ?? 0));
        $resCosts = $row['resource_costs'] ?? [];

        if (!$lvl) continue;

        $upsertCost->execute([':fid'=>$factoryId,':lvl'=>$lvl,':cc'=>$coins,':ut'=>$secs]);

        $delRes->execute([':fid'=>$factoryId,':lvl'=>$lvl]);
        foreach ($resCosts as $rc) {
            $rid = (int)($rc['resource_id'] ?? 0);
            $amt = max(0, (int)($rc['amount'] ?? 0));
            if ($rid && $amt > 0) {
                $insRes->execute([':fid'=>$factoryId,':lvl'=>$lvl,':rid'=>$rid,':amt'=>$amt]);
            }
        }
    }

    $conn->commit();
    log_activity($conn, $adminName, 'Saved all upgrade costs', $fac['name'], count($levels).' levels');
    echo json_encode(['status'=>'success','saved'=>count($levels)]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
?>