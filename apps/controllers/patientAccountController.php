<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../models/patientModel.php';
require_once __DIR__ . '/../models/accountEmailChangeModel.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/authorization.php';
require_once __DIR__ . '/../support/IdentityInput.php';

header('Content-Type: application/json');
vdRequireRoleJson(['Patient']);

$action = (string) ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($action, ['updateMyAccount', 'requestEmailChange', 'verifyEmailChange'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
if (!validate_csrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
    exit;
}

$db = (new Database())->connect();
if (!$db) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Account service is unavailable.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$patients = new Patient($db);
$old = $patients->getPatientByUserId($userId);
if (!$old) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    exit;
}

if ($action !== 'updateMyAccount') {
    $changes = new AccountEmailChange($db);
    if ($action === 'requestEmailChange') {
        require_once __DIR__ . '/../../config/mailer.php';
        $result = $changes->request(
            $userId,
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['current_password'] ?? ''),
            static function (string $email, string $code) use ($old): bool {
                $name = trim(($old['firstname'] ?? '') . ' ' . ($old['lastname'] ?? ''));
                return !empty(sendTemplateEmail($email, $name, 'account_email_change', $code)['success']);
            }
        );
    } else {
        $result = $changes->verify($userId, trim((string) ($_POST['code'] ?? '')), (string) ($_POST['current_password'] ?? ''));
        if ($result['success']) {
            $_SESSION['email'] = $result['email'];
            session_regenerate_id(true);
        }
    }
    if (!$result['success']) http_response_code(422);
    echo json_encode($result);
    exit;
}

$fields = [];
foreach (['firstname', 'middlename', 'lastname', 'phone_number'] as $key) {
    $fields[$key] = trim((string) ($_POST[$key] ?? ''));
}
if ($fields['firstname'] === '' || $fields['lastname'] === '' || $fields['phone_number'] === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Complete all required fields.']);
    exit;
}
$errors = IdentityInput::errors($fields);
if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => reset($errors), 'field' => array_key_first($errors)]);
    exit;
}

$oldValues = [
    'firstname' => (string) ($old['firstname'] ?? ''),
    'middlename' => (string) ($old['middlename'] ?? ''),
    'lastname' => (string) ($old['lastname'] ?? ''),
    'phone_number' => (string) ($old['phone_number'] ?? ''),
];
if ($oldValues === $fields) {
    echo json_encode(['success' => true, 'message' => 'No changes to save.']);
    exit;
}

$result = $patients->updateMyAccount($userId, $fields);
if (!$result['success']) {
    http_response_code(500);
    echo json_encode($result);
    exit;
}

$_SESSION['display_name'] = trim(implode(' ', array_filter([$fields['firstname'], $fields['middlename'], $fields['lastname']])));
echo json_encode(['success' => true, 'message' => 'Account details saved.', 'display_name' => $_SESSION['display_name']]);

