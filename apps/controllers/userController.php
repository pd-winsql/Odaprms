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

        if (!filter_var($identity, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid email address.']); exit;
        }
        $user = $this->userModel->findByEmail($identity);

        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials. Please try again.']);
            exit;
        }

        // Set session
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['email']     = $user['email'];
        // Reuse the combined profile name returned by User::findByEmail() on
        // authenticated pages instead of querying the profile on every page.
        $_SESSION['display_name'] = $user['display_name'] ?? $user['email'];
        $_SESSION['user_role'] = $user['user_role'];

        // Role-based redirect
        $redirect = match($user['user_role']) {
            'Admin'           => 'admin/dashboard.php',
            'Dental Assistant'=> 'dental_asst/dashboard.php',
            'Patient'         => 'patient/dashboard.php',
            default           => '../../index.php',
        };
        if ($user['user_role'] === 'Patient' && ($_POST['next'] ?? '') === 'booking') {
            $redirect = 'patient/dashboard.php#booking-content.php';
        }

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
        $password = trim($_POST['password'] ?? '');
        $identity = [
            'firstname' => trim($_POST['firstname'] ?? ''),
            'middlename' => trim($_POST['middlename'] ?? ''),
            'lastname' => trim($_POST['lastname'] ?? ''),
            'suffix' => trim($_POST['suffix'] ?? ''),
            'birthdate' => trim($_POST['birthdate'] ?? ''),
            'gender' => trim($_POST['gender'] ?? ''),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
        ];

        if (!$email || !$password || !$identity['firstname'] || !$identity['lastname'] || !$identity['birthdate'] || !$identity['gender'] || !$identity['phone_number']) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
            exit;
        }

        if (!in_array($identity['gender'], ['Male', 'Female', 'Prefer not to say'], true)) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid gender option.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
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

        $birthdate = DateTimeImmutable::createFromFormat('Y-m-d', $identity['birthdate']);
        if (!$birthdate || $birthdate->format('Y-m-d') !== $identity['birthdate'] || $birthdate > new DateTimeImmutable('today')) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid birthdate.']);
            exit;
        }
        if (strlen(Patient::normalizePhone($identity['phone_number'])) < 10) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid contact number.']);
            exit;
        }

        $exactPatient = $this->patientModel->findExactIdentity($identity);
        $linkAuthorization = null;
        if ($exactPatient) {
            $linkAuthorization = $this->patientModel->getActiveLinkAuthorization((int) $exactPatient['patient_id'], $email);
            if (!$linkAuthorization) {
                echo json_encode(['success' => false, 'message' => 'An existing patient record may already match your information. Please contact the clinic so your account can be connected securely.']);
                exit;
            }
        }
        $possibleMatches = $exactPatient ? [] : $this->patientModel->findPossibleIdentityMatches($identity);

        // Store registration data in session temporarily
        $_SESSION['pending_registration'] = [
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'identity' => $identity,
            'link_patient_id' => $exactPatient ? (int) $exactPatient['patient_id'] : null,
            'link_authorization_id' => $linkAuthorization ? (int) $linkAuthorization['authorization_id'] : null,
            'possible_match_ids' => $possibleMatches,
        ];

        // Generate and store OTP
        $otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $stmt = $this->conn->prepare("DELETE FROM email_verifications WHERE email = :email");
        $stmt->execute([':email' => $email]);

        $stmt = $this->conn->prepare("
            INSERT INTO email_verifications (email, otp, expires_at)
            VALUES (:email, :otp, NOW() + INTERVAL 10 MINUTE)
        ");
        $stmt->execute([':email' => $email, ':otp' => $otp]);

        $result = sendOTPEmail($email, trim($identity['firstname'] . ' ' . $identity['lastname']), $otp, 'register');

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

        $otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $stmt = $this->conn->prepare("DELETE FROM email_verifications WHERE email = :email");
        $stmt->execute([':email' => $email]);

        $stmt = $this->conn->prepare("
            INSERT INTO email_verifications (email, otp, expires_at)
            VALUES (:email, :otp, NOW() + INTERVAL 10 MINUTE)
        ");
        $stmt->execute([':email' => $email, ':otp' => $otp]);

        $result = sendOTPEmail($email, trim($pending['identity']['firstname'] . ' ' . $pending['identity']['lastname']), $otp, 'register');

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

        try {
            $this->conn->beginTransaction();
            if (!$this->userModel->register($pending['email'], $pending['password'])) {
                throw new RuntimeException('Unable to create the user account.');
            }
            $user_id = (int) $this->userModel->getLastInsertedId();
            if (!empty($pending['link_patient_id'])) {
                $authorization = $this->patientModel->getActiveLinkAuthorization((int) $pending['link_patient_id'], $pending['email']);
                if (!$authorization || (int) $authorization['authorization_id'] !== (int) $pending['link_authorization_id']) {
                    throw new RuntimeException('The account-link authorization expired.');
                }
                if (!$this->patientModel->linkUser((int) $pending['link_patient_id'], $user_id)) {
                    throw new RuntimeException('Unable to link the patient record.');
                }
                $this->conn->prepare("UPDATE patients SET email = :email WHERE patient_id = :patient_id")
                    ->execute([':email' => $pending['email'], ':patient_id' => $pending['link_patient_id']]);
                $birthdate = new DateTimeImmutable($pending['identity']['birthdate']);
                $age = $birthdate->diff(new DateTimeImmutable('today'))->y;
                $this->conn->prepare("
                    UPDATE patients
                    SET age = :age, gender = COALESCE(NULLIF(gender, ''), :gender)
                    WHERE patient_id = :patient_id
                ")->execute([
                    ':age' => $age,
                    ':gender' => $pending['identity']['gender'],
                    ':patient_id' => $pending['link_patient_id'],
                ]);
                $this->conn->prepare("
                    UPDATE patient_account_link_authorizations
                    SET status = 'Used', used_by_user_id = :user_id, used_at = NOW()
                    WHERE authorization_id = :authorization_id
                ")->execute([':user_id' => $user_id, ':authorization_id' => $authorization['authorization_id']]);
            } else {
                $patientId = $this->patientModel->createRegisteredPatient($user_id, $pending['identity'], $pending['email']);
                $this->patientModel->flagPossibleDuplicates($patientId, $pending['possible_match_ids'] ?? []);
            }
            $this->conn->prepare("UPDATE email_verifications SET used = 1 WHERE id = :id")
                ->execute([':id' => $record['id']]);
            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('verifyRegisterOTP error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to create account. Please contact the clinic if the problem continues.']);
            exit;
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $pending['email'];
        $_SESSION['display_name'] = trim($pending['identity']['firstname'] . ' ' . $pending['identity']['lastname']);
        $_SESSION['user_role'] = 'Patient';
        unset($_SESSION['pending_registration']);

        echo json_encode(['success' => true, 'message' => 'Account created successfully!', 'redirect' => '/Capstone System/apps/views/patient/dashboard.php#booking-content.php']);
        exit;
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
