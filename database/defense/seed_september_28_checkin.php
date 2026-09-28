<?php
declare(strict_types=1);

// One-time fictional appointments for the September 28 client demonstration.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../../config/conn.php';

$date = '2026-09-28';
$receiptSource = __DIR__ . '/demo-gcash-receipt-150.png';
$receiptRelative = 'storage/payment_receipts/defense-2026-09-28-demo-150.png';
$receiptTarget = __DIR__ . '/../../' . $receiptRelative;
$appointments = [
    [
        'email' => 'anamendoza.av@gmail.com', 'clinic_id' => 1, 'service_id' => 1,
        'booked' => '2026-09-18 10:00:00', 'accepted' => '2026-09-18 11:00:00',
        'paid' => '2026-09-18 18:05:00', 'submitted' => '2026-09-18 18:10:00',
        'verified' => '2026-09-19 09:00:00', 'reference' => '9384210766513092',
        'receipt' => true,
    ],
    [
        'email' => 'miguelramos.av@gmail.com', 'clinic_id' => 1, 'service_id' => 4,
        'booked' => '2026-09-19 09:00:00', 'accepted' => '2026-09-19 10:00:00',
        'paid' => '2026-09-19 10:50:00', 'submitted' => '2026-09-19 11:00:00',
        'verified' => '2026-09-19 12:00:00', 'reference' => '9900202609191002',
        'receipt' => false,
    ],
    [
        'email' => 'leahnavarro.av@gmail.com', 'clinic_id' => 2, 'service_id' => 9,
        'booked' => '2026-09-20 09:00:00', 'accepted' => '2026-09-20 10:00:00',
        'paid' => '2026-09-20 10:50:00', 'submitted' => '2026-09-20 11:00:00',
        'verified' => '2026-09-20 12:00:00', 'reference' => '9900202609201003',
        'receipt' => false,
    ],
    [
        'email' => 'noelsantos.av@gmail.com', 'clinic_id' => 2, 'service_id' => 3,
        'booked' => '2026-09-21 09:00:00', 'accepted' => '2026-09-21 10:00:00',
        'paid' => '2026-09-21 10:50:00', 'submitted' => '2026-09-21 11:00:00',
        'verified' => '2026-09-21 12:00:00', 'reference' => '9900202609211004',
        'receipt' => false,
    ],
];

