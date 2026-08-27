<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';

function noShowExpect($condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$appointments = new Appointment($conn);
$appointmentIds = [];
$patientIds = [];
$scheduleIds = [];
$clinicId = null;

try {
    $staffId = (int) $conn->query("SELECT id FROM users WHERE user_role = 'Dental Assistant' ORDER BY id LIMIT 1")->fetchColumn();
    noShowExpect($staffId > 0, 'A dental assistant is available for the no-show policy test.');

    $token = bin2hex(random_bytes(5));
    $conn->prepare("INSERT INTO clinics (clinic_name, clinic_address, clinic_contact) VALUES (:name, 'Test address', '09123456789')")
        ->execute([':name' => 'No-show Policy ' . $token]);
    $clinicId = (int) $conn->lastInsertId();

    $insertSchedule = $conn->prepare("INSERT INTO schedules (clinic_id, sched_date, start_time, end_time, max_appointments) VALUES (:clinic, :date, '00:00:00', '23:59:59', 10)");
    foreach ([date('Y-m-d', strtotime('-1 day')), date('Y-m-d'), date('Y-m-d', strtotime('+1 day'))] as $date) {
        $insertSchedule->execute([':clinic' => $clinicId, ':date' => $date]);
        $scheduleIds[$date] = (int) $conn->lastInsertId();
    }

    $insertPatient = $conn->prepare("INSERT INTO patients (firstname, lastname, email) VALUES (:firstname, 'NoShowTest', :email)");
    $insertAppointment = $conn->prepare("
        INSERT INTO appointments (patient_id, schedule_id, clinic_id, date, status, deposit_required, appointment_code, confirmed_at)
        VALUES (:patient, :schedule, :clinic, :date, 'Confirmed', 0, :code, NOW())
    ");
    $makeAppointment = function (string $label, string $date) use (
        $conn,
        $clinicId,
        $scheduleIds,
        $insertPatient,
        $insertAppointment,
        &$patientIds,
        &$appointmentIds,
        $token
    ): int {
        $insertPatient->execute([
            ':firstname' => $label,
            ':email' => strtolower($label) . '-' . $token . '@example.invalid',
        ]);
        $patientId = (int) $conn->lastInsertId();
        $patientIds[] = $patientId;
        $insertAppointment->execute([
            ':patient' => $patientId,
            ':schedule' => $scheduleIds[$date],
            ':clinic' => $clinicId,
            ':date' => $date,
            ':code' => 'NS-' . strtoupper(bin2hex(random_bytes(4))),
        ]);
        $appointmentId = (int) $conn->lastInsertId();
        $appointmentIds[] = $appointmentId;
        return $appointmentId;
    };

    $futureDate = date('Y-m-d', strtotime('+1 day'));
    $futureAppointmentId = $makeAppointment('Future', $futureDate);
    $futureResult = $appointments->updateAppointmentStatus($futureAppointmentId, 'No-show', $staffId);
    noShowExpect(!$futureResult['success'] && str_contains($futureResult['message'], 'appointment date'), 'A future appointment cannot be marked as no-show.');

    $today = date('Y-m-d');
    $pastAppointmentId = $makeAppointment('PastUnarrived', date('Y-m-d', strtotime('-1 day')));
    noShowExpect($appointments->updateAppointmentStatus($pastAppointmentId, 'No-show', $staffId)['success'], 'An unarrived confirmed patient can be marked as no-show after the appointment date.');
    $validAppointmentId = $makeAppointment('Unarrived', $today);
    $validResult = $appointments->updateAppointmentStatus($validAppointmentId, 'No-show', $staffId);
    noShowExpect($validResult['success'], 'An unarrived confirmed patient can be marked as no-show after today\'s clinic window begins.');

    $checkedInAppointmentId = $makeAppointment('CheckedIn', $today);
    $conn->prepare("
        INSERT INTO appointment_checkins (
            appointment_id, arrived_at, checked_in_by_user_id, lookup_method,
            checkin_status, profile_required_at_arrival, ready_at, queue_status, queue_entered_at
        ) VALUES (:appointment, NOW(), :staff, 'Code', 'Ready', 0, NOW(), 'Waiting', NOW())
    ")->execute([':appointment' => $checkedInAppointmentId, ':staff' => $staffId]);
    $checkedInResult = $appointments->updateAppointmentStatus($checkedInAppointmentId, 'No-show', $staffId);
    noShowExpect(!$checkedInResult['success'] && str_contains($checkedInResult['message'], 'checked-in'), 'A patient with a check-in record cannot be marked as no-show.');

    $statusStmt = $conn->prepare('SELECT status FROM appointments WHERE appointment_id = :id');
    $statusStmt->execute([':id' => $checkedInAppointmentId]);
    noShowExpect($statusStmt->fetchColumn() === 'Confirmed', 'A rejected no-show request leaves the appointment status unchanged.');
} finally {
    foreach (array_reverse($appointmentIds) as $appointmentId) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type = 'appointment' AND entity_id = :id")->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointment_checkins WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointment_deposits WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointments WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
    }
    foreach (array_reverse($patientIds) as $patientId) {
        $conn->prepare('DELETE FROM patients WHERE patient_id = :id')->execute([':id' => $patientId]);
    }
    foreach (array_reverse(array_values($scheduleIds)) as $scheduleId) {
        $conn->prepare('DELETE FROM schedules WHERE schedule_id = :id')->execute([':id' => $scheduleId]);
    }
    if ($clinicId !== null) {
        $conn->prepare('DELETE FROM clinics WHERE clinic_id = :id')->execute([':id' => $clinicId]);
    }
}

echo "No-show policy test completed.\n";
