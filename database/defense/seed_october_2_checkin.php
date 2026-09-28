<?php
declare(strict_types=1);

// One-time fictional bookings for the October 2 final-defense check-in demo.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../../config/conn.php';

$date = '2026-10-02';
$appointments = [
    ['email' => 'biancareyes.av@gmail.com', 'clinic_id' => 1, 'service_id' => 2,
        'booked' => '2026-09-23 09:00:00', 'accepted' => '2026-09-23 10:00:00',
        'paid' => '2026-09-23 11:00:00', 'submitted' => '2026-09-23 11:05:00',
        'verified' => '2026-09-23 12:00:00', 'reference' => '9900202609232001'],
    ['email' => 'carlobautista.av@gmail.com', 'clinic_id' => 1, 'service_id' => 1,
        'booked' => '2026-09-24 09:00:00', 'accepted' => '2026-09-24 10:00:00',
        'paid' => '2026-09-24 11:00:00', 'submitted' => '2026-09-24 11:05:00',
        'verified' => '2026-09-24 12:00:00', 'reference' => '9900202609242002'],
    ['email' => 'ellarivera.av@gmail.com', 'clinic_id' => 2, 'service_id' => 4,
        'booked' => '2026-09-25 08:30:00', 'accepted' => '2026-09-25 09:30:00',
        'paid' => '2026-09-25 10:15:00', 'submitted' => '2026-09-25 10:20:00',
        'verified' => '2026-09-25 11:00:00', 'reference' => '9900202609252003'],
    ['email' => 'isabellopez.av@gmail.com', 'clinic_id' => 2, 'service_id' => 9,
        'booked' => '2026-09-25 13:00:00', 'accepted' => '2026-09-25 14:00:00',
        'paid' => '2026-09-25 15:00:00', 'submitted' => '2026-09-25 15:05:00',
        'verified' => '2026-09-25 16:00:00', 'reference' => '9900202609252004'],
];

