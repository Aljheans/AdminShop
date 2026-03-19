<?php
/**
 * api/change_password.php
 *
 * Called after OTP login to set a permanent new password.
 * Requires the session flag must_change_password = true.
 *
 * POST body: { "new_password": "...", "confirm_password": "..." }
 *
 * Response:
 *   { "status": "success", "message": "Password updated." }
 *   { "status": "error",   "message": "..." }
 */

header('Content-Type: application/json');
session_start();

// Guard: must have come through OTP verification
if (empty($_SESSION['user_id']) || empty($_SESSION['must_change_password'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please complete the OTP step first.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$data            = json_decode(file_get_contents('php://input'), true);
$newPassword     = $data['new_password']     ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

if (empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'Both fields are required.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.']);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
    exit;
}

$hash = password_hash($newPassword, PASSWORD_BCRYPT);
$stmt = $conn->prepare("
    UPDATE users
    SET password   = :password,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = :id
");
$stmt->execute([':password' => $hash, ':id' => $_SESSION['user_id']]);

// Clear the forced-change flag so normal session continues
unset($_SESSION['must_change_password']);

echo json_encode(['status' => 'success', 'message' => 'Password updated successfully.']);