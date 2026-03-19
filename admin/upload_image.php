<?php
/**
 * admin/upload_image.php
 * Stores images as base64 data URIs directly in the DB.
 * This avoids losing images when the server filesystem resets (e.g. Render ephemeral disk).
 *
 * POST (multipart/form-data):
 *   file  — the image file
 *   type  — "resource" | "factory"
 *   id    — the resource or factory ID
 */
session_start();
header('Content-Type: application/json');
require_once "../config/db.php";
require_once "../config/activity.php";

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit;
}

$adminName = $_SESSION['username'] ?? 'admin';
$type      = $_POST['type'] ?? '';
$id        = (int)($_POST['id'] ?? 0);

if (!in_array($type, ['resource','factory']) || !$id) {
    echo json_encode(['status'=>'error','message'=>"Invalid type or id (type='$type', id=$id)"]); exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status'=>'error','message'=>'No file uploaded or upload error: ' . ($_FILES['file']['error'] ?? 'missing')]); exit;
}

$file    = $_FILES['file'];
$mime    = mime_content_type($file['tmp_name']);
$allowed = ['image/png'];

if (!in_array($mime, $allowed)) {
    echo json_encode(['status'=>'error','message'=>'Only PNG files are accepted']); exit;
}

// Read file bytes
$raw = file_get_contents($file['tmp_name']);
if ($raw === false) {
    echo json_encode(['status'=>'error','message'=>'Could not read uploaded file']); exit;
}

// Resize to max 256px using GD to keep DB row size small
if (function_exists('imagecreatefromstring')) {
    $src = @imagecreatefromstring($raw);
    if ($src) {
        $ow = imagesx($src);
        $oh = imagesy($src);
        $max = 256;
        if ($ow > $max || $oh > $max) {
            $scale = min($max / $ow, $max / $oh);
            $nw = (int)round($ow * $scale);
            $nh = (int)round($oh * $scale);
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
            ob_start();
            imagepng($dst, null, 6);
            $raw  = ob_get_clean();
            $mime = 'image/png';
            imagedestroy($dst);
        }
        imagedestroy($src);
    }
}

$dataUri = 'data:' . $mime . ';base64,' . base64_encode($raw);
$table   = ($type === 'resource') ? 'resources' : 'factories';

try {
    $conn->prepare("UPDATE {$table} SET image_url = :uri WHERE id = :id")
         ->execute([':uri' => $dataUri, ':id' => $id]);

    log_activity($conn, $adminName, "Uploaded {$type} image", (string)$id,
        'base64, ' . round(strlen($dataUri) / 1024, 1) . 'KB');

    echo json_encode(['status' => 'success', 'image_url' => $dataUri]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>