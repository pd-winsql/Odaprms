<?php
require_once '../models/userModel.php';
require_once '../../config/conn.php';
require_once '../models/patientModel.php';

session_start();

class UserController {
    private $userModel;
    private $patientModel;

    public function __construct() {
        $db   = new Database();
        $conn = $db->connect();
        $this->userModel = new User($conn);
        $this->patientModel = new Patient($conn);
    }

    public function login() {
        header('Content-Type: application/json');

        $identity = trim($_POST['identity'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$identity || !$password) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
            exit;
        }

        // Find user by email or username
        $user = $this->userModel->findByEmailOrUsername($identity);

        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials. Please try again.']);
            exit;
        }

        // Set session
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['user_role'] = $user['user_role'];

        // Role-based redirect
        $redirect = match($user['user_role']) {
            'Admin'           => 'admin/dashboard.php',
            'Dental Assistant'=> 'dental_asst/dashboard.php',
            'Patient'         => 'patient/dashboard.php',
            default           => '../../index.php',
        };

        echo json_encode(['success' => true, 'redirect' => $redirect]);
        exit;
    }

    private function isStrongPassword($password) {
        return preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password) === 1;
    }

    public function register() {
        header('Content-Type: application/json');

        $email    = trim($_POST['email']    ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$email || !$username || !$password) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores.']);
            exit;
        }

        if (!$this->isStrongPassword($password)) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters and include both letters and numbers.']);
            exit;
        }

        // Check if email or username already exists
        if ($this->userModel->emailExists($email)) {
            echo json_encode(['success' => false, 'message' => 'Email is already registered.']);
            exit;
        }

        if ($this->userModel->usernameExists($username)) {
            echo json_encode(['success' => false, 'message' => 'Username is already taken.']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $result = $this->userModel->register($email, $username, $hashedPassword);

        if (!$result) {

            echo json_encode([
                'success' => false,
                'message' => 'Registration failed.'
            ]);
            exit;
            
        }
        
        $user_id = $this->userModel->getLastInsertedId();
        $patient = $this->patientModel->getPatientByEmail($email);

        if($patient) {
            $this->patientModel->linkUser(
                $patient['patient_id'],
                $user_id
            );
        }

        else {
            $this->patientModel->createPatient(
                $user_id,
                null,
                null,
                null,
                null,
                null,
                null,
                $email
            );
        }

    }

    public function logout() {
        session_destroy();
        header('Location: ../../index.php');
        exit;
    }
}

$controller = new UserController();
$action     = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'login') {
    $controller->login();
} elseif ($action === 'register') {
    $controller->register();
} elseif ($action === 'logout') {
    $controller->logout();
}