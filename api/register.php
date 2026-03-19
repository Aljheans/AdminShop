<?php
require_once __DIR__ . '/../config/rate_limit.php';
rate_limit('register', 10, 60);
header("Content-Type: application/json");
require_once("../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);
$username = $data['username'];
$password = password_hash($data['password'], PASSWORD_BCRYPT);

try {
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
    $stmt->bindParam(":username", $username);
    $stmt->bindParam(":password", $password);
    $stmt->execute();

    echo json_encode(["status" => "success"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "User exists or error"]);
}
?>