function nextDemoCode(PDO $conn): string
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
$copiedReceipt = false;
try {
    if ($conn->query('SELECT DATABASE()')->fetchColumn() !== 'db-oaprms-system'
        || (float) $conn->query('SELECT deposit_amount FROM site_settings WHERE id = 1')->fetchColumn() !== 150.0) {
        throw new RuntimeException('Unexpected database or deposit amount.');
    }
    $check = $conn->prepare('SELECT COUNT(*) FROM appointments WHERE date = ?');
    $check->execute([$date]);
    if ((int) $check->fetchColumn() !== 0) {
        throw new RuntimeException('September 28 appointments already exist.');
    }
    $check = $conn->prepare('SELECT COUNT(*) FROM schedules WHERE sched_date = ?');
    $check->execute([$date]);
    if ((int) $check->fetchColumn() !== 0) {
        throw new RuntimeException('September 28 schedules already exist.');
    }
    if (is_file($receiptTarget)) {
        throw new RuntimeException('Demo receipt target already exists.');
    }
    if (!is_file($receiptSource) || mime_content_type($receiptSource) !== 'image/png') {
        throw new RuntimeException('Supplied demo receipt image is unavailable or invalid.');
    }
    $referenceCheck = $conn->prepare('SELECT COUNT(*) FROM appointment_deposits WHERE gcash_reference = ?');
    $patientCheck = $conn->prepare("SELECT p.patient_id, p.profile_status, p.profile_completed_at, u.email_verified_at
        FROM patients p JOIN users u ON u.id = p.user_id WHERE p.email = ? AND u.user_role = 'Patient'");
    $serviceCheck = $conn->prepare('SELECT billing_unit FROM services WHERE service_id = ? AND is_active = 1');
    $patients = [];
    $serviceUnits = [];
    foreach ($appointments as $visit) {
        $referenceCheck->execute([$visit['reference']]);
        if ((int) $referenceCheck->fetchColumn() !== 0) {
            throw new RuntimeException('A demo payment reference already exists.');
        }
        $patientCheck->execute([$visit['email']]);
        $patient = $patientCheck->fetch(PDO::FETCH_ASSOC);
        if (!$patient || $patient['profile_status'] !== 'Complete'
            || !$patient['profile_completed_at'] || !$patient['email_verified_at']) {
            throw new RuntimeException('A selected patient is missing a complete verified profile.');
        }
        $patients[$visit['email']] = (int) $patient['patient_id'];
        $serviceCheck->execute([$visit['service_id']]);
        $unit = $serviceCheck->fetchColumn();
        if ($unit === false) {
            throw new RuntimeException('A selected service is unavailable.');
        }
        $serviceUnits[$visit['service_id']] = $unit;
        $booked = new DateTimeImmutable($visit['booked'], new DateTimeZone('Asia/Manila'));
        if ($booked->format('Y-m-d') > '2026-09-21'
            || !($booked < new DateTimeImmutable($visit['accepted'])
                && $visit['accepted'] < $visit['paid']
                && $visit['paid'] <= $visit['submitted']
                && $visit['submitted'] < $visit['verified'])) {
            throw new RuntimeException('A demo booking/payment timeline is invalid.');
        }
    }
    $adminId = (int) $conn->query("SELECT id FROM users WHERE email = 'av.admin@gmail.com' AND user_role = 'Admin'")->fetchColumn();
    if ($adminId <= 0) {
        throw new RuntimeException('Aprille Ventura Admin account was not found.');
    }
    if (($argv[1] ?? '') === '--check') {
        echo json_encode(['preflight' => 'passed', 'date' => $date,
            'confirmed_checkin_ready' => count($appointments), 'clinics' => 2,
            'receipt_reference' => '9384210766513092'], JSON_PRETTY_PRINT), PHP_EOL;
        exit(0);
    }

    if (!copy($receiptSource, $receiptTarget)) {
        throw new RuntimeException('Could not copy the supplied demo receipt.');
    }
    $copiedReceipt = true;
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
        $code = nextDemoCode($conn);
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
        $stmt->execute([$appointmentId, $visit['service_id'], $serviceUnits[$visit['service_id']]]);

        $stmt = $conn->prepare("INSERT INTO appointment_deposits
            (appointment_id, amount, receipt_amount, gcash_reference, gcash_transaction_at,
             receipt_path, receipt_mime, status, submitted_at, verified_by_user_id, verified_at,
             created_at, updated_at)
             VALUES (?, 150.00, 150.00, ?, ?, ?, ?, 'Verified', ?, ?, ?, ?, ?)");
        $stmt->execute([$appointmentId, $visit['reference'], $visit['paid'],
            $visit['receipt'] ? $receiptRelative : null, $visit['receipt'] ? 'image/png' : null,
            $visit['submitted'], $adminId, $visit['verified'], $visit['accepted'], $visit['verified']]);

        $stmt = $conn->prepare("INSERT INTO audit_logs
            (entity_type, entity_id, action, description, old_values, new_values,
             performed_by_user_id, performed_by_name, performed_by_role, source, performed_at)
             VALUES ('appointment', ?, 'status_changed', ?, ?, ?, ?, 'Aprille Ventura', 'Admin', 'User', ?)");
        $stmt->execute([$appointmentId, 'Fictional September 28 booking confirmed for demonstration.',
            json_encode(['status' => 'Payment Under Review'], JSON_THROW_ON_ERROR),
            json_encode(['status' => 'Confirmed', 'deposit_status' => 'Verified', 'appointment_code' => $code], JSON_THROW_ON_ERROR),
            $adminId, $visit['verified']]);
        $result[] = ['patient_email' => $visit['email'], 'clinic_id' => $visit['clinic_id'],
            'appointment_id' => $appointmentId, 'appointment_code' => $code,
            'deposit_reference' => $visit['reference'], 'has_receipt' => $visit['receipt']];
    }
    $conn->commit();
    echo json_encode(['date' => $date, 'status' => 'Confirmed', 'checkins_created' => 0,
        'appointments' => $result], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($copiedReceipt && is_file($receiptTarget)) {
        unlink($receiptTarget);
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
