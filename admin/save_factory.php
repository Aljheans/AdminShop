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
$id                  = (int)($b['id']                  ?? 0);
$name                = trim($b['name']                ?? '');
$resourceId          = (int)($b['resource_id']         ?? 0);
$baseProductionRate  = (float)($b['base_production_rate']  ?? 1.0);
$productionPerLevel  = (float)($b['production_per_level']  ?? 0.5);
$maxLevel            = max(1, (int)($b['max_level']    ?? 10));
$description         = trim($b['description']         ?? '');
$costMultiplier      = max(1.0, (float)($b['cost_multiplier'] ?? 1.5));
$baseUpgradeTime     = max(0, (int)($b['base_upgrade_time'] ?? 60));
$upgradeTimeMultiplier = max(1.0, (float)($b['upgrade_time_multiplier'] ?? 1.5));
$delete              = !empty($b['_delete']);

if ($delete) {
    if (!$id) { echo json_encode(['status'=>'error','message'=>'Invalid ID']); exit; }
    try {
        $row = $conn->prepare("SELECT name, image_url FROM factories WHERE id=:id");
        $row->execute([':id'=>$id]); $row=$row->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['image_url'])) {
            $oldFile = __DIR__ . '/../' . ltrim($row['image_url'], '/');
            if (file_exists($oldFile)) @unlink($oldFile);
        }
        $conn->prepare("DELETE FROM factories WHERE id=:id")->execute([':id'=>$id]);
        log_activity($conn,$adminName,'Deleted factory',$row['name']??'');
        echo json_encode(['status'=>'success']);
    } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
    exit;
}

if (empty($name) || !$resourceId) { echo json_encode(['status'=>'error','message'=>'Name and resource are required']); exit; }

try {
    if ($id) {
        $conn->prepare("UPDATE factories SET name=:name,resource_id=:rid,base_production_rate=:bpr,production_per_level=:ppl,max_level=:ml,description=:desc,cost_multiplier=:cm,base_upgrade_time=:but,upgrade_time_multiplier=:utm WHERE id=:id")
             ->execute([':name'=>$name,':rid'=>$resourceId,':bpr'=>$baseProductionRate,':ppl'=>$productionPerLevel,':ml'=>$maxLevel,':desc'=>$description,':cm'=>$costMultiplier,':but'=>$baseUpgradeTime,':utm'=>$upgradeTimeMultiplier,':id'=>$id]);
        $newId = $id;
        log_activity($conn,$adminName,'Updated factory',$name,"base_rate=$baseProductionRate, per_level=$productionPerLevel, max_level=$maxLevel, cost_mult=$costMultiplier, base_time={$baseUpgradeTime}s");
    } else {
        $conn->prepare("INSERT INTO factories (name,resource_id,base_production_rate,production_per_level,max_level,description,image_url,cost_multiplier,base_upgrade_time,upgrade_time_multiplier) VALUES(:name,:rid,:bpr,:ppl,:ml,:desc,:url,:cm,:but,:utm)")
             ->execute([':name'=>$name,':rid'=>$resourceId,':bpr'=>$baseProductionRate,':ppl'=>$productionPerLevel,':ml'=>$maxLevel,':desc'=>$description,':url'=>'',':cm'=>$costMultiplier,':but'=>$baseUpgradeTime,':utm'=>$upgradeTimeMultiplier]);
        $newId = (int)$conn->lastInsertId();
        log_activity($conn,$adminName,'Added factory',$name,'');
    }
    echo json_encode(['status'=>'success','id'=>$newId]);
} catch (Exception $e) {
    if (str_contains($e->getMessage(),'UNIQUE')) { echo json_encode(['status'=>'error','message'=>'Factory name already exists']); }
    else { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
}
?>