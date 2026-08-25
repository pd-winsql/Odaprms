<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/patientModel.php';

function expectPersistence(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$patients = new Patient($conn);
$staffId = (int) $conn->query("SELECT id FROM users WHERE user_role IN ('Admin','Dental Assistant') ORDER BY id LIMIT 1")->fetchColumn();
$patientId = null;

try {
    $patientId = (int) $patients->createPatient(null, 'Questionnaire', 'Draft Test', '', 30, 'Prefer not to say', '09123456789', 'questionnaire-draft@example.invalid', '1996-01-01');
    expectPersistence($patientId > 0 && $staffId > 0, 'Temporary questionnaire fixture is available.');

    $profile = [
        'firstname'=>'Questionnaire','lastname'=>'Draft Test','middlename'=>'','birthdate'=>'1996-01-01','age'=>30,
        'gender'=>'Prefer not to say','civil_status'=>'','phone_number'=>'09123456789','email'=>'questionnaire-draft@example.invalid',
        'home_address'=>'','work_address'=>'','occupation'=>'','office_contact'=>'','fb_account'=>'','guardian_name'=>'',
        'guardian_contact'=>'','physician_name'=>'','physician_contact'=>'','physician_address'=>'','previous_dentist'=>'',
        'last_dental_visit'=>'','treatment_done'=>'','reason_for_visit'=>'Checkup','referred_by'=>'','good_health'=>1,
        'medical_condition'=>0,'medical_condition_detail'=>'','serious_illness'=>0,'serious_illness_detail'=>'',
        'hospitalized'=>0,'hospitalized_detail'=>'','medication'=>0,'medication_detail'=>'','smoke'=>0,'alcohol'=>0,
        'drugs'=>0,'allergy'=>0,'allergy_detail'=>'','pregnant'=>null,'nursing'=>null,'birth_control'=>null,
        'blood_type'=>'','blood_pressure'=>'','cond_others'=>'','no_known_conditions'=>1,'conditions'=>[],
        'consent_name'=>'Questionnaire Draft Test','consent_for'=>'myself','contact_confirmed'=>true,
    ];

    expectPersistence($patients->completeProfileByStaff($patientId, $profile, $staffId, false)['success'], 'Questionnaire draft saves successfully.');
    $reopened = $patients->getPatientFull($patientId);
    expectPersistence((int) ($reopened['no_known_conditions'] ?? 0) === 1, 'Reopened draft retains no known medical conditions.');
    expectPersistence(($reopened['profile_status'] ?? '') === 'Draft', 'Saving the questionnaire draft does not mark the patient ready.');
} finally {
    if ($patientId) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='patient' AND entity_id=:id")->execute([':id'=>$patientId]);
        foreach (['patient_conditions','patient_consent','patient_dental_history','patient_medical_history'] as $table) {
            $conn->prepare("DELETE FROM {$table} WHERE patient_id=:id")->execute([':id'=>$patientId]);
        }
        $conn->prepare('DELETE FROM patients WHERE patient_id=:id')->execute([':id'=>$patientId]);
    }
}
