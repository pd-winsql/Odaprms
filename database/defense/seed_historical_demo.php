<?php
declare(strict_types=1);

// Fictional final-defense history only. Does not create September 28 or future visits.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../../config/conn.php';

function demoInsert(PDO $conn, string $sql, array $values): int
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($values);
    return (int) $conn->lastInsertId();
}

$timezone = new DateTimeZone('Asia/Manila');
$time = static fn(string $date, string $clock): DateTimeImmutable => new DateTimeImmutable("$date $clock", $timezone);
$stamp = static fn(DateTimeImmutable $date): string => $date->format('Y-m-d H:i:s');

$people = [
    ['Ana', 'Mendoza', '1995-02-14', 'Female', 'Teacher'],
    ['Miguel', 'Ramos', '1990-07-03', 'Male', 'Office worker'],
    ['Leah', 'Navarro', '1998-11-21', 'Female', 'Nurse'],
    ['Paolo', 'Castillo', '1988-05-09', 'Male', 'Driver'],
    ['Bianca', 'Reyes', '2001-01-28', 'Female', 'Student'],
    ['Carlo', 'Bautista', '1992-09-16', 'Male', 'Technician'],
    ['Nina', 'Soriano', '1997-04-30', 'Female', 'Teacher'],
    ['Daniel', 'Flores', '1985-12-07', 'Male', 'Business owner'],
    ['Grace', 'Villanueva', '1993-08-19', 'Female', 'Clerk'],
    ['Marco', 'Aquino', '1999-03-11', 'Male', 'Student'],
    ['Ella', 'Rivera', '1987-06-24', 'Female', 'Accountant'],
    ['Rafael', 'Santiago', '1994-10-02', 'Male', 'Farmer'],
    ['Tessa', 'Mercado', '2000-12-15', 'Female', 'Student'],
    ['Julian', 'Torres', '1991-07-29', 'Male', 'Engineer'],
    ['Camille', 'Garcia', '1996-05-18', 'Female', 'Designer'],
    ['Adrian', 'Cruz', '1989-02-06', 'Male', 'Office worker'],
    ['Mira', 'Valdez', '2002-09-04', 'Female', 'Student'],
    ['Jonas', 'Medina', '1986-04-22', 'Male', 'Driver'],
    ['Isabel', 'Lopez', '1993-11-10', 'Female', 'Teacher'],
    ['Noel', 'Santos', '1997-01-05', 'Male', 'Technician'],
];

// One clinic per day; quantities vary so charts show a useful trend.
$days = [
    ['2026-08-09', 1, 1], ['2026-08-11', 2, 2],
    ['2026-08-13', 1, 1], ['2026-08-15', 2, 2],
    ['2026-08-18', 1, 2], ['2026-08-20', 2, 1],
    ['2026-08-22', 1, 2], ['2026-08-25', 2, 1],
    ['2026-08-27', 1, 2], ['2026-08-29', 2, 2],
    ['2026-09-01', 1, 2], ['2026-09-03', 2, 1],
    ['2026-09-05', 1, 2], ['2026-09-08', 2, 2],
    ['2026-09-10', 1, 1], ['2026-09-12', 2, 2],
    ['2026-09-15', 1, 2], ['2026-09-17', 2, 1],
    ['2026-09-19', 1, 2], ['2026-09-22', 2, 2],
    ['2026-09-24', 1, 1], ['2026-09-26', 2, 1],
];
$cancelled = [5 => true, 13 => true, 21 => true, 30 => true];
$noShow = [9 => true, 17 => true, 27 => true];
$rejected = [3 => true, 24 => true];
$prices = [
    1 => 900.00, 2 => 1400.00, 3 => 450.00, 4 => 1500.00,
    5 => 9000.00, 6 => 14000.00, 7 => 5500.00, 8 => 12000.00,
    9 => 1800.00, 10 => 5000.00, 11 => 25000.00,
    12 => 4500.00, 13 => 8000.00,
];
$primaryServices = [1, 4, 9, 2, 3, 7, 1, 5, 9, 10, 4, 12, 8, 1, 6, 13, 2, 7, 3, 9, 11, 4, 1, 5, 2, 9, 12, 1, 8, 4, 7, 3, 9, 2, 1];
$feedback = [
    'The visit was smooth and the procedure was explained clearly.',
    'Helpful staff and a comfortable appointment.',
    'The clinic was organized and the treatment went well.',
    'Clear instructions and friendly care.',
    'Good service. I understood the aftercare steps.',
];
$plannedAppointments = array_sum(array_column($days, 2));
if ($plannedAppointments !== 35 || count($primaryServices) !== $plannedAppointments
    || $days[0][0] !== '2026-08-09' || $days[array_key_last($days)][0] >= '2026-09-28') {
    fwrite(STDERR, "Historical date or scenario plan is invalid.\n");
    exit(1);
}

