<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';
require_once __DIR__ . '/../apps/models/depositModel.php';
require_once __DIR__ . '/../apps/models/logbookModel.php';
require_once __DIR__ . '/../apps/models/patientModel.php';

function expectTrue($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$db = new Database();
$conn = $db->connect();
expectTrue($conn->query('SELECT DATABASE()')->fetchColumn() === 'av-clinica-dental-feature', 'Tests are isolated to the feature database.');

$appointments = new Appointment($conn);
$deposits = new DepositModel($conn);
$logbook = new LogbookModel($conn);
$patients = new Patient($conn);
$createdIds = [];
$testPatientId = null;

try {
    $todaySchedule = $conn->query("SELECT schedule_id, clinic_id, sched_date FROM schedules WHERE sched_date = CURDATE() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $futureSchedule = $conn->query("SELECT schedule_id, clinic_id, sched_date FROM schedules WHERE sched_date > CURDATE() ORDER BY sched_date LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    expectTrue((bool) $todaySchedule && (bool) $futureSchedule, 'Test schedules are available.');

    $testPatientId = $patients->createPatient(null, 'Feature', 'Test Patient', '', 26, 'Prefer not to say', '09123456789', 'feature-test-patient@example.invalid', '2000-01-01');
    expectTrue((bool) $testPatientId, 'A temporary incomplete patient is available for workflow testing.');

    $booking = $appointments->bookAppointment($testPatientId, $todaySchedule['clinic_id'], [1], $todaySchedule['sched_date'], $todaySchedule['schedule_id']);
    expectTrue(is_array($booking), 'A provisional appointment and deposit are created atomically.');
    $createdIds[] = (int) $booking['appointment_id'];

    $row = $conn->query("SELECT a.status, a.deposit_required, d.amount, d.status AS deposit_status FROM appointments a JOIN appointment_deposits d ON d.appointment_id=a.appointment_id WHERE a.appointment_id=" . (int) $booking['appointment_id'])->fetch(PDO::FETCH_ASSOC);
    expectTrue($row['status'] === 'Awaiting Payment' && (int) $row['deposit_required'] === 1, 'New bookings wait for payment.');
    expectTrue($row['deposit_status'] === 'Awaiting Submission' && (float) $row['amount'] === 400.0, 'The fixed ₱400 deposit is recorded.');

    $reference = 'TEST' . date('YmdHis') . random_int(100, 999);
    $stmt = $conn->prepare("UPDATE appointment_deposits SET status='Under Review', gcash_reference=:reference, receipt_path='storage/payment_receipts/test.jpg', receipt_mime='image/jpeg', submitted_at=NOW() WHERE appointment_id=:appointment_id");
    $stmt->execute([':reference' => $reference, ':appointment_id' => $booking['appointment_id']]);
    $conn->prepare("UPDATE appointments SET payment_deadline_at=DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE appointment_id=:id")
        ->execute([':id' => $booking['appointment_id']]);
    expectTrue($appointments->countAppointmentsBySchedule($todaySchedule['schedule_id']) >= 1, 'A submitted payment keeps its slot reserved while under review.');
    $depositId = (int) $conn->query("SELECT deposit_id FROM appointment_deposits WHERE appointment_id=" . (int) $booking['appointment_id'])->fetchColumn();
    $verified = $deposits->verify($depositId, 7);
    expectTrue($verified['success'], 'Staff verification confirms the appointment.');

    $checkin = $logbook->checkIn((int) $booking['appointment_id'], 7);
    expectTrue($checkin['success'], 'A confirmed patient can be checked in on the appointment date.');
    expectTrue($checkin['status'] === 'Profile Required', 'An incomplete first-time patient is flagged for profile completion.');

    $profileData = [
        'firstname'=>'Feature','lastname'=>'Test Patient','middlename'=>'','birthdate'=>'2000-01-01','age'=>26,
        'gender'=>'Prefer not to say','civil_status'=>'Single','phone_number'=>'09123456789','email'=>'feature-test-patient@example.invalid',
        'home_address'=>'Test Address','work_address'=>'','occupation'=>'Tester','office_contact'=>'','fb_account'=>'',
        'guardian_name'=>'','guardian_contact'=>'','physician_name'=>'','physician_contact'=>'','physician_address'=>'',
        'previous_dentist'=>'','last_dental_visit'=>'','treatment_done'=>'','reason_for_visit'=>'Checkup','referred_by'=>'',
        'good_health'=>1,'medical_condition'=>0,'medical_condition_detail'=>'','serious_illness'=>0,'serious_illness_detail'=>'',
        'hospitalized'=>0,'hospitalized_detail'=>'','medication'=>0,'medication_detail'=>'','smoke'=>0,'alcohol'=>0,'drugs'=>0,
        'allergy'=>0,'allergy_detail'=>'','pregnant'=>0,'nursing'=>0,'birth_control'=>0,'blood_type'=>'','blood_pressure'=>'',
        'cond_others'=>'','conditions'=>[],'consent_name'=>'Feature Test Patient','consent_for'=>'myself',
    ];
    $profileResult = $patients->completeProfileByStaff($testPatientId, $profileData, 7);
    expectTrue($profileResult['success'], 'Front-desk staff can complete the existing patient profile.');
    $readyStatus = $conn->query("SELECT checkin_status FROM appointment_checkins WHERE appointment_id=" . (int) $booking['appointment_id'])->fetchColumn();
    expectTrue($readyStatus === 'Ready', 'Completing the form moves the checked-in patient to Ready.');

    $completed = $appointments->updateAppointmentStatus((int) $booking['appointment_id'], 'Completed', 7);
    expectTrue($completed['success'], 'Confirmed appointments can be completed with an audit record.');
    $invalid = $appointments->updateAppointmentStatus((int) $booking['appointment_id'], 'Confirmed', 7);
    expectTrue(!$invalid['success'], 'Invalid reverse status transitions are rejected.');

    $expiring = $appointments->bookAppointment($testPatientId, $futureSchedule['clinic_id'], [1], $futureSchedule['sched_date'], $futureSchedule['schedule_id']);
    expectTrue(is_array($expiring), 'A second provisional booking can be created for expiry testing.');
    $createdIds[] = (int) $expiring['appointment_id'];
    $conn->prepare("UPDATE appointments SET payment_deadline_at=DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE appointment_id=:id")
        ->execute([':id' => $expiring['appointment_id']]);
    expectTrue($deposits->expireUnpaidAppointments() >= 1, 'Expired unpaid appointments are cancelled automatically.');
    $expired = $conn->query("SELECT a.status, d.status AS deposit_status FROM appointments a JOIN appointment_deposits d ON d.appointment_id=a.appointment_id WHERE a.appointment_id=" . (int) $expiring['appointment_id'])->fetch(PDO::FETCH_ASSOC);
    expectTrue($expired['status'] === 'Cancelled' && $expired['deposit_status'] === 'Expired', 'Expiry releases the booking and marks its deposit expired.');
} finally {
    foreach ($createdIds as $appointmentId) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='appointment' AND entity_id=:id")->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointment_checkins WHERE appointment_id=:id')->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointment_deposits WHERE appointment_id=:id')->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointment_services WHERE appointment_id=:id')->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointments WHERE appointment_id=:id')->execute([':id' => $appointmentId]);
    }
    if ($testPatientId) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='patient' AND entity_id=:id")->execute([':id' => $testPatientId]);
        $conn->prepare('DELETE FROM patient_conditions WHERE patient_id=:id')->execute([':id' => $testPatientId]);
        $conn->prepare('DELETE FROM patient_consent WHERE patient_id=:id')->execute([':id' => $testPatientId]);
        $conn->prepare('DELETE FROM patient_dental_history WHERE patient_id=:id')->execute([':id' => $testPatientId]);
        $conn->prepare('DELETE FROM patient_medical_history WHERE patient_id=:id')->execute([':id' => $testPatientId]);
        $conn->prepare('DELETE FROM patients WHERE patient_id=:id')->execute([':id' => $testPatientId]);
    }
}

echo "Feature workflow test completed.\n";
