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
$id          = (int)($b['id']          ?? 0);
$name        = trim($b['name']        ?? '');
$description = trim($b['description'] ?? '');
$delete      = !empty($b['_delete']);

if ($delete) {
    if (!$id) { echo json_encode(['status'=>'error','message'=>'Invalid ID']); exit; }
    try {
        $row = $conn->prepare("SELECT name, image_url FROM resources WHERE id=:id");
        $row->execute([':id'=>$id]);
        $row = $row->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['image_url'])) {
            $oldFile = __DIR__ . '/../' . ltrim($row['image_url'], '/');
            if (file_exists($oldFile)) @unlink($oldFile);
        }
        $conn->prepare("DELETE FROM resources WHERE id=:id")->execute([':id'=>$id]);
        log_activity($conn,$adminName,'Deleted resource',$row['name']??'');
        echo json_encode(['status'=>'success']);
    } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
    exit;
}

if (empty($name)) { echo json_encode(['status'=>'error','message'=>'Name is required']); exit; }

try {
    if ($id) {
        $conn->prepare("UPDATE resources SET name=:name, description=:desc WHERE id=:id")
             ->execute([':name'=>$name,':desc'=>$description,':id'=>$id]);
        $newId = $id;
        log_activity($conn,$adminName,'Updated resource',$name,'');
    } else {
        $conn->prepare("INSERT INTO resources (name,description,image_url) VALUES(:name,:desc,:url)")
             ->execute([':name'=>$name,':desc'=>$description,':url'=>'']);
        $newId = (int)$conn->lastInsertId();
        log_activity($conn,$adminName,'Added resource',$name,'');
    }
    echo json_encode(['status'=>'success','id'=>$newId]);
} catch (Exception $e) {
    if (str_contains($e->getMessage(),'UNIQUE')) { echo json_encode(['status'=>'error','message'=>'Resource name already exists']); }
    else { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
}
?>