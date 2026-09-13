<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/billingModel.php';

function billingInsightsExpect($condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$conn->beginTransaction();

try {
    $clinicId = (int) $conn->query('SELECT clinic_id FROM clinics ORDER BY clinic_id LIMIT 1')->fetchColumn();
    $serviceId = (int) $conn->query('SELECT service_id FROM services ORDER BY service_id LIMIT 1')->fetchColumn();
    billingInsightsExpect($clinicId > 0 && $serviceId > 0, 'Billing fixtures can use an existing clinic and service.');

    $testDate = '1997-04-06';
    $schedule = $conn->prepare('INSERT INTO schedules (clinic_id, sched_date, max_appointments) VALUES (:clinic, :date, 5)');
    $schedule->execute([':clinic' => $clinicId, ':date' => $testDate]);
    $scheduleId = (int) $conn->lastInsertId();

    $patientIds = [];
    foreach (['Settlement', 'Refund'] as $name) {
        $patient = $conn->prepare("INSERT INTO patients (firstname, lastname, email, created_at) VALUES (:first, 'BillingFixture', :email, :created)");
        $patient->execute([
            ':first' => $name,
            ':email' => strtolower($name) . '-billing-fixture@example.invalid',
            ':created' => $testDate . ' 08:00:00',
        ]);
        $patientIds[] = (int) $conn->lastInsertId();
    }

    $appointment = $conn->prepare('
        INSERT INTO appointments (patient_id, schedule_id, clinic_id, date, status, completed_at, cancelled_at)
        VALUES (:patient, :schedule, :clinic, :date, :status, :completed_at, :cancelled_at)
    ');
    $appointmentIds = [];
    foreach (['Completed', 'Cancelled'] as $index => $status) {
        $appointment->execute([
            ':patient' => $patientIds[$index],
            ':schedule' => $scheduleId,
            ':clinic' => $clinicId,
            ':date' => $testDate,
            ':status' => $status,
            ':completed_at' => $status === 'Completed' ? $testDate . ' 12:00:00' : null,
            ':cancelled_at' => $status === 'Cancelled' ? $testDate . ' 11:00:00' : null,
        ]);
        $appointmentIds[] = (int) $conn->lastInsertId();
        $conn->prepare('INSERT INTO appointment_services (appointment_id, service_id) VALUES (:appointment, :service)')
            ->execute([':appointment' => $appointmentIds[$index], ':service' => $serviceId]);
    }

    $conn->prepare("
        INSERT INTO appointment_deposits (appointment_id, amount, status, verified_at)
        VALUES (:appointment, 400, 'Verified', :recorded)
    ")->execute([':appointment' => $appointmentIds[0], ':recorded' => $testDate . ' 09:00:00']);
    $conn->prepare("
        INSERT INTO appointment_billings
            (appointment_id, actual_service_amount, deposit_applied, remaining_balance,
             cash_received, payment_status, recorded_at, paid_at)
        VALUES (:appointment, 1000, 400, 600, 650, 'Paid', :recorded, :paid)
    ")->execute([
        ':appointment' => $appointmentIds[0],
        ':recorded' => $testDate . ' 12:00:00',
        ':paid' => $testDate . ' 12:00:00',
    ]);
    $conn->prepare("
        INSERT INTO appointment_deposits (appointment_id, amount, status, refunded_at)
        VALUES (:appointment, 400, 'Refunded', :refunded)
    ")->execute([':appointment' => $appointmentIds[1], ':refunded' => $testDate . ' 13:00:00']);

    $filters = BillingModel::normalizeInsightFilters([
        'period' => 'custom',
        'date_from' => $testDate,
        'date_to' => $testDate,
        'clinic_id' => $clinicId,
    ]);
    $data = (new BillingModel($conn))->getBillingInsights($filters, 1, 15);
    $summary = $data['summary'];

    billingInsightsExpect($summary['gross_collected'] === 1000.0, 'Gross collections combine the applied deposit and collected balance.');
    billingInsightsExpect($summary['refunds'] === 400.0 && $summary['net_collected'] === 600.0, 'Refunded deposits are deducted from net collected.');
    billingInsightsExpect($summary['settled_visits'] === 1 && $summary['average_per_visit'] === 600.0, 'Settlement count and average use finalized visits.');
    billingInsightsExpect($summary['deposit_share'] === 40.0, 'Deposit contribution uses gross settlement collections.');
    billingInsightsExpect(count($data['trend']) === 1 && $data['trend'][0]['net'] === 600.0, 'Collection movement combines settlement and refund activity by date.');
    billingInsightsExpect(count($data['clinics']) === 1 && $data['clinics'][0]['net_collected'] === 600.0, 'Clinic comparison uses the same net collection definition.');
    billingInsightsExpect(
        $data['records']['pagination']['total'] === 1
        && $data['records']['items'][0]['balance_collected'] === 600.0
        && $data['records']['items'][0]['change'] === 50.0,
        'Settlement records are paginated and distinguish collected cash from change.'
    );

    try {
        BillingModel::normalizeInsightFilters([
            'period' => 'custom',
            'date_from' => '2026-09-10',
            'date_to' => '2026-09-01',
        ]);
        throw new RuntimeException('Invalid settlement date order was accepted.');
    } catch (InvalidArgumentException $e) {
        billingInsightsExpect(str_contains($e->getMessage(), 'start date'), 'Invalid settlement date ranges are rejected.');
    }
} finally {
    if ($conn->inTransaction()) $conn->rollBack();
}

echo "Billing insights model test completed.\n";
