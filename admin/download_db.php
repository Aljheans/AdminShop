<?php
session_start();
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); exit('Access denied');
}
require_once("../config/db.php");
require_once("../config/activity.php");

$dbPath = __DIR__ . '/../database/database.db';
if (!file_exists($dbPath)) { http_response_code(404); exit('Database not found'); }

log_activity($conn, $_SESSION['username'] ?? 'admin', 'Downloaded database', 'database.db', '');

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="database_' . date('Ymd_His') . '.db"');
header('Content-Length: ' . filesize($dbPath));
readfile($dbPath);
exit;
?>