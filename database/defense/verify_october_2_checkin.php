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
            a.created_at, a.confirmed_at, a.deposit_required,
            p.firstname, p.lastname, p.email, p.profile_status, p.profile_completed_at,
            s.clinic_id, s.sched_date, s.start_time, s.end_time, s.max_appointments,
            d.amount, d.receipt_amount, d.status AS deposit_status,
            d.gcash_reference, d.receipt_path, c.checkin_id
        FROM appointments a
        JOIN patients p ON p.patient_id = a.patient_id
        JOIN schedules s ON s.schedule_id = a.schedule_id
        LEFT JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
        LEFT JOIN appointment_checkins c ON c.appointment_id = a.appointment_id
        WHERE a.date = '2026-10-02'
        ORDER BY a.appointment_id")->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 4) {
        throw new RuntimeException('Expected four October 2 appointments.');
    }
    $references = [];
    $codes = [];
    $clinics = [];
    foreach ($rows as $row) {
        if ($row['date'] !== $row['sched_date'] || $row['status'] !== 'Confirmed'
            || $row['deposit_status'] !== 'Verified' || (int) $row['deposit_required'] !== 1
            || $row['checkin_id'] !== null || $row['profile_status'] !== 'Complete'
            || !$row['profile_completed_at'] || !$row['confirmed_at']
            || (float) $row['amount'] !== 150.0 || (float) $row['receipt_amount'] !== 150.0
            || substr($row['created_at'], 0, 10) > '2026-09-25'
            || !preg_match('/^AVC-[A-Z2-9]{6}$/', $row['appointment_code'])
            || $row['receipt_path'] !== null) {
            throw new RuntimeException('An October 2 appointment is not ready for check-in.');
        }
        $references[] = $row['gcash_reference'];
        $codes[] = $row['appointment_code'];
        $clinics[$row['clinic_id']] = true;
    }
    if (count(array_unique($references)) !== 4 || count(array_unique($codes)) !== 4
        || count($clinics) !== 2) {
        throw new RuntimeException('October 2 references, codes, or clinic coverage are invalid.');
    }
    $allDuplicateCodes = (int) $conn->query('SELECT COUNT(*) - COUNT(DISTINCT appointment_code) FROM appointments WHERE appointment_code IS NOT NULL')->fetchColumn();
    $allDuplicateReferences = (int) $conn->query('SELECT COUNT(*) - COUNT(DISTINCT gcash_reference) FROM appointment_deposits WHERE gcash_reference IS NOT NULL')->fetchColumn();
    if ($allDuplicateCodes !== 0 || $allDuplicateReferences !== 0) {
        throw new RuntimeException('A code or payment reference conflicts with other demo data.');
    }
    $logbookRows = (new LogbookModel($conn))->getForDate('2026-10-02');
    if (count($logbookRows) !== 4) {
        throw new RuntimeException('October 2 bookings are not all visible in the staff logbook.');
    }
    echo json_encode(['verified' => true, 'date' => '2026-10-02',
        'confirmed_ready_for_checkin' => 4, 'logbook_entries' => count($logbookRows),
        'clinics' => count($clinics),
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
