<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/userModel.php';
require_once '../helpers/csrf.php';

header('Content-Type: application/json');

$allowedRoles = ['Admin', 'Dental Assistant', 'Patient'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}
//checks if action is changePassword and if the request method is POST, otherwise returns a 405 error
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'changePassword') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (!validate_csrf()) {
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
    exit;
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all password fields.']);
    exit;
}
if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
    exit;
}
if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters and include both letters and numbers.']);
    exit;
}
if (hash_equals($currentPassword, $newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Your new password must be different from your current password.']);
    exit;
}

$userModel = new User((new Database())->connect());
$user = $userModel->getUserById((int) $_SESSION['user_id']);
if (!$user || !password_verify($currentPassword, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
    exit;
}

$changed = $userModel->changePassword((int) $_SESSION['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
echo json_encode($changed
    ? ['success' => true, 'message' => 'Password changed successfully.']
    : ['success' => false, 'message' => 'Unable to change password. Please try again.']);
