<?php
session_start();
header('Content-Type: application/json');
require_once("../config/db.php");
require_once("../config/activity.php");
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
$adminName = $_SESSION['username'] ?? 'admin';
$b = json_decode(file_get_contents('php://input'), true);
$userId    = (int)($b['user_id']    ?? 0);
$factoryId = (int)($b['factory_id'] ?? 0);
$level     = max(0, (int)($b['level'] ?? 0));
if (!$userId || !$factoryId) { echo json_encode(['status'=>'error','message'=>'Invalid params']); exit; }
$target = $conn->prepare("SELECT username FROM users WHERE id=:id AND role='user'");
$target->execute([':id'=>$userId]);
$target = $target->fetch(PDO::FETCH_ASSOC);
if (!$target) { echo json_encode(['status'=>'error','message'=>'User not found']); exit; }
$fac = $conn->prepare("SELECT name, max_level FROM factories WHERE id=:id");
$fac->execute([':id'=>$factoryId]);
$fac = $fac->fetch(PDO::FETCH_ASSOC);
if (!$fac) { echo json_encode(['status'=>'error','message'=>'Factory not found']); exit; }
$level = min($level, $fac['max_level']);
try {
    if ($level === 0) {
        $conn->prepare("DELETE FROM user_factories WHERE user_id=:u AND factory_id=:f")
             ->execute([':u'=>$userId,':f'=>$factoryId]);
    } else {
        $conn->prepare("INSERT INTO user_factories (user_id,factory_id,level) VALUES(:u,:f,:l) ON CONFLICT(user_id,factory_id) DO UPDATE SET level=:l")
             ->execute([':u'=>$userId,':f'=>$factoryId,':l'=>$level]);
    }
    log_activity($conn,$adminName,'Updated user factory',$target['username'],"factory={$fac['name']}, level=$level");
    echo json_encode(['status'=>'success']);
} catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
?>