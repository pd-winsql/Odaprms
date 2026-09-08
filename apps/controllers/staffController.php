<?php
require_once '../models/staffModel.php';
require_once '../models/auditLogModel.php';
require_once '../../config/conn.php';
require_once '../../config/mailer.php';
require_once '../helpers/csrf.php';
require_once '../helpers/authorization.php';

if (session_status() === PHP_SESSION_NONE) session_start();

class StaffController {
    private $staffModel;
    private $auditLog;

    public function __construct() {
        $db   = new Database();
        $conn = $db->connect();
        $this->staffModel = new Staff($conn);
        $this->auditLog = new AuditLog($conn);
    }

    public function create() {
        header('Content-Type: application/json');

        vdRequireAdminJson();
        if (!validate_csrf()) { echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']); exit; }

        $firstname  = trim($_POST['firstname']  ?? '');
        $lastname   = trim($_POST['lastname']   ?? '');
        $middlename = trim($_POST['middlename'] ?? '');
        $gender     = trim($_POST['gender']     ?? '');
        $phone      = trim($_POST['phone']      ?? '');
        $email      = trim($_POST['email']      ?? '');
        $password   = trim($_POST['password']   ?? '');

        if (!$firstname || !$lastname || !$gender || !$phone || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            exit;
        }

        if (!preg_match('/^[0-9]{11}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Phone number must contain exactly 11 digits.']);
            exit;
        }

        $result = $this->staffModel->createStaff($firstname, $lastname, $middlename, $gender, $phone, $email, $password);

        if ($result['success']) {
            $this->auditLog->recordForUser('staff', (int) $result['staff_id'], 'staff_account_created', "Created dental assistant account for {$firstname} {$lastname}.", null, ['email' => $email, 'phone' => $phone, 'employment_status' => 'Active'], (int) $_SESSION['user_id']);
            $message = 'Account created successfully.';

            $emailResult = sendStaffAccountEmail($email, "$firstname $lastname", $password);
            if (!$emailResult['success']) {
                $message = 'Account created, but the notification email failed to send.';
            }

            echo json_encode([
                'success'  => true,
                'message'  => $message,
                'email' => $result['email'],
            ]);
        } else {
            // Check for duplicate email
            if (str_contains($result['message'] ?? '', 'Duplicate entry')) {
                echo json_encode(['success' => false, 'message' => 'Email already exists.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create account.']);
            }
        }
        exit;
    }

    public function update() {
        header('Content-Type: application/json');

        vdRequireAdminJson();
        if (!validate_csrf()) { echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']); exit; }

        $staff_id = $_POST['staff_id'] ?? '';
        $phone    = trim($_POST['phone'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (!$staff_id || !$phone || !$email) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        if (!preg_match('/^[0-9]{11}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Phone number must contain exactly 11 digits.']);
            exit;
        }

        $old = $this->staffModel->getStaffById((int) $staff_id);
        $result = $this->staffModel->updateStaff($staff_id, $phone, $email);
        if ($result && $old) $this->auditLog->recordForUser('staff', (int) $staff_id, 'staff_account_updated', 'Updated a dental assistant account.', ['phone' => $old['phone_number'], 'email' => $old['email']], ['phone' => $phone, 'email' => $email], (int) $_SESSION['user_id']);

        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Updated successfully.' : 'Failed to update.',
        ]);
        exit;
    }

    public function toggleStatus() {
        header('Content-Type: application/json');

        vdRequireAdminJson();
        if (!validate_csrf()) { echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']); exit; }

        $staff_id = $_POST['staff_id'] ?? '';

        if (!$staff_id) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Missing staff ID.']);
            exit;
        }

        $old = $this->staffModel->getStaffById((int) $staff_id);
        $result = $this->staffModel->toggleStatus($staff_id);
        $updated = $result ? $this->staffModel->getStaffById((int) $staff_id) : null;
        if ($result && $old && $updated) $this->auditLog->recordForUser('staff', (int) $staff_id, 'staff_status_updated', 'Changed a dental assistant account status.', ['employment_status' => $old['employment_status']], ['employment_status' => $updated['employment_status']], (int) $_SESSION['user_id']);

        if (!$result) {
            http_response_code($old ? 500 : 404);
        }

        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Status updated.' : ($old ? 'Failed to update status.' : 'Dental assistant not found.'),
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $controller = new StaffController();

    if ($action === 'create') {
        $controller->create();
    } elseif ($action === 'update') {
        $controller->update();
    } elseif ($action === 'toggleStatus') {
        $controller->toggleStatus();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
}
