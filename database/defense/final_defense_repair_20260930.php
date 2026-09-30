<?php
declare(strict_types=1);

// One-time, guarded repair of fictional data after clock-forward workflow tests.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../../config/conn.php';

function defenseInsert(PDO $db, string $sql, array $values): int
{
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    return (int) $db->lastInsertId();
}

function defenseCode(PDO $db): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $suffix = '';
        for ($i = 0; $i < 6; $i++) $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $code = 'AVC-' . $suffix;
        $stmt = $db->prepare('SELECT 1 FROM appointments WHERE appointment_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());
    return $code;
}

$db = (new Database())->connect();
if (!$db) {
    fwrite(STDERR, "Application database is unavailable.\n");
    exit(1);
}

try {
    $today = date('Y-m-d');
    $settings = $db->query('SELECT deposit_amount, minimum_booking_lead_days, clinic_transition_minutes FROM site_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'db-oaprms-system'
        || $today !== '2026-09-30' || !$settings
        || (float) $settings['deposit_amount'] !== 150.0
        || (int) $settings['minimum_booking_lead_days'] !== 7
        || (int) $settings['clinic_transition_minutes'] !== 90) {
        throw new RuntimeException('Database, date, or site settings do not match this repair plan.');
    }
    $adminId = (int) $db->query("SELECT u.id FROM users u JOIN staffs s ON s.user_id = u.id
        WHERE u.id = 7 AND u.user_role = 'Admin' AND s.firstname = 'Aprille' AND s.lastname = 'Ventura'")->fetchColumn();
    if ($adminId <= 0) throw new RuntimeException('Aprille Ventura Admin account is missing.');

    $oldOct2 = $db->query("SELECT a.appointment_id, a.status, d.status AS deposit_status
        FROM appointments a JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
        WHERE a.date = '2026-10-02' ORDER BY a.appointment_id")->fetchAll(PDO::FETCH_ASSOC);
    $expected = [981 => ['No-show', 'Forfeited'], 982 => ['No-show', 'Forfeited'],
        983 => ['No-show', 'Forfeited'], 984 => ['Cancelled', 'Refunded']];
    if (count($oldOct2) !== 4) throw new RuntimeException('October 2 appointment count changed.');
    foreach ($oldOct2 as $row) {
        $id = (int) $row['appointment_id'];
        if (!isset($expected[$id]) || $expected[$id] !== [$row['status'], $row['deposit_status']]) {
            throw new RuntimeException('An October 2 appointment changed since inspection.');
        }
    }
    $testRows = $db->query("SELECT appointment_id, date, status, schedule_id FROM appointments
        WHERE date IN ('2026-10-06', '2026-10-07') ORDER BY appointment_id")->fetchAll(PDO::FETCH_ASSOC);
    if (array_map(static fn(array $r): int => (int) $r['appointment_id'], $testRows) !== [985, 986, 987, 988, 989, 990, 991]) {
        throw new RuntimeException('Future test appointment set changed since inspection.');
    }
    foreach ($testRows as $row) {
        $id = (int) $row['appointment_id'];
        if ((int) $row['schedule_id'] !== ($id <= 987 ? 397 : 398)
            || $row['status'] !== ($id <= 987 ? 'Cancelled' : 'Completed')) {
            throw new RuntimeException('Future test appointment status or schedule changed.');
        }
    }
    if ((int) $db->query("SELECT COUNT(*) FROM appointment_reschedule_requests WHERE target_schedule_id IN (397,398) OR original_schedule_id IN (397,398)")->fetchColumn() !== 0
        || (int) $db->query("SELECT COUNT(*) FROM schedules WHERE sched_date IN ('2026-10-09','2026-10-10')")->fetchColumn() !== 0) {
        throw new RuntimeException('A schedule dependency or planned defense schedule already exists.');
    }
    $win = $db->query('SELECT patient_id, user_id, email FROM patients WHERE patient_id = 525')->fetch(PDO::FETCH_ASSOC);
    if (!$win || (int) $win['user_id'] !== 187 || $win['email'] !== 'winsight11@gmail.com') {
        throw new RuntimeException('Future test patient identity changed.');
    }
    $testCharts = $db->query('SELECT odontogram_id, patient_id, created_at FROM patient_odontograms
        WHERE odontogram_id IN (1,5,6) ORDER BY odontogram_id')->fetchAll(PDO::FETCH_ASSOC);
    if (count($testCharts) !== 3
        || array_map(static fn(array $r): int => (int) $r['patient_id'], $testCharts) !== [525,513,520]
        || count(array_filter($testCharts, static fn(array $r): bool => $r['created_at'] <= '2026-09-30 23:59:59')) !== 0) {
        throw new RuntimeException('Future test dental charts changed since inspection.');
    }

    $people = [
        'ninasoriano.av@gmail.com' => 1,
        'marcoaquino.av@gmail.com' => 2,
        'miravaldez.av@gmail.com' => 3,
        'tessamercado.av@gmail.com' => 9,
        'juliantorres.av@gmail.com' => 4,
    ];
    $patientStmt = $db->prepare("SELECT p.patient_id, p.profile_status, p.profile_completed_at,
            u.email_verified_at, s.billing_unit
        FROM patients p JOIN users u ON u.id = p.user_id
        JOIN services s ON s.service_id = ? AND s.is_active = 1
        WHERE p.email = ? AND u.user_role = 'Patient'");
    $patients = [];
    foreach ($people as $email => $serviceId) {
        $patientStmt->execute([$serviceId, $email]);
        $row = $patientStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['profile_status'] !== 'Complete'
            || !$row['profile_completed_at'] || !$row['email_verified_at']) {
            throw new RuntimeException("Patient/service preflight failed for $email.");
        }
        $patients[$email] = ['id' => (int) $row['patient_id'], 'service' => $serviceId, 'unit' => $row['billing_unit']];
    }
    foreach (['9900202609253001', '9900202609303002', '9900202609303003'] as $reference) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM appointment_deposits WHERE gcash_reference = ?');
        $stmt->execute([$reference]);
        if ((int) $stmt->fetchColumn() !== 0) throw new RuntimeException('A planned payment reference already exists.');
    }
    if (($argv[1] ?? '') === '--check') {
        echo json_encode(['preflight' => 'passed', 'future_test_appointments_to_remove' => 7,
            'october_2_bookings_to_restore' => 3, 'new_october_2_scenarios' => 4,
            'new_october_9_booking' => 1, 'new_schedules' => 4], JSON_PRETTY_PRINT), PHP_EOL;
        exit(0);
    }

    $db->beginTransaction();
    // Child rows that restrict appointment deletion. Other direct children cascade.
    foreach (['appointment_billings', 'appointment_checkins', 'appointment_deposits'] as $table) {
        $db->exec("DELETE FROM `$table` WHERE appointment_id BETWEEN 985 AND 991");
    }
    $db->exec("DELETE FROM audit_logs WHERE entity_type = 'appointment' AND entity_id BETWEEN 985 AND 991");
    $db->exec("DELETE FROM appointments WHERE appointment_id BETWEEN 985 AND 991");
    $db->exec('DELETE FROM schedules WHERE schedule_id IN (397,398)');
    // These charts were created only during the future-date treatment tests.
    $db->exec('DELETE FROM patient_odontograms WHERE odontogram_id IN (1,5,6)');
    $db->exec("DELETE FROM audit_logs WHERE entity_type = 'patient' AND entity_id = 525");
    $db->exec("DELETE FROM audit_logs WHERE entity_type = 'patient' AND entity_id IN (513,520) AND action LIKE 'odontogram_%' AND performed_at > NOW()");
    $db->exec("DELETE FROM audit_logs WHERE entity_type = 'schedule' AND entity_id IN (397,398)");
    $db->exec('DELETE FROM patients WHERE patient_id = 525');
    $db->exec('DELETE FROM users WHERE id = 187');

    $db->exec("UPDATE appointments SET status = 'Confirmed' WHERE appointment_id IN (981,982,983) AND date = '2026-10-02' AND status = 'No-show'");
    $db->exec("UPDATE appointment_deposits SET status = 'Verified', refund_reason = NULL, updated_at = NOW()
        WHERE appointment_id IN (981,982,983) AND status = 'Forfeited'");
    $audit = $db->prepare("INSERT INTO audit_logs
        (entity_type, entity_id, action, description, old_values, new_values,
         performed_by_user_id, performed_by_name, performed_by_role, source, performed_at)
        VALUES ('appointment', ?, 'status_changed', ?, ?, ?, ?, 'Defense preparation', 'Admin', 'User', NOW())");
    foreach ([981,982,983] as $id) {
        $audit->execute([$id, 'Restored a fictional October 2 booking after clock-forward testing.',
            json_encode(['status' => 'No-show', 'deposit_status' => 'Forfeited'], JSON_THROW_ON_ERROR),
            json_encode(['status' => 'Confirmed', 'deposit_status' => 'Verified'], JSON_THROW_ON_ERROR), $adminId]);
    }

    $scheduleIds = [
        '2026-10-02' => [1 => 395, 2 => 396],
    ];
    foreach (['2026-10-09', '2026-10-10'] as $scheduleDate) {
        foreach ([1 => ['08:00:00', '12:00:00'], 2 => ['14:00:00', '17:00:00']] as $clinicId => [$start, $end]) {
            $scheduleIds[$scheduleDate][$clinicId] = defenseInsert($db,
                'INSERT INTO schedules (clinic_id, sched_date, start_time, end_time, max_appointments) VALUES (?, ?, ?, ?, 8)',
                [$clinicId, $scheduleDate, $start, $end]);
        }
    }

    $scenarios = [
        ['email' => 'ninasoriano.av@gmail.com', 'date' => '2026-10-02', 'clinic' => 2,
            'status' => 'Confirmed', 'booked' => '2026-09-25 09:00:00', 'accepted' => '2026-09-25 10:00:00',
            'paid' => '2026-09-25 11:00:00', 'submitted' => '2026-09-25 11:05:00',
            'verified' => '2026-09-25 12:00:00', 'reference' => '9900202609253001'],
        ['email' => 'marcoaquino.av@gmail.com', 'date' => '2026-10-02', 'clinic' => 1,
            'status' => 'Pending Review', 'booked' => '2026-09-25 09:30:00'],
        ['email' => 'miravaldez.av@gmail.com', 'date' => '2026-10-02', 'clinic' => 1,
            'status' => 'Awaiting Deposit', 'booked' => '2026-09-25 10:00:00',
            'accepted' => '2026-09-30 20:00:00', 'deadline' => '2026-10-02 20:00:00'],
        ['email' => 'tessamercado.av@gmail.com', 'date' => '2026-10-02', 'clinic' => 2,
            'status' => 'Payment Under Review', 'booked' => '2026-09-25 10:30:00',
            'accepted' => '2026-09-30 20:00:00', 'deadline' => '2026-10-01 04:00:00',
            'paid' => '2026-09-30 20:25:00', 'submitted' => '2026-09-30 20:30:00',
            'reference' => '9900202609303002'],
        ['email' => 'juliantorres.av@gmail.com', 'date' => '2026-10-09', 'clinic' => 1,
            'status' => 'Confirmed', 'booked' => '2026-09-30 08:00:00',
            'accepted' => '2026-09-30 09:00:00', 'paid' => '2026-09-30 10:00:00',
            'submitted' => '2026-09-30 10:05:00', 'verified' => '2026-09-30 11:00:00',
            'reference' => '9900202609303003'],
    ];
    $created = [];
    foreach ($scenarios as $scenario) {
        $patient = $patients[$scenario['email']];
        $code = $scenario['status'] === 'Confirmed' ? defenseCode($db) : null;
        $accepted = $scenario['accepted'] ?? null;
        $verified = $scenario['verified'] ?? null;
        $deadline = $scenario['deadline'] ?? ($accepted
            ? (new DateTimeImmutable($accepted))->modify('+8 hours')->format('Y-m-d H:i:s') : null);
        $appointmentId = defenseInsert($db, 'INSERT INTO appointments
            (patient_id, schedule_id, clinic_id, date, status, deposit_required,
             payment_deadline_at, reviewed_by_user_id, reviewed_at, accepted_for_payment_at,
             appointment_code, code_generated_at, confirmed_at, created_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$patient['id'], $scheduleIds[$scenario['date']][$scenario['clinic']],
                $scenario['clinic'], $scenario['date'], $scenario['status'], $deadline,
                $accepted ? $adminId : null, $accepted, $accepted, $code, $verified, $verified,
                $scenario['booked']]);
        $db->prepare('INSERT INTO appointment_services
            (appointment_id, service_id, quantity, billing_unit_snapshot) VALUES (?, ?, 1.00, ?)')
            ->execute([$appointmentId, $patient['service'], $patient['unit']]);

        if ($scenario['status'] !== 'Pending Review') {
            $depositStatus = match ($scenario['status']) {
                'Confirmed' => 'Verified',
                'Payment Under Review' => 'Under Review',
                default => 'Awaiting Submission',
            };
            $db->prepare('INSERT INTO appointment_deposits
                (appointment_id, amount, receipt_amount, gcash_reference, gcash_transaction_at,
                 status, submitted_at, verified_by_user_id, verified_at,
                 deadline_extended_by_user_id, deadline_extended_at, deadline_extension_reason,
                 created_at, updated_at)
                 VALUES (?, 150.00, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$appointmentId,
                    isset($scenario['paid']) ? 150.00 : null,
                    $scenario['reference'] ?? null, $scenario['paid'] ?? null,
                    $depositStatus, $scenario['submitted'] ?? null,
                    $verified ? $adminId : null, $verified,
                    $scenario['status'] === 'Awaiting Deposit' ? $adminId : null,
                    $scenario['status'] === 'Awaiting Deposit' ? '2026-09-30 20:10:00' : null,
                    $scenario['status'] === 'Awaiting Deposit' ? 'Extended for the October 2 defense demonstration.' : null,
                    $accepted, $verified ?? ($scenario['submitted'] ?? $accepted)]);
        }
        $audit->execute([$appointmentId, 'Created a fictional final-defense appointment scenario.',
            null, json_encode(['status' => $scenario['status'], 'appointment_code' => $code], JSON_THROW_ON_ERROR), $adminId]);
        $created[] = ['appointment_id' => $appointmentId, 'date' => $scenario['date'],
            'status' => $scenario['status'], 'patient_email' => $scenario['email'],
            'clinic_id' => $scenario['clinic'], 'appointment_code' => $code];
    }
    $db->commit();
    echo json_encode(['repaired' => true, 'restored_october_2_ids' => [981,982,983],
        'preserved_cancelled_october_2_id' => 984, 'removed_test_ids' => [985,986,987,988,989,990,991],
        'created' => $created], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
