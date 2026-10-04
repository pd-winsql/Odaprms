<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';
require_once __DIR__ . '/../apps/models/depositModel.php';
require_once __DIR__ . '/../apps/models/patientModel.php';

function transferExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$appointments = new Appointment($conn);
$deposits = new DepositModel($conn);
$patients = new Patient($conn);
$appointmentIds = [];
$patientIds = [];
$scheduleIds = [];
$qaUserId = 0;

try {
    $clinicId = (int) $conn->query('SELECT clinic_id FROM clinics ORDER BY clinic_id LIMIT 1')->fetchColumn();
    $serviceId = (int) $conn->query('SELECT service_id FROM services WHERE is_active=1 ORDER BY service_id LIMIT 1')->fetchColumn();
    $staffId = (int) $conn->query("SELECT id FROM users WHERE user_role='Dental Assistant' ORDER BY id LIMIT 1")->fetchColumn();
    transferExpect($clinicId > 0 && $serviceId > 0 && $staffId > 0, 'Clinic, service, and dental assistant fixtures exist.');

    $dateCheck = $conn->prepare('SELECT COUNT(*) FROM schedules WHERE sched_date=:date');
    $dates = [];
    for ($days = 90; $days <= 730 && count($dates) < 3; $days++) {
        $candidate = date('Y-m-d', strtotime("+{$days} days"));
        $dateCheck->execute([':date' => $candidate]);
        if ((int) $dateCheck->fetchColumn() === 0) $dates[] = $candidate;
    }
    transferExpect(count($dates) === 3, 'Three isolated replacement-workflow dates are available.');

    $insertSchedule = $conn->prepare('INSERT INTO schedules(clinic_id,sched_date,max_appointments) VALUES(:clinic,:date,2)');
    foreach ($dates as $date) {
        $insertSchedule->execute([':clinic' => $clinicId, ':date' => $date]);
        $scheduleIds[] = (int) $conn->lastInsertId();
    }

    $email = 'transfer-' . bin2hex(random_bytes(4)) . '@example.invalid';
    $conn->prepare("INSERT INTO users (email,password,user_role) VALUES (?,?,'Patient')")->execute([$email,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
    $qaUserId = (int) $conn->lastInsertId();
    $patientIds[] = (int) $patients->createPatient($qaUserId, 'Transfer', 'Patient', '', 30, 'Prefer not to say', '09123456781', $email, '1996-01-01');
    $patientIds[] = (int) $patients->createPatient(null, 'Different', 'Patient', '', 31, 'Prefer not to say', '09123456782', 'different-' . bin2hex(random_bytes(4)) . '@example.invalid', '1995-01-01');
    transferExpect(min($patientIds) > 0, 'Temporary patients were created.');

    $source = $appointments->bookAppointment($patientIds[0], $clinicId, [$serviceId], $dates[0], $scheduleIds[0]);
    $target = $appointments->bookAppointment($patientIds[0], $clinicId, [$serviceId], $dates[1], $scheduleIds[1]);
    $otherPatientTarget = $appointments->bookAppointment($patientIds[1], $clinicId, [$serviceId], $dates[2], $scheduleIds[2]);
    transferExpect(($source['success'] ?? false) && ($target['success'] ?? false) && ($otherPatientTarget['success'] ?? false), 'Original and replacement appointments were patient-booked.');
    $appointmentIds = [(int) $source['appointment_id'], (int) $target['appointment_id'], (int) $otherPatientTarget['appointment_id']];

    foreach ($appointmentIds as $appointmentId) {
        transferExpect($appointments->updateAppointmentStatus($appointmentId, 'Awaiting Deposit', $staffId)['success'], "Appointment #{$appointmentId} was accepted by clinic staff.");
    }

    $reference = 'TRANSFER' . date('YmdHis') . random_int(100, 999);
    transferExpect($deposits->submitReceipt($appointmentIds[0], $reference, 'storage/payment_receipts/test.jpg', 'image/jpeg')['success'], 'The original deposit receipt was submitted.');
    $sourceDepositId = (int) $conn->query('SELECT deposit_id FROM appointment_deposits WHERE appointment_id=' . $appointmentIds[0])->fetchColumn();
    transferExpect($deposits->verify($sourceDepositId, $staffId)['success'], 'The original deposit was verified and its appointment confirmed.');

    transferExpect(!$deposits->transferDeposit($appointmentIds[0], $appointmentIds[1], $staffId, '')['success'], 'A transfer without a staff reason is rejected.');
    transferExpect(!$deposits->transferDeposit($appointmentIds[0], $appointmentIds[1], $staffId, 'Patient requested a new date.')['success'], 'A transfer from an appointment that is not cancelled is rejected.');

    $cancelReason = 'Patient requested rescheduling to the newly booked date.';
    transferExpect($appointments->updateAppointmentStatus($appointmentIds[0], 'Cancelled', $staffId, $cancelReason)['success'], 'The original confirmed appointment was cancelled by clinic staff.');
    transferExpect(!$deposits->transferDeposit($appointmentIds[0], $appointmentIds[2], $staffId, 'Move payment to the other booking.')['success'], 'A transfer to another patient is rejected.');

    transferExpect(count($deposits->getPatientTransferCredits($qaUserId, $appointmentIds[1])) === 1, 'Patient sees their eligible verified credit on the accepted replacement.');
    transferExpect($deposits->getPatientTransferCredits($qaUserId, $appointmentIds[2]) === [], 'Patient cannot list credits for another patient replacement.');
    transferExpect(!$deposits->transferDeposit($appointmentIds[0], $appointmentIds[1], $staffId, 'Self-service transfer.', true)['success'], 'Staff cannot masquerade as a self-service patient.');
    transferExpect(!$deposits->transferDeposit($appointmentIds[0], $appointmentIds[1], $qaUserId, 'Staff transfer.')['success'], 'Patient cannot invoke the staff transfer path.');

    // Reversible transaction probes: no actual payments or emails are delivered.
    $probe = static function (callable $test) use ($conn): void {
        $conn->beginTransaction();
        try { $test(); } finally { $conn->rollBack(); }
    };
    $expectPatientFailure = static function (string $label) use ($deposits,$appointmentIds,$qaUserId): void {
        try {
            $deposits->transferDeposit($appointmentIds[0],$appointmentIds[1],$qaUserId,'Patient applied an existing verified deposit.',true);
        } catch (RuntimeException $e) { transferExpect(true,$label); return; }
        throw new RuntimeException($label . ' was incorrectly allowed.');
    };
    $probe(static function () use ($conn,$patientIds,$expectPatientFailure) {
        $conn->exec('UPDATE patients SET user_id=NULL WHERE patient_id=' . $patientIds[0]);
        $expectPatientFailure('Both appointments sharing a patient ID does not grant ownership.');
    });
    $probe(static function () use ($conn,$appointmentIds,$expectPatientFailure) {
        $conn->exec("UPDATE appointments SET payment_deadline_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE appointment_id=" . $appointmentIds[1]);
        $expectPatientFailure('Expired replacement payment window is rejected.');
    });
    $probe(static function () use ($conn,$appointmentIds,$expectPatientFailure) {
        $conn->exec("UPDATE appointments SET status='Pending Review' WHERE appointment_id=" . $appointmentIds[1]);
        $expectPatientFailure('Self-service cannot bypass staff appointment acceptance.');
    });
    $probe(static function () use ($conn,$appointmentIds,$expectPatientFailure) {
        $conn->exec('UPDATE appointment_deposits SET amount=1 WHERE appointment_id=' . $appointmentIds[0]);
        $expectPatientFailure('Insufficient credit is rejected without partial transfer.');
    });
    $probe(static function () use ($conn,$appointmentIds,$expectPatientFailure) {
        $conn->exec('UPDATE appointment_deposits SET verified_at=NULL WHERE appointment_id=' . $appointmentIds[0]);
        $expectPatientFailure('Credit without previous verification is rejected.');
    });
    foreach (['Refunded','Forfeited','Under Review'] as $ineligibleStatus) {
        $probe(static function () use ($conn,$appointmentIds,$expectPatientFailure,$ineligibleStatus) {
            $conn->prepare('UPDATE appointment_deposits SET status=? WHERE appointment_id=?')->execute([$ineligibleStatus,$appointmentIds[0]]);
            $expectPatientFailure($ineligibleStatus . ' credit cannot be applied.');
        });
    }
    $probe(static function () use ($conn,$deposits,$appointmentIds,$qaUserId,$staffId,$expectPatientFailure) {
        $result=$deposits->transferDeposit($appointmentIds[0],$appointmentIds[1],$qaUserId,'Patient applied an existing verified deposit.',true);
        transferExpect($result['success'] && $result['audit']['performed_by_role']==='Patient', 'Patient self-service confirms replacement and records Patient audit actor.');
        transferExpect((int)$conn->query('SELECT verified_by_user_id FROM appointment_deposits WHERE appointment_id=' . $appointmentIds[1])->fetchColumn()===$staffId,'Original staff verifier is preserved on reused credit.');
        $expectPatientFailure('Already transferred credit cannot be spent twice.');
        transferExpect($deposits->getPatientTransferCredits($qaUserId,$appointmentIds[1])===[], 'Confirmed replacement no longer offers credit application.');
    });

    $transferReason = 'Patient requested deposit transfer for rescheduling.';
    $result = $deposits->transferDeposit($appointmentIds[0], $appointmentIds[1], $staffId, $transferReason);
    transferExpect(($result['success'] ?? false) && str_starts_with($result['appointment_code'] ?? '', 'AVC-'), 'The refundable deposit transfers to the same patient replacement and generates a code.');

    $sourceState = $conn->query('SELECT a.status appointment_status,d.status deposit_status,d.transfer_reason FROM appointments a JOIN appointment_deposits d ON d.appointment_id=a.appointment_id WHERE a.appointment_id=' . $appointmentIds[0])->fetch(PDO::FETCH_ASSOC);
    $targetState = $conn->query('SELECT a.status appointment_status,a.appointment_code,d.status deposit_status,d.transferred_from_appointment_id,d.transfer_reason FROM appointments a JOIN appointment_deposits d ON d.appointment_id=a.appointment_id WHERE a.appointment_id=' . $appointmentIds[1])->fetch(PDO::FETCH_ASSOC);
    transferExpect($sourceState['appointment_status'] === 'Cancelled' && $sourceState['deposit_status'] === 'Transferred' && $sourceState['transfer_reason'] === $transferReason, 'The original appointment stays cancelled and records the outgoing transfer.');
    transferExpect($targetState['appointment_status'] === 'Confirmed' && $targetState['deposit_status'] === 'Verified' && (int) $targetState['transferred_from_appointment_id'] === $appointmentIds[0] && $targetState['transfer_reason'] === $transferReason, 'The replacement is confirmed with verified transferred payment details.');

    $auditStmt = $conn->prepare("SELECT action,new_values FROM audit_logs WHERE entity_type='appointment' AND entity_id=:id AND action=:action ORDER BY audit_log_id DESC LIMIT 1");
    $auditStmt->execute([':id' => $appointmentIds[0], ':action' => 'deposit_transferred_out']);
    $sourceAudit = $auditStmt->fetch(PDO::FETCH_ASSOC);
    $auditStmt->execute([':id' => $appointmentIds[1], ':action' => 'deposit_transferred']);
    $targetAudit = $auditStmt->fetch(PDO::FETCH_ASSOC);
    transferExpect($sourceAudit && str_contains($sourceAudit['new_values'], $transferReason), 'The original appointment audit records the target and transfer reason.');
    transferExpect($targetAudit && str_contains($targetAudit['new_values'], $transferReason), 'The replacement appointment audit records the source and transfer reason.');

    $notificationCount = (int) $conn->query("SELECT COUNT(*) FROM appointment_email_notifications WHERE appointment_id={$appointmentIds[1]} AND notification_type='appointment_confirmed_code'")->fetchColumn();
    transferExpect($notificationCount >= 1, 'The existing replacement confirmation notification is preserved.');
} finally {
    foreach (array_reverse($appointmentIds) as $appointmentId) {
        $conn->prepare('DELETE FROM appointment_email_notifications WHERE appointment_id=:id')->execute([':id' => $appointmentId]);
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='appointment' AND entity_id=:id")->execute([':id' => $appointmentId]);
        foreach (['appointment_billings', 'appointment_checkins', 'appointment_deposits', 'appointment_services'] as $table) {
            $conn->prepare("DELETE FROM {$table} WHERE appointment_id=:id")->execute([':id' => $appointmentId]);
        }
        $conn->prepare('DELETE FROM appointments WHERE appointment_id=:id')->execute([':id' => $appointmentId]);
    }
    foreach ($patientIds as $patientId) {
        if ($patientId) $conn->prepare('DELETE FROM patients WHERE patient_id=:id')->execute([':id' => $patientId]);
    }
    foreach ($scheduleIds as $scheduleId) {
        $conn->prepare('DELETE FROM schedules WHERE schedule_id=:id')->execute([':id' => $scheduleId]);
    }
    if ($qaUserId) $conn->prepare('DELETE FROM users WHERE id=?')->execute([$qaUserId]);
}

echo "Deposit transfer workflow test completed.\n";
