<?php
require_once __DIR__ . "/../config/gateway_guard.php";
require_once __DIR__ . '/../config/rate_limit.php';
rate_limit('register', 30, 60);
header("Content-Type: application/json");
require_once("../config/db.php");

$data     = json_decode(file_get_contents("php://input"), true);
$username = trim($data['username'] ?? '');
$email    = trim($data['email']    ?? '');
$password = $data['password'] ?? '';

// Basic validation
if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Username and password are required."]);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters."]);
    exit;
}

// Email defaults to a placeholder if not provided (column is NOT NULL UNIQUE)
if (!$email) {
    $email = $username . '@user.local';
}

// Generate a unique 4-digit UID for the user (5000-9999 range, separate from admin UIDs)
$uid = null;
for ($attempt = 0; $attempt < 20; $attempt++) {
    $candidate = (string)rand(5000, 9999);
    $check = $conn->prepare("SELECT id FROM users WHERE uid = :uid");
    $check->execute([':uid' => $candidate]);
    if (!$check->fetch()) {
        $uid = $candidate;
        break;
    }
}

try {
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password, role, uid)
        VALUES (:username, :email, :password, 'user', :uid)
    ");
    $stmt->execute([
        ':username' => $username,
        ':email'    => $email,
        ':password' => password_hash($password, PASSWORD_BCRYPT),
        ':uid'      => $uid,
    ]);

    echo json_encode(["status" => "success"]);

} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (str_contains($msg, 'UNIQUE') && str_contains($msg, 'username')) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username already taken."]);
    } elseif (str_contains($msg, 'UNIQUE') && str_contains($msg, 'email')) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Email already registered."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Registration failed. Please try again."]);
    }
}
?>