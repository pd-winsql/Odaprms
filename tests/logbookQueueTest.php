<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/logbookModel.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';

function queueExpect($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$logbook = new LogbookModel($conn);
$appointments = new Appointment($conn);
$appointmentIds = [];
$patientIds = [];
$createdScheduleId = null;

try {
    $clinicId = (int) $conn->query('SELECT clinic_id FROM clinics ORDER BY clinic_id LIMIT 1')->fetchColumn();
    $serviceId = (int) $conn->query('SELECT service_id FROM services WHERE is_active = 1 ORDER BY service_id LIMIT 1')->fetchColumn();
    $staffId = (int) $conn->query("SELECT id FROM users WHERE user_role IN ('Admin','Dental Assistant') ORDER BY id LIMIT 1")->fetchColumn();
    queueExpect($clinicId > 0 && $serviceId > 0 && $staffId > 0, 'Queue test fixtures are available.');

    $schedule = $conn->prepare('SELECT schedule_id FROM schedules WHERE clinic_id = :clinic AND sched_date = CURDATE() LIMIT 1');
    $schedule->execute([':clinic' => $clinicId]);
    $scheduleId = (int) $schedule->fetchColumn();
    if ($scheduleId <= 0) {
        $conn->prepare('INSERT INTO schedules (clinic_id, sched_date, max_appointments) VALUES (:clinic, CURDATE(), 50)')
            ->execute([':clinic' => $clinicId]);
        $scheduleId = (int) $conn->lastInsertId();
        $createdScheduleId = $scheduleId;
    }

    $insertPatient = $conn->prepare("
        INSERT INTO patients (firstname, lastname, email, profile_status, profile_completed_at)
        VALUES (:firstname, 'QueueTest', :email, 'Complete', NOW())
    ");
    $insertAppointment = $conn->prepare("
        INSERT INTO appointments (patient_id, schedule_id, clinic_id, date, status, deposit_required, appointment_code, confirmed_at)
        VALUES (:patient, :schedule, :clinic, CURDATE(), 'Checked In', 0, :code, NOW())
    ");
    $insertService = $conn->prepare('INSERT INTO appointment_services (appointment_id, service_id) VALUES (:appointment, :service)');
    $insertCheckin = $conn->prepare("
        INSERT INTO appointment_checkins (
            appointment_id, arrived_at, checked_in_by_user_id, lookup_method,
            checkin_status, profile_required_at_arrival, ready_at,
            queue_status, queue_entered_at
        ) VALUES (
            :appointment, :arrived, :staff, 'Code', 'Ready', 0, :arrived,
            'Waiting', :entered
        )
    ");

    foreach ([
        ['First', '2000-01-01 08:00:00'],
        ['Second', '2000-01-01 08:01:00'],
        ['Third', '2000-01-01 08:02:00'],
    ] as $index => [$firstname, $entered]) {
        $email = 'queue-' . bin2hex(random_bytes(5)) . '@example.invalid';
        $insertPatient->execute([':firstname' => $firstname, ':email' => $email]);
        $patientId = (int) $conn->lastInsertId();
        $patientIds[] = $patientId;

        $insertAppointment->execute([
            ':patient' => $patientId,
            ':schedule' => $scheduleId,
            ':clinic' => $clinicId,
            ':code' => 'QTEST-' . strtoupper(bin2hex(random_bytes(4))),
        ]);
        $appointmentId = (int) $conn->lastInsertId();
        $appointmentIds[] = $appointmentId;
        $insertService->execute([':appointment' => $appointmentId, ':service' => $serviceId]);
        $insertCheckin->execute([
            ':appointment' => $appointmentId,
            ':arrived' => $entered,
            ':staff' => $staffId,
            ':entered' => $entered,
        ]);
    }

    queueExpect((int) $logbook->getNextPatient()['appointment_id'] === $appointmentIds[0], 'Earliest eligible arrival is selected as next patient.');
    $preexistingActive = (int) $conn->query("SELECT COUNT(*) FROM appointments WHERE status = 'In Progress'")->fetchColumn() > 0;
    $outOfOrder = $appointments->updateAppointmentStatus($appointmentIds[1], 'In Progress', $staffId);
    queueExpect(
        !$outOfOrder['success'] && (
            str_contains($outOfOrder['message'], 'not next')
            || ($preexistingActive && str_contains($outOfOrder['message'], 'current patient'))
        ),
        'A later patient cannot bypass the queue. Result: ' . json_encode($outOfOrder)
    );

    queueExpect($logbook->placeOnHold($appointmentIds[0], $staffId, 'Patient is currently outside.')['success'], 'The next patient can be placed on hold with a reason.');
    queueExpect((int) $logbook->getNextPatient()['appointment_id'] === $appointmentIds[1], 'Placing a patient on hold advances the next ready patient.');
    queueExpect($logbook->returnToQueue($appointmentIds[0], $staffId)['success'], 'An on-hold patient can return to the queue.');
    queueExpect((int) $logbook->getNextPatient()['appointment_id'] === $appointmentIds[1], 'Returning places the patient at the end of the normal queue.');

    /* Verify the new staff override changes only the next patient and remains fully audited. */
    queueExpect($logbook->serveNext($appointmentIds[2], $staffId, 'Dentist requested this patient next.')['success'], 'Staff can select a ready patient to be served next.');
    queueExpect((int) $logbook->getNextPatient()['appointment_id'] === $appointmentIds[2], 'Serve Next moves the selected patient ahead of the FIFO queue.');

    if ($preexistingActive) {
        echo "SKIP: Treatment-start assertions require no pre-existing live visit.\n";
    } else {
        $started = $appointments->updateAppointmentStatus($appointmentIds[2], 'In Progress', $staffId);
        queueExpect($started['success'], 'The selected next patient can start treatment.');
        $parallelStart = $appointments->updateAppointmentStatus($appointmentIds[1], 'In Progress', $staffId);
        queueExpect(!$parallelStart['success'] && str_contains($parallelStart['message'], 'current patient'), 'A second treatment cannot start while one patient is in progress.');
        queueExpect($appointments->updateAppointmentStatus($appointmentIds[2], 'Completed', $staffId)['success'], 'Completing treatment releases the queue for the next patient.');
    }
} finally {
    foreach (array_reverse($appointmentIds) as $appointmentId) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type = 'appointment' AND entity_id = :id")->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointment_checkins WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointment_services WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointments WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
    }
    foreach (array_reverse($patientIds) as $patientId) {
        $conn->prepare('DELETE FROM patients WHERE patient_id = :id')->execute([':id' => $patientId]);
    }
    if ($createdScheduleId !== null) {
        $conn->prepare('DELETE FROM schedules WHERE schedule_id = :id')->execute([':id' => $createdScheduleId]);
    }
}

echo "Logbook queue test completed.\n";
