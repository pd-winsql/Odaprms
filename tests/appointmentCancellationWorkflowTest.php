<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';
require_once __DIR__ . '/../apps/models/depositModel.php';
require_once __DIR__ . '/../apps/models/patientModel.php';

function cancellationExpect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$appointments = new Appointment($conn);
$deposits = new DepositModel($conn);
$patients = new Patient($conn);
$appointmentId = 0;
$patientId = 0;
$scheduleId = 0;

try {
    $clinicId = (int) $conn->query('SELECT clinic_id FROM clinics ORDER BY clinic_id LIMIT 1')->fetchColumn();
    $serviceId = (int) $conn->query('SELECT service_id FROM services WHERE is_active=1 ORDER BY service_id LIMIT 1')->fetchColumn();
    $staffId = (int) $conn->query("SELECT id FROM users WHERE user_role IN ('Admin','Dental Assistant') ORDER BY id LIMIT 1")->fetchColumn();
    cancellationExpect($clinicId > 0 && $serviceId > 0 && $staffId > 0, 'Required clinic, service, and staff fixtures exist.');

    $date = null;
    $dateCheck = $conn->prepare('SELECT COUNT(*) FROM schedules WHERE sched_date=:date');
    for ($days = 30; $days <= 365; $days++) {
        $candidate = date('Y-m-d', strtotime("+{$days} days"));
        $dateCheck->execute([':date' => $candidate]);
        if ((int) $dateCheck->fetchColumn() === 0) { $date = $candidate; break; }
    }
    cancellationExpect($date !== null, 'An isolated future schedule date is available.');

    $schedule = $conn->prepare('INSERT INTO schedules(clinic_id,sched_date,max_appointments) VALUES(:clinic,:date,1)');
    $schedule->execute([':clinic' => $clinicId, ':date' => $date]);
    $scheduleId = (int) $conn->lastInsertId();

    $email = 'cancel-workflow-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $patientId = (int) $patients->createPatient(null, 'Cancellation', 'Workflow', '', 30, 'Prefer not to say', '09123456789', $email, '1996-01-01');
    cancellationExpect($patientId > 0, 'Temporary patient was created.');

    $booking = $appointments->bookAppointment($patientId, $clinicId, [$serviceId], $date, $scheduleId);
    cancellationExpect(($booking['success'] ?? false) === true, 'Future appointment was booked.');
    $appointmentId = (int) $booking['appointment_id'];
    cancellationExpect($appointments->updateAppointmentStatus($appointmentId, 'Awaiting Deposit', $staffId)['success'], 'Appointment was accepted for payment.');

    $reference = 'CANCEL' . date('YmdHis') . random_int(100, 999);
    cancellationExpect($deposits->submitReceipt($appointmentId, $reference, 'storage/payment_receipts/test.jpg', 'image/jpeg')['success'], 'Deposit receipt was submitted.');
    $depositId = (int) $conn->query('SELECT deposit_id FROM appointment_deposits WHERE appointment_id=' . $appointmentId)->fetchColumn();
    cancellationExpect($deposits->verify($depositId, $staffId)['success'], 'Deposit was verified and appointment confirmed.');

    $missingReason = $appointments->updateAppointmentStatus($appointmentId, 'Cancelled', $staffId);
    cancellationExpect(!$missingReason['success'], 'Cancellation without a reason is rejected.');

    $reason = 'Patient emergency - requested reschedule.';
    cancellationExpect($appointments->updateAppointmentStatus($appointmentId, 'Cancelled', $staffId, $reason)['success'], 'Confirmed appointment was cancelled with a reason.');

    $appointment = $conn->query('SELECT status,cancellation_reason FROM appointments WHERE appointment_id=' . $appointmentId)->fetch(PDO::FETCH_ASSOC);
    $deposit = $conn->query('SELECT status,refund_reason FROM appointment_deposits WHERE appointment_id=' . $appointmentId)->fetch(PDO::FETCH_ASSOC);
    cancellationExpect($appointment['status'] === 'Cancelled' && $appointment['cancellation_reason'] === $reason, 'Cancellation reason is stored on the appointment.');
    cancellationExpect($deposit['status'] === 'For Refund' && $deposit['refund_reason'] === $reason, 'Verified deposit remains eligible for refund or transfer with the same reason.');

    $upcomingIds = array_map('intval', array_column($appointments->getPatientUpcomingAppointments($patientId), 'appointment_id'));
    cancellationExpect(!in_array($appointmentId, $upcomingIds, true), 'Cancelled appointment is hidden from the patient upcoming list.');
} finally {
    if ($appointmentId) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='appointment' AND entity_id=:id")->execute([':id' => $appointmentId]);
        foreach (['appointment_billings', 'appointment_checkins', 'appointment_deposits', 'appointment_services'] as $table) {
            $conn->prepare("DELETE FROM {$table} WHERE appointment_id=:id")->execute([':id' => $appointmentId]);
        }
        $conn->prepare('DELETE FROM appointments WHERE appointment_id=:id')->execute([':id' => $appointmentId]);
    }
    if ($patientId) $conn->prepare('DELETE FROM patients WHERE patient_id=:id')->execute([':id' => $patientId]);
    if ($scheduleId) $conn->prepare('DELETE FROM schedules WHERE schedule_id=:id')->execute([':id' => $scheduleId]);
}

echo "Appointment cancellation workflow test completed.\n";
