<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/helpers/bookingPolicy.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';
require_once __DIR__ . '/../apps/models/clinicModel.php';
require_once __DIR__ . '/../apps/models/patientModel.php';
require_once __DIR__ . '/../apps/models/scheduleModel.php';

function bookingPolicyExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$appointments = new Appointment($conn);
$clinics = new Clinic($conn);
$patients = new Patient($conn);
$schedules = new Schedule($conn);
$appointmentIds = [];
$scheduleIds = [];
$patientId = 0;
$clinicId = 0;
$originalLeadDays = (int) $conn->query('SELECT minimum_booking_lead_days FROM site_settings WHERE id = 1')->fetchColumn();

try {
    $serviceId = (int) $conn->query('SELECT service_id FROM services WHERE is_active = 1 ORDER BY service_id LIMIT 1')->fetchColumn();
    bookingPolicyExpect($serviceId > 0, 'An active service is available for booking-policy coverage.');

    $clinicId = $clinics->createClinic(
        'Booking Policy Test ' . bin2hex(random_bytes(4)),
        'Booking policy test address',
        null
    );
    bookingPolicyExpect($clinicId > 0, 'An isolated clinic was created for booking-policy coverage.');

    $patientId = (int) $patients->createPatient(
        null,
        'Booking',
        'Policy',
        '',
        30,
        'Prefer not to say',
        '09123456780',
        'booking-policy-' . bin2hex(random_bytes(4)) . '@example.invalid',
        '1996-01-01'
    );
    bookingPolicyExpect($patientId > 0, 'An isolated patient was created for booking-policy coverage.');

    $nearDate = date('Y-m-d', strtotime('+6 days'));
    $boundaryDate = date('Y-m-d', strtotime('+7 days'));
    $insertSchedule = $conn->prepare(
        'INSERT INTO schedules (clinic_id, sched_date, max_appointments) VALUES (:clinic, :date, 5)'
    );
    foreach ([$nearDate, $boundaryDate] as $date) {
        $insertSchedule->execute([':clinic' => $clinicId, ':date' => $date]);
        $scheduleIds[] = (int) $conn->lastInsertId();
    }

    $conn->exec('UPDATE site_settings SET minimum_booking_lead_days = 7 WHERE id = 1');
    bookingPolicyExpect(BookingPolicy::minimumLeadDays($conn) === 7, 'The saved global seven-day booking policy is loaded.');
    $earliestDate = BookingPolicy::earliestBookableDate(7);
    bookingPolicyExpect($earliestDate === $boundaryDate, 'The earliest bookable date uses calendar-day arithmetic.');

    $staffVisibleIds = array_map('intval', array_column(
        $schedules->getAvailableSchedulesByClinic($clinicId),
        'schedule_id'
    ));
    bookingPolicyExpect(
        in_array($scheduleIds[0], $staffVisibleIds, true) && in_array($scheduleIds[1], $staffVisibleIds, true),
        'The booking policy does not hide near-term schedules from clinic operations.'
    );

    $patientVisibleIds = array_map('intval', array_column(
        $schedules->getAvailableSchedulesByClinic($clinicId, $earliestDate),
        'schedule_id'
    ));
    bookingPolicyExpect(
        !in_array($scheduleIds[0], $patientVisibleIds, true) && in_array($scheduleIds[1], $patientVisibleIds, true),
        'Patient schedule availability excludes dates inside the notice period.'
    );

    $blocked = $appointments->bookAppointment($patientId, $clinicId, [$serviceId], $nearDate, $scheduleIds[0]);
    bookingPolicyExpect(
        !($blocked['success'] ?? false) && str_contains($blocked['message'] ?? '', 'at least 7 calendar days'),
        'The appointment model blocks a direct request inside the seven-day notice period.'
    );

    $allowed = $appointments->bookAppointment($patientId, $clinicId, [$serviceId], $boundaryDate, $scheduleIds[1]);
    bookingPolicyExpect(($allowed['success'] ?? false) === true, 'A request exactly seven calendar days ahead is allowed.');
    $appointmentIds[] = (int) $allowed['appointment_id'];

    $conn->exec('UPDATE site_settings SET minimum_booking_lead_days = 14 WHERE id = 1');
    bookingPolicyExpect(
        (int) $conn->query('SELECT COUNT(*) FROM appointments WHERE appointment_id = ' . $appointmentIds[0])->fetchColumn() === 1,
        'Increasing the notice period does not change an existing appointment.'
    );

    $conn->exec('UPDATE site_settings SET minimum_booking_lead_days = 3 WHERE id = 1');
    $allowedAfterChange = $appointments->bookAppointment($patientId, $clinicId, [$serviceId], $nearDate, $scheduleIds[0]);
    bookingPolicyExpect(
        ($allowedAfterChange['success'] ?? false) === true,
        'A newly reduced global notice period applies immediately to future requests.'
    );
    $appointmentIds[] = (int) $allowedAfterChange['appointment_id'];
} finally {
    foreach (array_reverse($appointmentIds) as $appointmentId) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type = 'appointment' AND entity_id = :id")
            ->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointment_services WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointments WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
    }
    foreach (array_reverse($scheduleIds) as $scheduleId) {
        $conn->prepare('DELETE FROM schedules WHERE schedule_id = :id')->execute([':id' => $scheduleId]);
    }
    if ($patientId > 0) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type = 'patient' AND entity_id = :id")
            ->execute([':id' => $patientId]);
        $conn->prepare('DELETE FROM patients WHERE patient_id = :id')->execute([':id' => $patientId]);
    }
    if ($clinicId > 0) {
        $conn->prepare('DELETE FROM clinics WHERE clinic_id = :id')->execute([':id' => $clinicId]);
    }
    $conn->prepare('UPDATE site_settings SET minimum_booking_lead_days = :days WHERE id = 1')
        ->execute([':days' => $originalLeadDays]);
}

echo "Booking lead-time test completed.\n";
