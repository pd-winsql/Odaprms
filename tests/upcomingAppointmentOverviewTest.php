<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';
require_once __DIR__ . '/../apps/models/depositModel.php';
require_once __DIR__ . '/../apps/models/patientModel.php';
require_once __DIR__ . '/../apps/models/scheduleModel.php';

function overviewExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$appointments = new Appointment($conn);
$deposits = new DepositModel($conn);
$patients = new Patient($conn);
$schedules = new Schedule($conn);
$appointmentIds = [];
$scheduleIds = [];
$patientId = 0;

try {
    $clinicId = (int) $conn->query('SELECT clinic_id FROM clinics ORDER BY clinic_id LIMIT 1')->fetchColumn();
    $serviceId = (int) $conn->query('SELECT service_id FROM services WHERE is_active=1 ORDER BY service_id LIMIT 1')->fetchColumn();
    $serviceName = (string) $conn->query('SELECT service_name FROM services WHERE service_id=' . $serviceId)->fetchColumn();
    $staffId = (int) $conn->query("SELECT id FROM users WHERE user_role='Dental Assistant' ORDER BY id LIMIT 1")->fetchColumn();
    overviewExpect($clinicId > 0 && $serviceId > 0 && $staffId > 0, 'Overview fixtures are available.');

    $dateCheck = $conn->prepare('SELECT COUNT(*) FROM schedules WHERE sched_date=:date');
    $dates = [];
    for ($days = 180; $days <= 730 && count($dates) < 2; $days++) {
        $candidate = date('Y-m-d', strtotime("+{$days} days"));
        $dateCheck->execute([':date' => $candidate]);
        if ((int) $dateCheck->fetchColumn() === 0) $dates[] = $candidate;
    }
    overviewExpect(count($dates) === 2, 'Isolated upcoming schedule dates are available.');

    $insertSchedule = $conn->prepare('INSERT INTO schedules(clinic_id,sched_date,max_appointments) VALUES(:clinic,:date,4)');
    foreach ($dates as $date) {
        $insertSchedule->execute([':clinic' => $clinicId, ':date' => $date]);
        $scheduleIds[] = (int) $conn->lastInsertId();
    }

    $patientId = (int) $patients->createPatient(null, 'Overview', 'Patient', '', 29, 'Prefer not to say', '09123456783', 'overview-' . bin2hex(random_bytes(4)) . '@example.invalid', '1997-01-01');
    overviewExpect($patientId > 0, 'Temporary overview patient was created.');

    $pending = $appointments->bookAppointment($patientId, $clinicId, [$serviceId], $dates[0], $scheduleIds[0]);
    overviewExpect(($pending['success'] ?? false) === true, 'A patient-booked upcoming appointment was created.');
    $appointmentIds[] = (int) $pending['appointment_id'];

    $activeRoster = $schedules->getActiveAppointmentsByScheduleIds([$scheduleIds[0]]);
    $pendingRow = $activeRoster[$scheduleIds[0]][0] ?? null;
    overviewExpect($pendingRow && $pendingRow['status'] === 'Pending Review', 'Admin overview includes active appointment requests occupying the schedule.');
    overviewExpect(($pendingRow['services'][0]['service_name'] ?? '') === $serviceName, 'Admin overview includes each patient’s selected service.');
    overviewExpect(empty($schedules->getConfirmedAppointmentsByScheduleIds([$scheduleIds[0]])), 'Dental Assistant confirmed roster remains limited to confirmed appointments.');

    overviewExpect($appointments->updateAppointmentStatus($appointmentIds[0], 'Awaiting Deposit', $staffId)['success'], 'Clinic staff accepted the appointment for payment.');
    overviewExpect($deposits->submitReceipt($appointmentIds[0], 'OVERVIEW' . date('YmdHis') . random_int(100, 999), 'storage/payment_receipts/test.jpg', 'image/jpeg')['success'], 'The test deposit was submitted.');
    $depositId = (int) $conn->query('SELECT deposit_id FROM appointment_deposits WHERE appointment_id=' . $appointmentIds[0])->fetchColumn();
    overviewExpect($deposits->verify($depositId, $staffId)['success'], 'The appointment was confirmed.');
    overviewExpect(count($schedules->getConfirmedAppointmentsByScheduleIds([$scheduleIds[0]])[$scheduleIds[0]] ?? []) === 1, 'Confirmed appointments remain visible in the established schedule roster.');

    $cancelled = $appointments->bookAppointment($patientId, $clinicId, [$serviceId], $dates[1], $scheduleIds[1]);
    overviewExpect(($cancelled['success'] ?? false) === true, 'A second upcoming appointment was created for cancellation coverage.');
    $appointmentIds[] = (int) $cancelled['appointment_id'];
    overviewExpect($appointments->updateAppointmentStatus($appointmentIds[1], 'Awaiting Deposit', $staffId)['success'], 'The second appointment was accepted.');
    overviewExpect($appointments->updateAppointmentStatus($appointmentIds[1], 'Cancelled', $staffId, 'Patient cancelled the request.')['success'], 'The second appointment was cancelled.');
    overviewExpect(empty($schedules->getActiveAppointmentsByScheduleIds([$scheduleIds[1]])), 'Cancelled appointments are excluded from the upcoming roster.');
} finally {
    foreach (array_reverse($appointmentIds) as $appointmentId) {
        $conn->prepare('DELETE FROM appointment_email_notifications WHERE appointment_id=:id')->execute([':id' => $appointmentId]);
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='appointment' AND entity_id=:id")->execute([':id' => $appointmentId]);
        foreach (['appointment_billings', 'appointment_checkins', 'appointment_deposits', 'appointment_services'] as $table) {
            $conn->prepare("DELETE FROM {$table} WHERE appointment_id=:id")->execute([':id' => $appointmentId]);
        }
        $conn->prepare('DELETE FROM appointments WHERE appointment_id=:id')->execute([':id' => $appointmentId]);
    }
    if ($patientId) $conn->prepare('DELETE FROM patients WHERE patient_id=:id')->execute([':id' => $patientId]);
    foreach ($scheduleIds as $scheduleId) $conn->prepare('DELETE FROM schedules WHERE schedule_id=:id')->execute([':id' => $scheduleId]);
}

echo "Upcoming appointment overview test completed.\n";
