<?php
session_start();
header('Content-Type: application/json');
require_once("../config/db.php");
require_once("../config/activity.php");
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
$adminName = $_SESSION['username'] ?? 'admin';
$b = json_decode(file_get_contents('php://input'), true);
$userId     = (int)($b['user_id']     ?? 0);
$resourceId = (int)($b['resource_id'] ?? 0);
$amount     = max(0, (int)($b['amount'] ?? 0));
if (!$userId || !$resourceId) { echo json_encode(['status'=>'error','message'=>'Invalid params']); exit; }
$target = $conn->prepare("SELECT username FROM users WHERE id=:id AND role='user'");
$target->execute([':id'=>$userId]);
$target = $target->fetch(PDO::FETCH_ASSOC);
if (!$target) { echo json_encode(['status'=>'error','message'=>'User not found']); exit; }
$res = $conn->prepare("SELECT name FROM resources WHERE id=:id");
$res->execute([':id'=>$resourceId]);
$res = $res->fetch(PDO::FETCH_ASSOC);
if (!$res) { echo json_encode(['status'=>'error','message'=>'Resource not found']); exit; }
try {
    $conn->prepare("INSERT INTO user_resources (user_id,resource_id,amount) VALUES(:u,:r,:a) ON CONFLICT(user_id,resource_id) DO UPDATE SET amount=:a")
         ->execute([':u'=>$userId,':r'=>$resourceId,':a'=>$amount]);
    log_activity($conn,$adminName,'Updated user resource',$target['username'],"resource={$res['name']}, amount=$amount");
    echo json_encode(['status'=>'success']);
} catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
?>