function octoberDemoCode(PDO $conn): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $suffix = '';
        for ($i = 0; $i < 6; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $code = 'AVC-' . $suffix;
        $stmt = $conn->prepare('SELECT 1 FROM appointments WHERE appointment_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());
    return $code;
}

$conn = (new Database())->connect();
if (!$conn) {
    fwrite(STDERR, "Application database is unavailable.\n");
    exit(1);
}

try {
    $settings = $conn->query('SELECT deposit_amount, minimum_booking_lead_days, clinic_transition_minutes FROM site_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if ($conn->query('SELECT DATABASE()')->fetchColumn() !== 'db-oaprms-system'
        || !$settings || (float) $settings['deposit_amount'] !== 150.0
        || (int) $settings['minimum_booking_lead_days'] !== 7
        || (int) $settings['clinic_transition_minutes'] > 120) {
        throw new RuntimeException('Unexpected database or booking settings.');
    }
    foreach (['appointments' => 'date', 'schedules' => 'sched_date'] as $table => $column) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
        $stmt->execute([$date]);
        if ((int) $stmt->fetchColumn() !== 0) {
            throw new RuntimeException("October 2 $table already exist.");
        }
    }
    $patientStmt = $conn->prepare("SELECT p.patient_id, p.profile_status, p.profile_completed_at, u.email_verified_at
        FROM patients p JOIN users u ON u.id = p.user_id WHERE p.email = ? AND u.user_role = 'Patient'");
    $serviceStmt = $conn->prepare('SELECT billing_unit FROM services WHERE service_id = ? AND is_active = 1');
    $referenceStmt = $conn->prepare('SELECT COUNT(*) FROM appointment_deposits WHERE gcash_reference = ?');
    $patients = [];
    $units = [];
    foreach ($appointments as $visit) {
        $patientStmt->execute([$visit['email']]);
        $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
        if (!$patient || $patient['profile_status'] !== 'Complete'
            || !$patient['profile_completed_at'] || !$patient['email_verified_at']) {
            throw new RuntimeException('A selected patient lacks a complete verified profile.');
        }
        $patients[$visit['email']] = (int) $patient['patient_id'];
        $serviceStmt->execute([$visit['service_id']]);
        $unit = $serviceStmt->fetchColumn();
        if ($unit === false) {
            throw new RuntimeException('A selected service is unavailable.');
        }
        $units[$visit['service_id']] = $unit;
        $referenceStmt->execute([$visit['reference']]);
        if ((int) $referenceStmt->fetchColumn() !== 0) {
            throw new RuntimeException('A selected deposit reference already exists.');
        }
        if (substr($visit['booked'], 0, 10) > '2026-09-25'
            || !($visit['booked'] < $visit['accepted']
                && $visit['accepted'] < $visit['paid']
                && $visit['paid'] <= $visit['submitted']
                && $visit['submitted'] < $visit['verified'])) {
            throw new RuntimeException('A booking or deposit timeline is invalid.');
        }
    }
    $adminId = (int) $conn->query("SELECT id FROM users WHERE email = 'av.admin@gmail.com' AND user_role = 'Admin'")->fetchColumn();
    if ($adminId <= 0) {
        throw new RuntimeException('The preserved Admin account is missing.');
    }
    if (($argv[1] ?? '') === '--check') {
        echo json_encode(['preflight' => 'passed', 'date' => $date,
            'confirmed_checkin_ready' => count($appointments), 'clinics' => 2], JSON_PRETTY_PRINT), PHP_EOL;
        exit(0);
    }

    $conn->beginTransaction();
    $scheduleIds = [];
    foreach ([1 => ['08:00:00', '12:00:00'], 2 => ['14:00:00', '17:00:00']] as $clinicId => [$start, $end]) {
        $stmt = $conn->prepare('INSERT INTO schedules (clinic_id, sched_date, start_time, end_time, max_appointments) VALUES (?, ?, ?, ?, 4)');
        $stmt->execute([$clinicId, $date, $start, $end]);
        $scheduleIds[$clinicId] = (int) $conn->lastInsertId();
    }
    $result = [];
    foreach ($appointments as $visit) {
        $deadline = (new DateTimeImmutable($visit['accepted']))->modify('+8 hours')->format('Y-m-d H:i:s');
        $code = octoberDemoCode($conn);
        $stmt = $conn->prepare("INSERT INTO appointments
            (patient_id, schedule_id, clinic_id, date, status, deposit_required, payment_deadline_at,
             reviewed_by_user_id, reviewed_at, accepted_for_payment_at, appointment_code,
             code_generated_at, confirmed_at, created_at)
             VALUES (?, ?, ?, ?, 'Confirmed', 1, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$patients[$visit['email']], $scheduleIds[$visit['clinic_id']], $visit['clinic_id'],
            $date, $deadline, $adminId, $visit['accepted'], $visit['accepted'], $code,
            $visit['verified'], $visit['verified'], $visit['booked']]);
        $appointmentId = (int) $conn->lastInsertId();

        $stmt = $conn->prepare('INSERT INTO appointment_services
            (appointment_id, service_id, quantity, billing_unit_snapshot) VALUES (?, ?, 1.00, ?)');
        $stmt->execute([$appointmentId, $visit['service_id'], $units[$visit['service_id']]]);

        $stmt = $conn->prepare("INSERT INTO appointment_deposits
            (appointment_id, amount, receipt_amount, gcash_reference, gcash_transaction_at,
             status, submitted_at, verified_by_user_id, verified_at, created_at, updated_at)
             VALUES (?, 150.00, 150.00, ?, ?, 'Verified', ?, ?, ?, ?, ?)");
        $stmt->execute([$appointmentId, $visit['reference'], $visit['paid'], $visit['submitted'],
            $adminId, $visit['verified'], $visit['accepted'], $visit['verified']]);

        $stmt = $conn->prepare("INSERT INTO audit_logs
            (entity_type, entity_id, action, description, old_values, new_values,
             performed_by_user_id, performed_by_name, performed_by_role, source, performed_at)
             VALUES ('appointment', ?, 'status_changed', ?, ?, ?, ?, 'Aprille Ventura', 'Admin', 'User', ?)");
        $stmt->execute([$appointmentId, 'Fictional October 2 booking confirmed for demonstration.',
            json_encode(['status' => 'Payment Under Review'], JSON_THROW_ON_ERROR),
            json_encode(['status' => 'Confirmed', 'deposit_status' => 'Verified', 'appointment_code' => $code], JSON_THROW_ON_ERROR),
            $adminId, $visit['verified']]);
        $result[] = ['patient_email' => $visit['email'], 'clinic_id' => $visit['clinic_id'],
            'appointment_id' => $appointmentId, 'appointment_code' => $code,
            'deposit_reference' => $visit['reference']];
    }
    $conn->commit();
    echo json_encode(['date' => $date, 'status' => 'Confirmed', 'checkins_created' => 0,
        'appointments' => $result], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
