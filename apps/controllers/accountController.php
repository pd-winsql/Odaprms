<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/userModel.php';
require_once '../models/auditLogModel.php';
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

$db = (new Database())->connect();
if (!$db) { http_response_code(503); echo json_encode(['success'=>false,'message'=>'Account service is unavailable.']); exit; }
$changed = false;
try {
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT password FROM users WHERE id=? FOR UPDATE');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($currentPassword, $hash)) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }
    $changed = (new User($db))->changePassword((int) $_SESSION['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
    if (!$changed) throw new RuntimeException('Password update failed.');
    if (in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'], true)) {
        (new AuditLog($db))->recordForUser('user', (int) $_SESSION['user_id'], 'account_password_changed', 'Changed own account password.', null, null, (int) $_SESSION['user_id']);
    }
    $db->commit();
    session_regenerate_id(true);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    $changed = false;
    error_log('Account password update failed: ' . $e->getMessage());
}
echo json_encode($changed
    ? ['success' => true, 'message' => 'Password changed successfully.']
    : ['success' => false, 'message' => 'Unable to change password. Please try again.']);
