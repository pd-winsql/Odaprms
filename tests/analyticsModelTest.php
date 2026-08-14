<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/analyticsModel.php';

function analyticsExpect($condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$conn->beginTransaction();

try {
    $clinicId = (int) $conn->query('SELECT clinic_id FROM clinics ORDER BY clinic_id LIMIT 1')->fetchColumn();
    $serviceId = (int) $conn->query('SELECT service_id FROM services ORDER BY service_id LIMIT 1')->fetchColumn();
    analyticsExpect($clinicId > 0 && $serviceId > 0, 'Analytics fixtures can use an existing clinic and service.');

    $testDate = '2001-02-03';
    $schedule = $conn->prepare('INSERT INTO schedules (clinic_id, sched_date, max_appointments) VALUES (:clinic, :date, 10)');
    $schedule->execute([':clinic' => $clinicId, ':date' => $testDate]);
    $scheduleId = (int) $conn->lastInsertId();

    $patientIds = [];
    for ($i = 1; $i <= 4; $i++) {
        $patient = $conn->prepare("INSERT INTO patients (firstname, lastname, email, created_at) VALUES (:first, 'Analytics', :email, :created)");
        $patient->execute([
            ':first' => 'Fixture' . $i,
            ':email' => "analytics-fixture-{$i}@example.invalid",
            ':created' => $testDate . ' 08:00:00',
        ]);
        $patientIds[] = (int) $conn->lastInsertId();
    }

    $statuses = ['Completed', 'Cancelled', 'No-show', 'Pending Review'];
    foreach ($statuses as $index => $status) {
        $appointment = $conn->prepare('
            INSERT INTO appointments (patient_id, schedule_id, clinic_id, date, status, completed_at)
            VALUES (:patient, :schedule, :clinic, :date, :status, :completed_at)
        ');
        $appointment->execute([
            ':patient' => $patientIds[$index],
            ':schedule' => $scheduleId,
            ':clinic' => $clinicId,
            ':date' => $testDate,
            ':status' => $status,
            ':completed_at' => $status === 'Completed' ? $testDate . ' 10:00:00' : null,
        ]);
        $appointmentId = (int) $conn->lastInsertId();
        $conn->prepare('INSERT INTO appointment_services (appointment_id, service_id) VALUES (:appointment, :service)')
            ->execute([':appointment' => $appointmentId, ':service' => $serviceId]);
    }

    $filters = AnalyticsModel::normalizeFilters([
        'date_from' => $testDate,
        'date_to' => $testDate,
        'clinic_id' => $clinicId,
    ]);
    $data = (new AnalyticsModel($conn))->getDashboardData($filters);
    $kpis = $data['kpis'];

    analyticsExpect($kpis['appointments'] === 4, 'Total appointments count each appointment once.');
    analyticsExpect($kpis['completed'] === 1, 'Completed visits use the Completed status.');
    analyticsExpect($kpis['new_patients'] === 4, 'New patients use patient creation dates and clinic association.');
    analyticsExpect($kpis['capacity'] === 10 && $kpis['booked'] === 2 && $kpis['utilization_rate'] === 20.0, 'Utilization matches active booking capacity rules.');
    analyticsExpect($kpis['cancellation_rate'] === 33.3, 'Cancellation rate uses accepted-stage appointments.');
    analyticsExpect($kpis['no_show_rate'] === 50.0, 'No-show rate uses completed and no-show outcomes.');
    analyticsExpect(array_sum(array_column($data['appointment_trend'], 'value')) === 4, 'Appointment trend totals match the KPI.');
    analyticsExpect(($data['top_services'][0]['value'] ?? 0) === 4, 'Service popularity counts appointment-service selections.');
    analyticsExpect(($data['clinic_comparison'][0]['appointments'] ?? 0) === 4, 'Clinic comparison respects the selected clinic.');
    analyticsExpect($data['meta']['granularity'] === 'day', 'Automatic grouping uses daily buckets for short ranges.');

    $monthlyFilters = AnalyticsModel::normalizeFilters([
        'date_from' => '2001-01-01',
        'date_to' => '2001-12-31',
        'clinic_id' => $clinicId,
        'group_by' => 'month',
    ]);
    $monthlyData = (new AnalyticsModel($conn))->getDashboardData($monthlyFilters);
    analyticsExpect($monthlyData['meta']['granularity'] === 'month' && count($monthlyData['appointment_trend']) === 12, 'Monthly grouping returns one bucket per month.');

    $analytics = new AnalyticsModel($conn);
    analyticsExpect(count($analytics->getDrilldown($filters, 'completed')['rows']) === 1, 'Completed KPI drill-down returns the matching appointment.');
    analyticsExpect(count($analytics->getDrilldown($filters, 'service', (string) $serviceId)['rows']) === 4, 'Service chart drill-down returns matching appointments.');
    $paginated = $analytics->getDrilldown($filters, 'service', (string) $serviceId, 2, 2);
    analyticsExpect(
        count($paginated['rows']) === 2
        && $paginated['pagination']['page'] === 2
        && $paginated['pagination']['total'] === 4
        && $paginated['pagination']['total_pages'] === 2,
        'Drill-down records are paginated with stable totals.'
    );
    analyticsExpect(count($analytics->getDrilldown($filters, 'patient_bucket', $testDate)['rows']) === 4, 'Patient trend drill-down uses the selected bucket.');
    analyticsExpect(count($analytics->getDrilldown($filters, 'schedules')['rows']) === 1, 'Utilization KPI drill-down returns matching schedules.');

    try {
        AnalyticsModel::normalizeFilters(['date_from' => '2026-02-10', 'date_to' => '2026-02-01']);
        throw new RuntimeException('Invalid date order was accepted.');
    } catch (InvalidArgumentException $e) {
        analyticsExpect(str_contains($e->getMessage(), 'start date'), 'Invalid date ranges are rejected.');
    }

    try {
        $analytics->getDrilldown($filters, 'unsupported');
        throw new RuntimeException('Invalid drill-down dimension was accepted.');
    } catch (InvalidArgumentException $e) {
        analyticsExpect(str_contains($e->getMessage(), 'drill-down'), 'Invalid drill-down dimensions are rejected.');
    }
} finally {
    if ($conn->inTransaction()) $conn->rollBack();
}
