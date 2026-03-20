<?php
require_once __DIR__ . "/../config/gateway_guard.php";
header('Content-Type: application/json');
require_once __DIR__ . '/../config/rate_limit.php';
rate_limit('login', 5, 60);

$data     = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

try {
    require_once __DIR__ . "/../config/db.php";

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        $update = $conn->prepare("
            UPDATE users
            SET is_online = 1
            WHERE id = :id
        ");
        $update->execute([':id' => $user['id']]);

        echo json_encode([
            "status"   => "success",
            "user_id"  => $user['id'],
            "username" => $user['username'],
            "role"     => $user['role']
        ]);

    } else {
        echo json_encode([
            "status"  => "error",
            "message" => "Invalid username or password"
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
}