$conn = (new Database())->connect();
if (!$conn) {
    fwrite(STDERR, "Application database is unavailable.\n");
    exit(1);
}
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    if ($conn->query('SELECT DATABASE()')->fetchColumn() !== 'db-oaprms-system'
        || (float) $conn->query('SELECT deposit_amount FROM site_settings WHERE id = 1')->fetchColumn() !== 150.0) {
        throw new RuntimeException('Unexpected database or demo deposit setting.');
    }
    foreach (['patients', 'appointments', 'schedules', 'appointment_billings', 'appointment_reviews'] as $table) {
        if ((int) $conn->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() !== 0) {
            throw new RuntimeException("Historical seed requires an empty $table table.");
        }
    }
    $staffId = (int) $conn->query("SELECT id FROM users WHERE user_role = 'Admin' ORDER BY id LIMIT 1")->fetchColumn();
    if ($staffId <= 0) {
        throw new RuntimeException('An Admin account must be preserved before seeding.');
    }
    $services = [];
    foreach ($conn->query('SELECT service_id, service_name, billing_unit FROM services WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $service) {
        $services[(int) $service['service_id']] = $service;
    }
    foreach (array_keys($prices) as $serviceId) {
        if (!isset($services[$serviceId])) {
            throw new RuntimeException("Required service $serviceId is missing.");
        }
    }
    if (($argv[1] ?? '') === '--check') {
        echo json_encode([
            'preflight' => 'passed',
            'fictional_patients' => count($people),
            'historical_schedules' => count($days),
            'historical_appointments' => $plannedAppointments,
            'date_range' => [$days[0][0], $days[array_key_last($days)][0]],
            'future_appointments' => 0,
        ], JSON_PRETTY_PRINT), PHP_EOL;
        exit(0);
    }

    $conn->beginTransaction();
    $patientIds = [];
    $patientCompleted = [];
    $registrationTimes = [
        '2026-08-02 08:17:00', '2026-08-04 10:42:00', '2026-08-06 13:08:00',
        '2026-08-08 09:31:00', '2026-08-10 15:14:00', '2026-08-11 11:26:00',
        '2026-08-12 08:53:00', '2026-08-13 14:37:00', '2026-08-14 10:05:00',
        '2026-08-16 16:21:00', '2026-08-17 09:46:00', '2026-08-20 12:12:00',
        '2026-08-22 15:39:00', '2026-08-24 10:28:00', '2026-09-02 08:41:00',
        '2026-09-05 13:16:00', '2026-09-09 10:34:00', '2026-09-14 15:02:00',
        '2026-09-19 09:57:00', '2026-09-23 14:23:00',
    ];
    foreach ($people as $index => [$first, $last, $birthdate, $gender, $occupation]) {
        $registered = new DateTimeImmutable($registrationTimes[$index], $timezone);
        $completed = $registered->modify('+2 hours');
        $email = strtolower($first . $last) . '.av@gmail.com';
        $age = (new DateTimeImmutable($birthdate, $timezone))->diff($time('2026-09-27', '00:00:00'))->y;
        $userId = demoInsert($conn,
            'INSERT INTO users (email, password, email_verified_at, user_role) VALUES (?, ?, ?, \'Patient\')',
            [$email, password_hash('password1', PASSWORD_DEFAULT), $stamp($registered)]);
        $hasCondition = in_array($index, [4, 11], true);
        $condition = $index === 4 ? 'Controlled hypertension' : ($index === 11 ? 'Asthma' : null);
        $patientId = demoInsert($conn,
            'INSERT INTO patients (user_id, firstname, lastname, age, gender, phone_number, email, birthdate, civil_status, home_address, occupation, created_at, profile_completed_at, profile_completed_by_user_id, profile_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'Complete\')',
            [$userId, $first, $last, $age, $gender, sprintf('0999%07d', $index + 1), $email, $birthdate,
                'Single', $index % 2 === 0 ? 'Alcala, Cagayan' : 'Tuguegarao City, Cagayan', $occupation,
                $stamp($registered), $stamp($completed), $userId]);
        $patientIds[$index] = $patientId;
        $patientCompleted[$index] = $completed;
        demoInsert($conn,
            'INSERT INTO patient_dental_history (patient_id, previous_dentist, last_dental_visit, treatment_done, reason_for_visit, referred_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$patientId, 'Previous dental provider', '2025-11-15', 'Routine cleaning', 'Routine dental care', 'Self', $stamp($completed)]);
        demoInsert($conn,
            'INSERT INTO patient_medical_history (patient_id, good_health, medical_condition, medical_condition_detail, serious_illness, hospitalized, medication, smoke, alcohol, drugs, allergy, pregnant, no_known_conditions, created_at, blood_type, blood_pressure) VALUES (?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0, ?, ?, ?, ?, ?)',
            [$patientId, 1, $hasCondition ? 1 : 0, $condition, $gender === 'Female' ? 0 : null,
                $hasCondition ? 0 : 1, $stamp($completed), $index % 2 === 0 ? 'O+' : 'A+', $hasCondition ? '130/85' : '120/80']);
        if ($hasCondition) {
            demoInsert($conn, 'INSERT INTO patient_conditions (patient_id, `condition`) VALUES (?, ?)', [$patientId, $condition]);
        }
        demoInsert($conn,
            'INSERT INTO patient_consent (patient_id, consent_name, consent_for, consent_date, created_at) VALUES (?, ?, ?, ?, ?)',
            [$patientId, "$first $last", 'myself', $registered->format('Y-m-d'), $stamp($completed)]);
    }

    $counts = ['patients' => count($patientIds), 'schedules' => 0, 'appointments' => 0, 'completed' => 0,
        'cancelled' => 0, 'no_show' => 0, 'rejected' => 0, 'checkins' => 0, 'deposits' => 0,
        'billings' => 0, 'reviews' => 0, 'audit_logs' => 0];
    $visitIndex = 0;
    foreach ($days as [$date, $clinicId, $visits]) {
        $scheduleId = demoInsert($conn,
            'INSERT INTO schedules (clinic_id, sched_date, start_time, end_time, max_appointments) VALUES (?, ?, ?, \'17:00:00\', 8)',
            [$clinicId, $date, $clinicId === 1 ? '08:00:00' : '10:00:00']);
        $counts['schedules']++;
        for ($slot = 0; $slot < $visits; $slot++, $visitIndex++) {
            $patientIndex = $date < '2026-09-08' ? $visitIndex % 14 : ($visitIndex * 7) % 20;
            $patientId = $patientIds[$patientIndex];
            $day = $time($date, '00:00:00');
            $requested = $day->modify('-9 days')->setTime(9, 0);
            $earliest = $patientCompleted[$patientIndex]->modify('+1 hour');
            if ($requested < $earliest) $requested = $earliest;
            if ($requested > $day->modify('-7 days')->setTime(23, 59)) {
                throw new RuntimeException('Historical booking lead time would be too short.');
            }
            $accepted = $day->modify('-2 days')->setTime(13, 0);
            $submitted = $accepted->modify('+1 hour 10 minutes');
            $verified = $accepted->modify('+1 hour 30 minutes');
            $deadline = $accepted->modify('+8 hours');
            $arrival = $day->setTime($clinicId === 1 ? 9 : 10, 0)->modify('+' . ($slot * 100) . ' minutes');
            $ready = $arrival->modify('+10 minutes');
            $treatment = $arrival->modify('+20 minutes');
            $finished = $arrival->modify('+80 minutes');
            $cancelAt = $day->modify('-1 day')->setTime(11, 0);
            $isCancelled = isset($cancelled[$visitIndex]);
            $isNoShow = isset($noShow[$visitIndex]);
            $isRejected = isset($rejected[$visitIndex]);
            $status = $isRejected ? 'Rejected' : ($isCancelled ? 'Cancelled' : ($isNoShow ? 'No-show' : 'Completed'));
            $depositRequired = !$isRejected && !($isCancelled && in_array($visitIndex, [5, 21], true))
                && !($status === 'Completed' && $visitIndex % 10 === 0);
            $code = $isRejected ? null : sprintf('AVC-H%04d', $visitIndex + 1);
            $primaryServiceId = $primaryServices[$visitIndex];
            $selectedServiceIds = [$primaryServiceId];
            if ($status === 'Completed' && $visitIndex % 6 === 0) {
                $selectedServiceIds[] = $primaryServiceId === 3 ? 1 : 3;
            }
            $appointmentId = demoInsert($conn,
                'INSERT INTO appointments (patient_id, schedule_id, clinic_id, date, status, deposit_required, payment_deadline_at, reviewed_by_user_id, reviewed_at, accepted_for_payment_at, rejected_at, rejection_reason, appointment_code, code_generated_at, confirmed_at, treatment_started_at, completed_at, cancelled_at, cancellation_reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$patientId, $scheduleId, $clinicId, $date, $status, (int) $depositRequired,
                    $depositRequired ? $stamp($deadline) : null, $staffId, $stamp($accepted),
                    $isRejected ? null : $stamp($accepted), $isRejected ? $stamp($accepted) : null,
                    $isRejected ? 'Requested treatment was unavailable on this date.' : null,
                    $code, $code ? $stamp($verified) : null, $code ? $stamp($verified) : null,
                    $status === 'Completed' ? $stamp($treatment) : null,
                    $status === 'Completed' ? $stamp($finished) : null,
                    $isCancelled ? $stamp($cancelAt) : null,
                    $isCancelled ? 'Patient requested cancellation.' : null,
                    $stamp($requested)]);
            $counts['appointments']++;
            foreach ($selectedServiceIds as $serviceId) {
                $conn->prepare('INSERT INTO appointment_services (appointment_id, service_id, quantity, unit_price_snapshot, billing_unit_snapshot) VALUES (?, ?, 1.00, ?, ?)')
                    ->execute([$appointmentId, $serviceId, $prices[$serviceId], $services[$serviceId]['billing_unit']]);
            }

            $reference = '9900' . str_replace('-', '', $date) . sprintf('%04d', $visitIndex + 1);
            if ($depositRequired) {
                $depositStatus = $isNoShow ? 'Forfeited' : ($isCancelled ? ($visitIndex === 13 ? 'Refunded' : 'For Refund') : 'Verified');
                $depositUpdated = $isNoShow ? $day->modify('+1 day')->setTime(8, 0)
                    : ($isCancelled ? $cancelAt : $verified);
                demoInsert($conn,
                    'INSERT INTO appointment_deposits (appointment_id, amount, receipt_amount, gcash_reference, gcash_transaction_at, status, submitted_at, verified_by_user_id, verified_at, refund_reason, refunded_by_user_id, refunded_at, refund_notes, created_at, updated_at) VALUES (?, 150.00, 150.00, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$appointmentId, $reference, $stamp($submitted->modify('-5 minutes')), $depositStatus,
                        $stamp($submitted), $staffId, $stamp($verified),
                        $isCancelled ? 'Patient cancelled a confirmed visit.' : null,
                        $depositStatus === 'Refunded' ? $staffId : null,
                        $depositStatus === 'Refunded' ? $stamp($cancelAt->modify('+2 hours')) : null,
                        $depositStatus === 'Refunded' ? 'Demo refund recorded.' : null,
                        $stamp($accepted), $stamp($depositUpdated)]);
                $counts['deposits']++;
            }

            if ($status === 'Completed') {
                demoInsert($conn,
                    'INSERT INTO appointment_checkins (appointment_id, arrived_at, checked_in_by_user_id, lookup_method, checkin_status, profile_required_at_arrival, ready_at, queue_status, queue_entered_at, created_at, updated_at) VALUES (?, ?, ?, \'Code\', \'Ready\', 0, ?, \'Waiting\', ?, ?, ?)',
                    [$appointmentId, $stamp($arrival), $staffId, $stamp($ready), $stamp($arrival), $stamp($arrival), $stamp($ready)]);
                $counts['checkins']++;
                $serviceTotal = array_sum(array_map(static fn(int $id): float => $prices[$id], $selectedServiceIds));
                $depositApplied = $depositRequired ? 150.00 : 0.00;
                $balance = $serviceTotal - $depositApplied;
                $billingId = demoInsert($conn,
                    'INSERT INTO appointment_billings (appointment_id, actual_service_amount, deposit_applied, remaining_balance, cash_received, payment_status, recorded_by_user_id, recorded_at, paid_at, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, \'Paid\', ?, ?, ?, ?, ?, ?)',
                    [$appointmentId, $serviceTotal, $depositApplied, $balance, $balance, $staffId,
                        $stamp($finished), $stamp($finished), 'Fictional defense history.', $stamp($finished), $stamp($finished)]);
                foreach ($selectedServiceIds as $order => $serviceId) {
                    demoInsert($conn,
                        'INSERT INTO appointment_billing_items (billing_id, service_id, service_name_snapshot, quantity, unit_price, billing_unit, pricing_source, sort_order, created_at) VALUES (?, ?, ?, 1.00, ?, ?, \'defense-demo\', ?, ?)',
                        [$billingId, $serviceId, $services[$serviceId]['service_name'], $prices[$serviceId],
                            $services[$serviceId]['billing_unit'], $order, $stamp($finished)]);
                }
                $counts['completed']++;
                $counts['billings']++;
                if ($visitIndex % 2 === 0) {
                    $rating = [5, 4, 5, 3, 4][$visitIndex % 5];
                    demoInsert($conn,
                        'INSERT INTO appointment_reviews (appointment_id, rating, feedback, created_at) VALUES (?, ?, ?, ?)',
                        [$appointmentId, $rating, $feedback[$visitIndex % count($feedback)], $stamp($finished->modify('+1 day'))]);
                    $counts['reviews']++;
                }
            } elseif ($isCancelled) {
                $counts['cancelled']++;
            } elseif ($isNoShow) {
                $counts['no_show']++;
            } else {
                $counts['rejected']++;
            }

            $eventAt = $status === 'Completed' ? $finished : ($isCancelled ? $cancelAt : ($isNoShow ? $day->modify('+1 day')->setTime(8, 0) : $accepted));
            demoInsert($conn,
                'INSERT INTO audit_logs (entity_type, entity_id, action, description, old_values, new_values, performed_by_user_id, performed_by_name, performed_by_role, source, performed_at) VALUES (\'appointment\', ?, \'status_changed\', ?, ?, ?, ?, ?, ?, ?, ?)',
                [$appointmentId, "Fictional historical appointment marked $status.",
                    json_encode(['status' => $status === 'Rejected' ? 'Pending Review' : 'Confirmed'], JSON_THROW_ON_ERROR),
                    json_encode(['status' => $status], JSON_THROW_ON_ERROR),
                    $isNoShow ? null : $staffId, $isNoShow ? 'System' : 'Defense preparation',
                    $isNoShow ? 'System' : 'Admin', $isNoShow ? 'System' : 'User', $stamp($eventAt)]);
            $counts['audit_logs']++;
        }
    }

    if ($visitIndex !== 35 || $counts['appointments'] !== array_sum(array_slice($counts, 3, 4))) {
        throw new RuntimeException('Historical scenario count mismatch.');
    }
    $conn->commit();
    echo json_encode($counts, JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $error) {
    if ($conn->inTransaction()) $conn->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
