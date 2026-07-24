<?php
require_once '../models/userModel.php';
require_once '../../config/conn.php';
require_once '../models/patientModel.php';
require_once '../../config/mailer.php';

session_start();

class UserController {
    private $userModel;
    private $patientModel;
    private $conn;

    public function __construct() {
        $db   = new Database();
        $this->conn = $db->connect();

        $this->userModel = new User($this->conn);
        $this->patientModel = new Patient($this->conn);
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

    // ── Send Register OTP ──────────────────────────────────────
    public function sendRegisterOTP() {
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

        if ($this->userModel->emailExists($email)) {
            echo json_encode(['success' => false, 'message' => 'Email is already registered.']);
            exit;
        }

        if ($this->userModel->usernameExists($username)) {
            echo json_encode(['success' => false, 'message' => 'Username is already taken.']);
            exit;
        }

        // Store registration data in session temporarily
        $_SESSION['pending_registration'] = [
            'email'    => $email,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ];

        // Generate and store OTP
        $otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $stmt = $this->conn->prepare("DELETE FROM email_verifications WHERE email = :email");
        $stmt->execute([':email' => $email]);

        $stmt = $this->conn->prepare("
            INSERT INTO email_verifications (email, otp, expires_at)
            VALUES (:email, :otp, NOW() + INTERVAL 10 MINUTE)
        ");
        $stmt->execute([':email' => $email, ':otp' => $otp]);

        $result = sendOTPEmail($email, $username, $otp);

        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Verification code sent to your email.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send verification email. Please try again.']);
        }
        exit;
    }

    // ── Resend Register OTP ────────────────────────────────────
    public function resendRegisterOTP() {
        header('Content-Type: application/json');

        $pending = $_SESSION['pending_registration'] ?? null;

        if (!$pending) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please register again.']);
            exit;
        }

        $email    = $pending['email'];
        $username = $pending['username'];

        $otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $stmt = $this->conn->prepare("DELETE FROM email_verifications WHERE email = :email");
        $stmt->execute([':email' => $email]);

        $stmt = $this->conn->prepare("
            INSERT INTO email_verifications (email, otp, expires_at)
            VALUES (:email, :otp, :expires_at)
        ");
        $stmt->execute([':email' => $email, ':otp' => $otp, ':expires_at' => $expiresAt]);

        $result = sendOTPEmail($email, $username, $otp);

        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'New verification code sent.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to resend code. Please try again.']);
        }
        exit;
    }

    // ── Verify Register OTP ────────────────────────────────────
    public function verifyRegisterOTP() {
        header('Content-Type: application/json');

        $email = trim($_POST['email'] ?? '');
        $otp   = trim($_POST['otp']   ?? '');

        if (!$email || !$otp) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        $stmt = $this->conn->prepare("
            SELECT * FROM email_verifications
            WHERE email = :email AND otp = :otp AND used = 0 AND expires_at > NOW()
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([':email' => $email, ':otp' => $otp]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired code. Please try again.']);
            exit;
        }

        $pending = $_SESSION['pending_registration'] ?? null;

        if (!$pending || $pending['email'] !== $email) {
            echo json_encode(['success' => false, 'message' => 'Registration session expired. Please start again.']);
            exit;
        }

        // Insert into users
        $result = $this->userModel->register(
            $pending['email'],
            $pending['username'],
            $pending['password']
        );

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Failed to create account. Please try again.']);
            exit;
        }

        $user_id = $this->userModel->getLastInsertedId();

        // Link or create patient record
        $patient = $this->patientModel->getPatientByEmail($pending['email']);
        if ($patient) {
            $this->patientModel->linkUser($patient['patient_id'], $user_id);
        } else {
            $this->patientModel->createPatient(
                $user_id, null, null, null, null, null, null, $pending['email']
            );
        }

        // Mark OTP as used and clear session
        $stmt = $this->conn->prepare("UPDATE email_verifications SET used = 1 WHERE id = :id");
        $stmt->execute([':id' => $record['id']]);
        unset($_SESSION['pending_registration']);

        echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
        exit;
    }

    /*
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

*/

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
} elseif ($action === 'sendRegisterOTP') {
    $controller->sendRegisterOTP();
} elseif ($action === 'resendRegisterOTP') {
    $controller->resendRegisterOTP();
} elseif ($action === 'verifyRegisterOTP') {
    $controller->verifyRegisterOTP();
} elseif ($action === 'logout') {
    $controller->logout();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}