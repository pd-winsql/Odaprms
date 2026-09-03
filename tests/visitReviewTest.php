<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';
require_once __DIR__ . '/../apps/models/reviewModel.php';

function reviewExpect($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
reviewExpect($conn instanceof PDO, 'Review test connected to the application database.');

$reviews = new ReviewModel($conn);
$appointments = new Appointment($conn);
$createdAppointmentIds = [];
$patientId = 0;
$userId = 0;

try {
    $email = 'visit-review-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $createUser = $conn->prepare("
        INSERT INTO users (email, password, email_verified_at, user_role)
        VALUES (:email, :password, NOW(), 'Patient')
    ");
    $createUser->execute([
        ':email' => $email,
        ':password' => password_hash('ReviewTest123', PASSWORD_DEFAULT),
    ]);
    $userId = (int) $conn->lastInsertId();

    $createPatient = $conn->prepare("
        INSERT INTO patients (user_id, firstname, lastname, email, profile_status)
        VALUES (:user_id, 'Review', 'Patient', :email, 'Complete')
    ");
    $createPatient->execute([':user_id' => $userId, ':email' => $email]);
    $patientId = (int) $conn->lastInsertId();

    $schedule = $conn->query("
        SELECT schedule_id, clinic_id
        FROM schedules
        ORDER BY schedule_id
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    reviewExpect((bool) $schedule, 'A schedule fixture is available.');

    $createAppointment = $conn->prepare("
        INSERT INTO appointments (patient_id, schedule_id, clinic_id, date, status, completed_at)
        VALUES (:patient_id, :schedule_id, :clinic_id, CURDATE(), :status,
            CASE WHEN :completed_status = 'Completed' THEN NOW() ELSE NULL END)
    ");
    foreach (['Completed' => 'Completed', 'Confirmed' => 'Confirmed', 'RatingOnly' => 'Completed'] as $fixture => $status) {
        $createAppointment->execute([
            ':patient_id' => $patientId,
            ':schedule_id' => (int) $schedule['schedule_id'],
            ':clinic_id' => (int) $schedule['clinic_id'],
            ':status' => $status,
            ':completed_status' => $status,
        ]);
        $createdAppointmentIds[$fixture] = (int) $conn->lastInsertId();
    }

    reviewExpect(
        !$reviews->submitForPatientUser($createdAppointmentIds['Completed'], $userId, 0)['success'],
        'Ratings outside the 1-to-5 range are rejected.'
    );
    reviewExpect(
        !$reviews->submitForPatientUser($createdAppointmentIds['Completed'], $userId + 999999, 5)['success'],
        'A patient cannot rate an appointment they do not own.'
    );
    reviewExpect(
        !$reviews->submitForPatientUser($createdAppointmentIds['Confirmed'], $userId, 5)['success'],
        'Only completed visits can be rated.'
    );
    reviewExpect(
        !$reviews->submitForPatientUser($createdAppointmentIds['Completed'], $userId, 5, str_repeat('x', 1001))['success'],
        'Written feedback longer than 1,000 characters is rejected.'
    );

    $submitted = $reviews->submitForPatientUser($createdAppointmentIds['Completed'], $userId, 5, '  Excellent and reassuring visit.  ');
    reviewExpect($submitted['success'], 'A patient can rate their own completed visit.');
    reviewExpect(
        ($submitted['review']['feedback'] ?? '') === 'Excellent and reassuring visit.',
        'Written feedback is normalized before storage.'
    );
    reviewExpect(
        !$reviews->submitForPatientUser($createdAppointmentIds['Completed'], $userId, 4, 'Second review')['success'],
        'A second review for the same appointment is rejected.'
    );
    $ratingOnly = $reviews->submitForPatientUser($createdAppointmentIds['RatingOnly'], $userId, 4, '   ');
    reviewExpect($ratingOnly['success'], 'Written feedback is optional for a valid star rating.');
    $ratingOnlyFeedback = $conn->query(
        'SELECT feedback FROM appointment_reviews WHERE appointment_id = ' . (int) $createdAppointmentIds['RatingOnly']
    )->fetchColumn();
    reviewExpect($ratingOnlyFeedback === null, 'Blank optional feedback is stored as null.');

    $stored = $reviews->getForAppointments([$createdAppointmentIds['Completed']]);
    reviewExpect(
        (int) ($stored[$createdAppointmentIds['Completed']]['rating'] ?? 0) === 5,
        'Submitted feedback is available to the patient history view.'
    );

    $past = $appointments->getPatientPastAppointments($patientId);
    $pastIds = array_map('intval', array_column($past, 'appointment_id'));
    reviewExpect(
        in_array($createdAppointmentIds['Completed'], $pastIds, true),
        'A visit completed today appears in patient history immediately.'
    );
    reviewExpect(
        !in_array($createdAppointmentIds['Confirmed'], $pastIds, true),
        'A confirmed appointment scheduled today remains outside patient history.'
    );

    $adminRows = $reviews->getAdminReviews();
    reviewExpect(
        count(array_filter($adminRows, fn(array $row): bool => (int) $row['appointment_id'] === $createdAppointmentIds['Completed'])) === 1,
        'The submitted visit rating appears in the Admin feedback list.'
    );
    $summary = $reviews->getAdminSummary();
    reviewExpect($summary['total_reviews'] >= 1, 'Admin feedback summary includes submitted ratings.');
} finally {
    foreach (array_reverse(array_values($createdAppointmentIds)) as $appointmentId) {
        $conn->prepare('DELETE FROM appointments WHERE appointment_id = :appointment_id')
            ->execute([':appointment_id' => $appointmentId]);
    }
    if ($patientId) {
        $conn->prepare('DELETE FROM patients WHERE patient_id = :patient_id')
            ->execute([':patient_id' => $patientId]);
    }
    if ($userId) {
        $conn->prepare('DELETE FROM users WHERE id = :user_id')
            ->execute([':user_id' => $userId]);
    }
}

echo "Visit review test completed.\n";
