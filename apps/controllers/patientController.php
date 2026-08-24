<?php
require_once '../../config/conn.php';
require_once '../models/patientModel.php';
require_once '../models/userModel.php';
require_once '../helpers/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        $this->requirePatient();
        echo json_encode(['success' => false, 'message' => 'Personal information can only be updated by clinic staff.']);
        exit;
    }

    public function updateMinors() {
        header('Content-Type: application/json');
        $this->requirePatient();
        echo json_encode(['success' => false, 'message' => 'Guardian and physician information can only be updated by clinic staff.']);
        exit;
    }

    public function updateDental() {
        header('Content-Type: application/json');
        $this->requirePatient();
        echo json_encode(['success' => false, 'message' => 'Dental history can only be updated by clinic staff.']);
        exit;
    }

    public function updateHealth() {
        header('Content-Type: application/json');
        $this->requirePatient();
        echo json_encode(['success' => false, 'message' => 'Medical information can only be updated by clinic staff.']);
        exit;
    }

    public function updateConditions() {
        header('Content-Type: application/json');
        $this->requirePatient();
        echo json_encode(['success' => false, 'message' => 'Medical conditions can only be updated by clinic staff.']);
        exit;
    }

    public function updateConsent() {
        header('Content-Type: application/json');
        $this->requirePatient();
        echo json_encode(['success' => false, 'message' => 'Consent information can only be updated by clinic staff.']);
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
        if (!validate_csrf()) {
            echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
            exit;
        }

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $isDraft = ($_POST['save_mode'] ?? '') === 'draft';
        $required = ['firstname', 'lastname', 'birthdate', 'gender', 'phone_number', 'reason_for_visit', 'consent_name', 'consent_for'];
        foreach ($isDraft ? [] : $required as $field) {
            if (trim($_POST[$field] ?? '') === '') {
                echo json_encode(['success' => false, 'message' => 'Please complete all required patient-form fields.']);
                exit;
            }
        }
        if (!$isDraft && ($_POST['contact_confirmed'] ?? '') !== '1') {
            echo json_encode(['success' => false, 'message' => 'Confirm the patient’s phone number and email before marking the profile ready.']);
            exit;
        }
        $submittedPhone = trim($_POST['phone_number'] ?? '');
        if ($submittedPhone !== '' && !preg_match('/^\d{1,11}$/', $submittedPhone)) {
            echo json_encode(['success' => false, 'message' => 'Phone number must contain numbers only and cannot exceed 11 digits.']);
            exit;
        }
        $normalizedPhone = Patient::normalizePhone($submittedPhone);
        if (!$isDraft && !preg_match('/^09\d{9}$/', $normalizedPhone)) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid 11-digit Philippine mobile number.']);
            exit;
        }
        if (!$isDraft && trim($_POST['email'] ?? '') !== '' && !filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid email address or leave the email field blank.']);
            exit;
        }
        $birth = DateTime::createFromFormat('Y-m-d', $_POST['birthdate']);
        $today = new DateTime('today');
        if (!$isDraft && (!$birth || $birth > $today)) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid birthdate.']);
            exit;
        }

        $booleanFields = ['good_health','medical_condition','serious_illness','hospitalized','medication','smoke','alcohol','drugs','allergy','pregnant','nursing','birth_control'];
        $data = [];
        foreach ($booleanFields as $field) {
            $data[$field] = $this->toBool($_POST[$field] ?? null);
        }
        $requiredMedicalAnswers = ['good_health','medical_condition','serious_illness','hospitalized','medication','smoke','alcohol','drugs','allergy'];
        if (!$isDraft) {
            foreach ($requiredMedicalAnswers as $field) {
                if ($data[$field] === null) {
                    echo json_encode(['success' => false, 'message' => 'Review and answer every general health question.']);
                    exit;
                }
            }
            if (($_POST['gender'] ?? '') === 'Female') {
                foreach (['pregnant','nursing','birth_control'] as $field) {
                    if ($data[$field] === null) {
                        echo json_encode(['success' => false, 'message' => 'Complete the women-only health questions.']);
                        exit;
                    }
                }
            }
            foreach (['medical_condition','serious_illness','hospitalized','medication','allergy'] as $field) {
                if ($data[$field] === 1 && trim($_POST[$field . '_detail'] ?? '') === '') {
                    echo json_encode(['success' => false, 'message' => 'Add details for every medical question answered Yes.']);
                    exit;
                }
            }
        }
        $textFields = [
            'firstname','lastname','middlename','gender','civil_status','phone_number','email','home_address','work_address',
            'occupation','office_contact','fb_account','guardian_name','guardian_contact','physician_name','physician_contact',
            'physician_address','previous_dentist','last_dental_visit','treatment_done','reason_for_visit','referred_by',
            'medical_condition_detail','serious_illness_detail','hospitalized_detail','medication_detail','allergy_detail',
            'blood_type','blood_pressure','cond_others','consent_name','consent_for'
        ];
        foreach ($textFields as $field) $data[$field] = trim($_POST[$field] ?? '');
        $data['phone_number'] = $normalizedPhone;
        $data['birthdate'] = $_POST['birthdate'];
        $data['age'] = $birth ? $birth->diff($today)->y : null;
        $submittedConditions = (array) ($_POST['conditions'] ?? []);
        $conditionGroups = require __DIR__ . '/../../config/medicalConditions.php';
        $allowedConditions = array_merge(...array_values($conditionGroups));
        $data['conditions'] = array_values(array_intersect(array_map('trim', $submittedConditions), $allowedConditions));
        $data['contact_confirmed'] = ($_POST['contact_confirmed'] ?? '') === '1';
        $data['appointment_id'] = (int) ($_POST['appointment_id'] ?? 0) ?: null;
        if (!empty($_POST['no_known_conditions'])) {
            $data['conditions'] = [];
            $data['cond_others'] = '';
        }
        if (!$isDraft && empty($_POST['no_known_conditions']) && !$data['conditions'] && $data['cond_others'] === '') {
            echo json_encode(['success' => false, 'message' => 'Select the patient’s medical conditions or confirm that there are no known conditions.']);
            exit;
        }

        echo json_encode($this->patients->completeProfileByStaff($patientId, $data, (int) $_SESSION['user_id'], !$isDraft));
        exit;
    }

    public function authorizeAccountLink() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin','Dental Assistant'], true)) { echo json_encode(['success'=>false,'message'=>'Forbidden.']); exit; }
        if (!validate_csrf()) { echo json_encode(['success'=>false,'message'=>'Your session expired. Refresh and try again.']); exit; }
        echo json_encode($this->patients->authorizeAccountLink((int)($_POST['patient_id'] ?? 0), trim($_POST['email'] ?? ''), (int)$_SESSION['user_id'])); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $controller = new PatientController();

    if ($action === 'saveDentalForm') {
        $controller->saveDentalForm();
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
    } elseif ($action === 'authorizeAccountLink') {
        $controller->authorizeAccountLink();
    }
}
