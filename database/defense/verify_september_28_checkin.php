<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../../apps/models/logbookModel.php';

$conn = (new Database())->connect();
if (!$conn) {
    fwrite(STDERR, "Database unavailable.\n");
    exit(1);
}

try {
    $rows = $conn->query("SELECT a.appointment_id, a.appointment_code, a.date, a.status,
            a.created_at, a.confirmed_at, a.deposit_required, a.schedule_id,
            p.firstname, p.lastname, p.email, p.profile_status, p.profile_completed_at,
            s.clinic_id, s.start_time, s.end_time, s.max_appointments,
            d.amount, d.receipt_amount, d.status AS deposit_status,
            d.gcash_reference, d.receipt_path, d.receipt_mime,
            c.checkin_id
        FROM appointments a
        JOIN patients p ON p.patient_id = a.patient_id
        JOIN schedules s ON s.schedule_id = a.schedule_id
        LEFT JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
        LEFT JOIN appointment_checkins c ON c.appointment_id = a.appointment_id
        WHERE a.date = '2026-09-28'
        ORDER BY a.appointment_id")->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 4) {
        throw new RuntimeException('Expected four September 28 appointments.');
    }
    $references = [];
    $codes = [];
    $clinics = [];
    $receiptCount = 0;
    foreach ($rows as $row) {
        if ($row['status'] !== 'Confirmed' || $row['deposit_status'] !== 'Verified'
            || (int) $row['deposit_required'] !== 1 || $row['checkin_id'] !== null
            || $row['profile_status'] !== 'Complete' || !$row['profile_completed_at']
            || (float) $row['amount'] !== 150.0 || (float) $row['receipt_amount'] !== 150.0
            || substr($row['created_at'], 0, 10) > '2026-09-21'
            || !$row['confirmed_at'] || !preg_match('/^AVC-[A-Z2-9]{6}$/', $row['appointment_code'])) {
            throw new RuntimeException('A September 28 appointment is not ready for check-in.');
        }
        $references[] = $row['gcash_reference'];
        $codes[] = $row['appointment_code'];
        $clinics[$row['clinic_id']] = true;
        if ($row['receipt_path'] !== null) {
            $receiptCount++;
            $source = __DIR__ . '/demo-gcash-receipt-150.png';
            $target = __DIR__ . '/../../' . $row['receipt_path'];
            if ($row['gcash_reference'] !== '9384210766513092'
                || $row['receipt_mime'] !== 'image/png' || !is_file($target)
                || hash_file('sha256', $source) !== hash_file('sha256', $target)) {
                throw new RuntimeException('The supplied demo receipt does not match the deposit.');
            }
        }
    }
    if (count(array_unique($references)) !== 4 || count(array_unique($codes)) !== 4
        || count($clinics) !== 2 || $receiptCount !== 1) {
        throw new RuntimeException('September 28 clinic, code, or receipt coverage is invalid.');
    }
    $logbookRows = (new LogbookModel($conn))->getForDate('2026-09-28');
    if (count($logbookRows) !== 4) {
        throw new RuntimeException('The September 28 bookings do not appear in the staff logbook.');
    }
    echo json_encode(['verified' => true, 'date' => '2026-09-28',
        'confirmed_ready_for_checkin' => 4, 'logbook_entries' => count($logbookRows),
        'clinics' => count($clinics), 'demo_receipts' => $receiptCount,
        'appointments' => array_map(static fn(array $row): array => [
            'patient' => $row['firstname'] . ' ' . $row['lastname'],
            'email' => $row['email'],
            'clinic_id' => (int) $row['clinic_id'],
            'appointment_code' => $row['appointment_code'],
        ], $rows)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
