<?php
require_once '../../config/conn.php';
require_once '../models/patientModel.php';
require_once '../models/userModel.php';

session_start();

class PatientController {
    private $patients;
    private $userModel;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->patients = new Patient($conn);
        $this->userModel = new User($conn);
    }

    //Admin: all patients
    public function adminAllPatients() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../../../index.php?openModal=true');
            exit;
        }

        if (!in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
            header('Location: ../admin/dashboard.php');
            exit;
        }

        $data = $this->patients->getAllPatients();
        require_once '../views/admin-all-patients.php';
    }

    public function saveDentalForm() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        $requiredFields = ['lastName', 'firstName', 'birthdate', 'sex', 'mobile'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Please fill in the required patient information.']);
                exit;
            }
        }

        $data = [
            'firstname' => trim($_POST['firstName'] ?? ''),
            'lastname' => trim($_POST['lastName'] ?? ''),
            'middlename' => trim($_POST['middleName'] ?? ''),
            'age' => !empty($_POST['age']) ? (int)$_POST['age'] : null,
            'gender' => trim($_POST['sex'] ?? ''),
            'phone_number' => trim($_POST['mobile'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'birthdate' => trim($_POST['birthdate'] ?? ''),
            'civil_status' => trim($_POST['civilStatus'] ?? ''),
            'home_address' => trim($_POST['homeAddress'] ?? ''),
            'work_address' => trim($_POST['workAddress'] ?? ''),
            'fb_account' => trim($_POST['fbAccount'] ?? ''),
            'occupation' => trim($_POST['occupation'] ?? ''),
            'office_contact' => trim($_POST['officeContact'] ?? ''),
            'guardian_name' => trim($_POST['guardianName'] ?? ''),
            'guardian_contact' => trim($_POST['guardianContact'] ?? ''),
            'physician_name' => trim($_POST['physicianName'] ?? ''),
            'physician_contact' => trim($_POST['physicianContact'] ?? ''),
            'physician_address' => trim($_POST['physicianAddress'] ?? ''),
            'previous_dentist' => trim($_POST['previousDentist'] ?? ''),
            'last_dental_visit' => trim($_POST['lastDentalVisit'] ?? ''),
            'treatment_done' => trim($_POST['treatmentDone'] ?? ''),
            'reason_for_visit' => trim($_POST['reasonForVisit'] ?? ''),
            'referred_by' => trim($_POST['referredBy'] ?? ''),
            'good_health' => $this->toBool($_POST['goodHealth'] ?? null),
            'medical_condition' => $this->toBool($_POST['medicalCondition'] ?? null),
            'medical_condition_detail' => trim($_POST['medicalConditionDetail'] ?? ''),
            'serious_illness' => $this->toBool($_POST['seriousIllness'] ?? null),
            'serious_illness_detail' => trim($_POST['seriousIllnessDetail'] ?? ''),
            'hospitalized' => $this->toBool($_POST['hospitalized'] ?? null),
            'hospitalized_detail' => trim($_POST['hospitalizedDetail'] ?? ''),
            'medication' => $this->toBool($_POST['medication'] ?? null),
            'medication_detail' => trim($_POST['medicationDetail'] ?? ''),
            'smoke' => $this->toBool($_POST['smoke'] ?? null),
            'alcohol' => $this->toBool($_POST['alcohol'] ?? null),
            'drugs' => $this->toBool($_POST['drugs'] ?? null),
            'allergy' => $this->toBool($_POST['allergy'] ?? null),
            'allergy_detail' => trim($_POST['allergyDetail'] ?? ''),
            'pregnant' => $this->toBool($_POST['pregnant'] ?? null),
            'nursing' => $this->toBool($_POST['nursing'] ?? null),
            'birth_control' => $this->toBool($_POST['birthControl'] ?? null),
            'cond_others' => trim($_POST['condOthers'] ?? ''),
            'conditions' => isset($_POST['cond']) ? (array)$_POST['cond'] : [],
            'consent_name' => trim($_POST['consentName'] ?? ''),
            'consent_for' => trim($_POST['consentFor'] ?? ''),
            'consent_date' => date('Y-m-d')
        ];

        $patient_id = $this->patients->savePatientForm($data);

        if ($patient_id) {
            echo json_encode(['success' => true, 'message' => 'Patient form submitted successfully.', 'patient_id' => $patient_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unable to save the form. Please try again.']);
        }
        exit;
    }

    private function isStrongPassword($password) {
        return preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password) === 1;
    }

    public function changePassword() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'You must be logged in to change your password.']);
            exit;
        }

        $currentPassword = trim($_POST['current_password'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');

        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all password fields.']);
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
            exit;
        }

        if (!$this->isStrongPassword($newPassword)) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters and include both letters and numbers.']);
            exit;
        }

        $user = $this->userModel->getUserById($_SESSION['user_id']);
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $result = $this->userModel->changePassword($_SESSION['user_id'], $hashedPassword);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unable to change password. Please try again.']);
        }
        exit;
    }

    private function toBool($value) {
        if ($value === 'yes') {
            return 1;
        }

        if ($value === 'no') {
            return 0;
        }

        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $controller = new PatientController();

    if ($action === 'saveDentalForm') {
        $controller->saveDentalForm();
    } elseif ($action === 'changePassword') {
        $controller->changePassword();
    }
}