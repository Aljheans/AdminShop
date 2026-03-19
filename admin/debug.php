<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbFile = __DIR__ . "/../database/database.db";

echo "<pre>";
echo "DB path: " . $dbFile . "\n";
echo "DB dir exists: " . (is_dir(dirname($dbFile)) ? "YES" : "NO") . "\n";
echo "DB dir writable: " . (is_writable(dirname($dbFile)) ? "YES" : "NO") . "\n";
echo "DB file exists: " . (file_exists($dbFile) ? "YES" : "NO") . "\n";

try {
    require_once("../config/db.php");
    echo "DB connection: OK\n";
    $users = $conn->query("SELECT id, username, role FROM users")->fetchAll();
    echo "Users found: " . count($users) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "</pre>";