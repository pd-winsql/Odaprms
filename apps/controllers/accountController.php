<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/userModel.php';

header('Content-Type: application/json');

$allowedRoles = ['Admin', 'Dental Assistant', 'Patient'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'changePassword') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$expectedToken = (string) ($_SESSION['csrf_token'] ?? '');
$providedToken = (string) ($_POST['csrf_token'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
    exit;
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all password fields.']); exit;
}
if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']); exit;
}
if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters and include both letters and numbers.']); exit;
}
if (hash_equals($currentPassword, $newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Your new password must be different from your current password.']); exit;
}

$userModel = new User((new Database())->connect());
$user = $userModel->getUserById((int) $_SESSION['user_id']);
if (!$user || !password_verify($currentPassword, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']); exit;
}

$changed = $userModel->changePassword((int) $_SESSION['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
echo json_encode($changed
    ? ['success' => true, 'message' => 'Password changed successfully.']
    : ['success' => false, 'message' => 'Unable to change password. Please try again.']);

