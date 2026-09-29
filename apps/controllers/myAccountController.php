<?php
require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../models/staffModel.php';
require_once __DIR__ . '/../models/auditLogModel.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/authorization.php';

header('Content-Type: application/json');
vdRequireRoleJson(['Admin', 'Dental Assistant']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'updateMyAccount') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
if (!validate_csrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$staff = new Staff((new Database())->connect());
$old = $staff->getMyAccount($userId);
if (!$old) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    exit;
}

$fields = [];
foreach (['firstname', 'middlename', 'lastname', 'phone_number', 'email'] as $key) {
    $fields[$key] = trim((string) ($_POST[$key] ?? ''));
}
if ($fields['firstname'] === '' || $fields['lastname'] === '' || $fields['phone_number'] === '' || $fields['email'] === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Complete all required fields.']);
    exit;
}
foreach (['firstname', 'middlename', 'lastname'] as $key) {
    if (mb_strlen($fields[$key]) > 100) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Names must be 100 characters or fewer.']);
        exit;
    }
}
if (!preg_match('/^\d{11}$/', $fields['phone_number'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Phone number must contain exactly 11 digits.']);
    exit;
}
if (strlen($fields['email']) > 255 || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid email address.']);
    exit;
}

$emailChanged = $fields['email'] !== $old['login_email'];
if ($emailChanged && !password_verify((string) ($_POST['current_password'] ?? ''), $old['password'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter your current password to change your email.', 'field' => 'current_password']);
    exit;
}

$oldValues = [
    'firstname' => (string) ($old['firstname'] ?? ''), 'middlename' => (string) ($old['middlename'] ?? ''),
    'lastname' => (string) ($old['lastname'] ?? ''), 'phone_number' => (string) ($old['phone_number'] ?? ''),
    'email' => $old['login_email'],
];
if ($oldValues === $fields) {
    echo json_encode(['success' => true, 'message' => 'No changes to save.']);
    exit;
}

$result = $staff->updateMyAccount($userId, $fields);
if (!$result['success']) {
    http_response_code($result['message'] === 'This email is already in use.' ? 422 : 500);
    echo json_encode($result);
    exit;
}

$_SESSION['email'] = $fields['email'];
$_SESSION['display_name'] = trim(implode(' ', array_filter([$fields['firstname'], $fields['middlename'], $fields['lastname']])));
try {
    $audit = new AuditLog((new Database())->connect());
    $audit->recordForUser('staff', (int) $result['staff_id'], 'staff_profile_updated', 'Updated own staff account details.', $oldValues, $fields, $userId);
} catch (Throwable $e) {
    error_log('My Account audit error: ' . $e->getMessage());
}

echo json_encode(['success' => true, 'message' => 'Account details saved.', 'display_name' => $_SESSION['display_name']]);
