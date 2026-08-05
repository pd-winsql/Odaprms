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

     private function requirePatient(): int {
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Patient') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

        $patient = $this->patients->getPatientByUserId($_SESSION['user_id']);

        if (!$patient) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Patient record not found.']);
            exit;
        }

        return (int) $patient['patient_id'];
    }

    public function updatePersonal() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        $patient_id = $this->requirePatient();

        $result = $this->patients->updatePersonal($patient_id, [
            'firstname'      => $_POST['firstname'] ?? '',
            'middlename'     => $_POST['middlename'] ?? '',
            'lastname'       => $_POST['lastname'] ?? '',
            'birthdate'      => $_POST['birthdate'] ?? null,
            'age'            => $_POST['age'] ?? null,
            'gender'         => $_POST['gender'] ?? '',
            'civil_status'   => $_POST['civil_status'] ?? '',
            'phone_number'   => $_POST['phone_number'] ?? '',
            'email'          => $_POST['email'] ?? '',
            'home_address'   => $_POST['home_address'] ?? '',
            'work_address'   => $_POST['work_address'] ?? '',
            'occupation'     => $_POST['occupation'] ?? '',
            'office_contact' => $_POST['office_contact'] ?? '',
            'fb_account'     => $_POST['fb_account'] ?? '',
        ]);

        echo json_encode([
            'success' => (bool) $result,
            'message' => $result ? 'Personal information updated.' : 'Failed to update.',
        ]);
        exit;
    }

    public function updateMinors() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        $patient_id = $this->requirePatient();

        $result = $this->patients->updateMinors($patient_id, [
            'guardian_name'     => $_POST['guardian_name'] ?? '',
            'guardian_contact'  => $_POST['guardian_contact'] ?? '',
            'physician_name'    => $_POST['physician_name'] ?? '',
            'physician_contact' => $_POST['physician_contact'] ?? '',
            'physician_address' => $_POST['physician_address'] ?? '',
        ]);

        echo json_encode([
            'success' => (bool) $result,
            'message' => $result ? 'Guardian information updated.' : 'Failed to update.',
        ]);
        exit;
    }

    public function updateDental() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        $patient_id = $this->requirePatient();

        $result = $this->patients->updateDentalHistory($patient_id, [
            'previous_dentist'  => $_POST['previous_dentist'] ?? '',
            'last_dental_visit'  => $_POST['last_dental_visit'] ?? null,
            'treatment_done'     => $_POST['treatment_done'] ?? '',
            'reason_for_visit'   => $_POST['reason_for_visit'] ?? '',
            'referred_by'        => $_POST['referred_by'] ?? '',
            'last_updated_by'    => 'patient',
        ]);

        echo json_encode([
            'success' => (bool) $result,
            'message' => $result ? 'Dental history updated.' : 'Failed to update.',
        ]);
        exit;
    }

    public function updateHealth() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        $patient_id = $this->requirePatient();

        $result = $this->patients->updateMedicalHistory($patient_id, [
            'good_health'              => $_POST['good_health'] ?? null,
            'medical_condition'        => $_POST['medical_condition'] ?? null,
            'medical_condition_detail' => $_POST['medical_condition_detail'] ?? '',
            'serious_illness'          => $_POST['serious_illness'] ?? null,
            'serious_illness_detail'   => $_POST['serious_illness_detail'] ?? '',
            'hospitalized'             => $_POST['hospitalized'] ?? null,
            'hospitalized_detail'      => $_POST['hospitalized_detail'] ?? '',
            'medication'               => $_POST['medication'] ?? null,
            'medication_detail'        => $_POST['medication_detail'] ?? '',
            'smoke'                    => $_POST['smoke'] ?? null,
            'alcohol'                  => $_POST['alcohol'] ?? null,
            'drugs'                    => $_POST['drugs'] ?? null,
            'allergy'                  => $_POST['allergy'] ?? null,
            'allergy_detail'           => $_POST['allergy_detail'] ?? '',
            'pregnant'                 => $_POST['pregnant'] ?? null,
            'nursing'                  => $_POST['nursing'] ?? null,
            'birth_control'            => $_POST['birth_control'] ?? null,
            'blood_type'               => $_POST['blood_type'] ?? '',
            'blood_pressure'           => $_POST['blood_pressure'] ?? '',
            'last_updated_by'          => 'patient',
        ]);

        echo json_encode([
            'success' => (bool) $result,
            'message' => $result ? 'Health questionnaire updated.' : 'Failed to update.',
        ]);
        exit;
    }

    public function updateConditions() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        $patient_id = $this->requirePatient();

        $conditions = $_POST['conditions'] ?? [];
        if (!is_array($conditions)) {
            $conditions = [];
        }

        $cond_others = $_POST['cond_others'] ?? '';

        $result = $this->patients->updateConditions($patient_id, $conditions, $cond_others);

        echo json_encode([
            'success' => (bool) $result,
            'message' => $result ? 'Conditions updated.' : 'Failed to update.',
        ]);
        exit;
    }

    public function updateConsent() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        $patient_id = $this->requirePatient();

        $result = $this->patients->updateConsent($patient_id, [
            'consent_name' => $_POST['consent_name'] ?? '',
            'consent_for'  => $_POST['consent_for'] ?? '',
            'consent_date' => $_POST['consent_date'] ?? null,
        ]);

        echo json_encode([
            'success' => (bool) $result,
            'message' => $result ? 'Consent updated.' : 'Failed to update.',
        ]);
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

    public function completeProfileByStaff() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }
        $providedToken = (string) ($_POST['csrf_token'] ?? '');
        $expectedToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
            exit;
        }

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $required = ['firstname', 'lastname', 'birthdate', 'gender', 'phone_number', 'reason_for_visit', 'consent_name', 'consent_for'];
        foreach ($required as $field) {
            if (trim($_POST[$field] ?? '') === '') {
                echo json_encode(['success' => false, 'message' => 'Please complete all required patient-form fields.']);
                exit;
            }
        }
        $birth = DateTime::createFromFormat('Y-m-d', $_POST['birthdate']);
        $today = new DateTime('today');
        if (!$birth || $birth > $today) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid birthdate.']);
            exit;
        }

        $booleanFields = ['good_health','medical_condition','serious_illness','hospitalized','medication','smoke','alcohol','drugs','allergy','pregnant','nursing','birth_control'];
        $data = [];
        foreach ($booleanFields as $field) {
            $data[$field] = $this->toBool($_POST[$field] ?? null);
        }
        $textFields = [
            'firstname','lastname','middlename','gender','civil_status','phone_number','email','home_address','work_address',
            'occupation','office_contact','fb_account','guardian_name','guardian_contact','physician_name','physician_contact',
            'physician_address','previous_dentist','last_dental_visit','treatment_done','reason_for_visit','referred_by',
            'medical_condition_detail','serious_illness_detail','hospitalized_detail','medication_detail','allergy_detail',
            'blood_type','blood_pressure','cond_others','consent_name','consent_for'
        ];
        foreach ($textFields as $field) $data[$field] = trim($_POST[$field] ?? '');
        $data['birthdate'] = $_POST['birthdate'];
        $data['age'] = $birth->diff($today)->y;
        $submittedConditions = (array) ($_POST['conditions'] ?? []);
        if (!empty($_POST['conditions_text'])) {
            $submittedConditions = array_merge($submittedConditions, explode(',', $_POST['conditions_text']));
        }
        $data['conditions'] = array_values(array_filter(array_map('trim', $submittedConditions)));

        echo json_encode($this->patients->completeProfileByStaff($patientId, $data, (int) $_SESSION['user_id']));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $controller = new PatientController();

    if ($action === 'saveDentalForm') {
        $controller->saveDentalForm();
    } elseif ($action === 'changePassword') {
        $controller->changePassword();
    } elseif ($action === 'updatePersonal') {
        $controller->updatePersonal();
    } elseif ($action === 'updateMinors') {
        $controller->updateMinors();
    } elseif ($action === 'updateDental') {
        $controller->updateDental();
    } elseif ($action === 'updateHealth') {
        $controller->updateHealth();
    } elseif ($action === 'updateConditions') {
        $controller->updateConditions();
    } elseif ($action === 'updateConsent') {
        $controller->updateConsent();
    } elseif ($action === 'completeProfileByStaff') {
        $controller->completeProfileByStaff();
    }
}
