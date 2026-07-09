<?php
require_once '../models/staffModel.php';
require_once '../../config/conn.php';

session_start();

class StaffController {
    private $staffModel;

    public function __construct() {
        $db   = new Database();
        $conn = $db->connect();
        $this->staffModel = new Staff($conn);
    }

    public function create() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

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

        $result = $this->staffModel->createStaff($firstname, $lastname, $middlename, $gender, $phone, $email, $password);

        if ($result['success']) {
            echo json_encode([
                'success'  => true,
                'message'  => 'Account created successfully.',
                'username' => $result['username'],
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

        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

        $staff_id = $_POST['staff_id'] ?? '';
        $phone    = trim($_POST['phone'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (!$staff_id || !$phone || !$email) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        $result = $this->staffModel->updateStaff($staff_id, $phone, $email);

        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Updated successfully.' : 'Failed to update.',
        ]);
        exit;
    }

    public function toggleStatus() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

        $staff_id = $_POST['staff_id'] ?? '';

        if (!$staff_id) {
            echo json_encode(['success' => false, 'message' => 'Missing staff ID.']);
            exit;
        }

        $result = $this->staffModel->toggleStatus($staff_id);

        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Status updated.' : 'Failed to update status.',
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