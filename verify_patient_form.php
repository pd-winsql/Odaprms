<?php
require 'c:/xampp/htdocs/Capstone System/config/conn.php';
require 'c:/xampp/htdocs/Capstone System/apps/models/patientModel.php';

$db = new Database();
$conn = $db->connect();
if (!$conn) {
    echo "DB_CONNECTION_FAILED";
    exit(1);
}

$patient = new Patient($conn);
$data = [
    'firstname' => 'Test',
    'lastname' => 'User',
    'middlename' => 'CLI',
    'age' => 30,
    'gender' => 'Female',
    'phone_number' => '09170000000',
    'email' => 'testcli@example.com',
    'birthdate' => '1995-01-10',
    'civil_status' => 'Single',
    'home_address' => 'Test Address',
    'work_address' => 'Work Address',
    'fb_account' => 'testfb',
    'occupation' => 'Tester',
    'office_contact' => '09170000001',
    'guardian_name' => '',
    'guardian_contact' => '',
    'physician_name' => '',
    'physician_contact' => '',
    'physician_address' => '',
    'previous_dentist' => 'Dr. Test',
    'last_dental_visit' => '2024-01-01',
    'treatment_done' => 'Cleaning',
    'reason_for_visit' => 'Checkup',
    'referred_by' => 'Friend',
    'good_health' => 1,
    'medical_condition' => 0,
    'medical_condition_detail' => '',
    'serious_illness' => 0,
    'serious_illness_detail' => '',
    'hospitalized' => 0,
    'hospitalized_detail' => '',
    'medication' => 0,
    'medication_detail' => '',
    'smoke' => 0,
    'alcohol' => 0,
    'drugs' => 0,
    'allergy' => 0,
    'allergy_detail' => '',
    'pregnant' => 0,
    'nursing' => 0,
    'birth_control' => 0,
    'cond_others' => '',
    'conditions' => ['Heart Disease'],
    'consent_name' => 'Test User',
    'consent_for' => 'myself',
    'consent_date' => '2026-07-07'
];

$id = $patient->savePatientForm($data);
if ($id === false) {
    echo "SAVE_FAILED";
    exit(1);
}

echo "INSERTED:$id";
