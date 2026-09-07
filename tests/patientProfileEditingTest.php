<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/patientModel.php';

function expectPatientEdit(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$patients = new Patient($conn);
$staffId = (int) $conn->query("SELECT id FROM users WHERE user_role IN ('Admin','Dental Assistant') ORDER BY id LIMIT 1")->fetchColumn();
$patientUserId = (int) $conn->query("SELECT id FROM users WHERE user_role='Patient' ORDER BY id LIMIT 1")->fetchColumn();
$patientId = null;

try {
    $patientId = (int) $patients->createPatient(null, 'Editable', 'Profile Test', '', 30, 'Prefer not to say', '09123456789', 'editable-profile@example.invalid', '1996-01-01');
    expectPatientEdit($patientId > 0 && $staffId > 0 && $patientUserId > 0, 'Temporary patient profile fixture is available.');

    $profile = [
        'firstname'=>'Editable','lastname'=>'Profile Test','middlename'=>'','birthdate'=>'1996-01-01','age'=>30,
        'gender'=>'Prefer not to say','civil_status'=>'Single','phone_number'=>'09123456789','email'=>'editable-profile@example.invalid',
        'home_address'=>'Test address','work_address'=>'','occupation'=>'','office_contact'=>'','fb_account'=>'','guardian_name'=>'',
        'guardian_contact'=>'','physician_name'=>'','physician_contact'=>'','physician_address'=>'','previous_dentist'=>'',
        'last_dental_visit'=>'','treatment_done'=>'','reason_for_visit'=>'Checkup','referred_by'=>'','good_health'=>1,
        'medical_condition'=>0,'medical_condition_detail'=>'','serious_illness'=>0,'serious_illness_detail'=>'',
        'hospitalized'=>0,'hospitalized_detail'=>'','medication'=>0,'medication_detail'=>'','smoke'=>0,'alcohol'=>0,
        'drugs'=>0,'allergy'=>0,'allergy_detail'=>'','pregnant'=>null,'nursing'=>null,'birth_control'=>null,
        'blood_type'=>'','blood_pressure'=>'','cond_others'=>'','no_known_conditions'=>1,'conditions'=>[],
        'consent_name'=>'Editable Profile Test','consent_for'=>'myself','consent_date'=>'2026-09-07','contact_confirmed'=>true,
    ];

    expectPatientEdit($patients->completeProfileByStaff($patientId, $profile, $staffId, true)['success'], 'Staff can approve the fixture before a patient edit.');
    $approved = $patients->getPatientFull($patientId);
    expectPatientEdit(($approved['profile_status'] ?? '') === 'Complete' && !empty($approved['profile_completed_at']), 'The fixture starts with a staff-completed profile.');

    $profile['home_address'] = 'Updated by patient';
    $result = $patients->saveProfileByPatient($patientId, $profile, $patientUserId);
    expectPatientEdit($result['success'] && ($result['profile_status'] ?? '') === 'Draft', 'A patient can save profile changes as a draft.');

    $saved = $patients->getPatientFull($patientId);
    expectPatientEdit(($saved['home_address'] ?? '') === 'Updated by patient', 'Patient-entered profile changes persist.');
    expectPatientEdit(($saved['profile_status'] ?? '') === 'Draft' && empty($saved['profile_completed_at']) && empty($saved['profile_completed_by_user_id']), 'A patient edit clears the prior staff completion stamp.');
    expectPatientEdit(($saved['dental_last_updated_by'] ?? '') === 'patient' && ($saved['medical_last_updated_by'] ?? '') === 'patient', 'Clinical history records identify the patient as the editor.');

    $audit = $conn->prepare("SELECT action, new_values FROM audit_logs WHERE entity_type='patient' AND entity_id=:id ORDER BY audit_log_id DESC LIMIT 1");
    $audit->execute([':id'=>$patientId]);
    $auditRow = $audit->fetch(PDO::FETCH_ASSOC);
    expectPatientEdit(($auditRow['action'] ?? '') === 'profile_updated_by_patient', 'The patient profile edit is recorded in the audit log.');
} finally {
    if ($patientId) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='patient' AND entity_id=:id")->execute([':id'=>$patientId]);
        foreach (['patient_conditions','patient_consent','patient_dental_history','patient_medical_history'] as $table) {
            $conn->prepare("DELETE FROM {$table} WHERE patient_id=:id")->execute([':id'=>$patientId]);
        }
        $conn->prepare('DELETE FROM patients WHERE patient_id=:id')->execute([':id'=>$patientId]);
    }
}
