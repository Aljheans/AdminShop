<?php
header("Content-Type: application/json");

$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Missing token"]);
    exit;
}

$auth = $headers['Authorization'];
list($type, $token) = explode(" ", $auth);

if ($type !== "Bearer" || empty($token)) {
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Invalid token"]);
    exit;
}

// Optionally, forward to API for JWT verification
echo json_encode(["status"=>"ok","token"=>$token]);
?>