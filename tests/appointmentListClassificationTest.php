<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';
require_once __DIR__ . '/../apps/models/depositModel.php';

function classificationExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$model = new Appointment($conn);
$fixture = $conn->query('SELECT patient_id, clinic_id, schedule_id, email FROM vw_appointment_overview LIMIT 1')->fetch(PDO::FETCH_ASSOC);
classificationExpect((bool) $fixture, 'An existing appointment provides reference IDs for rollback-only fixtures.');
$conn->beginTransaction();
try {
    $insert = $conn->prepare('INSERT INTO appointments(patient_id,clinic_id,schedule_id,date,status,deposit_required) VALUES(:patient,:clinic,:schedule,:date,:status,0)');
    $dates = $conn->query('SELECT CURDATE() AS today, DATE_SUB(CURDATE(), INTERVAL 1 DAY) AS yesterday, DATE_ADD(CURDATE(), INTERVAL 1 DAY) AS tomorrow')->fetch(PDO::FETCH_ASSOC);
    $cases = [];
    foreach ($dates as $when => $date) {
        foreach (['Completed', 'Cancelled', 'No-show', 'Rejected', 'Confirmed', 'Checked In', 'In Progress', 'Pending Review', 'Awaiting Deposit', 'Payment Under Review'] as $status) {
            $insert->execute([':patient' => $fixture['patient_id'], ':clinic' => $fixture['clinic_id'], ':schedule' => $fixture['schedule_id'], ':date' => $date, ':status' => $status]);
            $cases[] = ['id' => (int) $conn->lastInsertId(), 'when' => $when, 'status' => $status];
        }
    }
    $ids = static fn(array $rows): array => array_map('intval', array_column($rows, 'appointment_id'));
    $staffUpcoming = $ids($model->getAllUpcomingWithStatus());
    $staffPast = $ids($model->getAdminPastAppointments());
    $patientUpcoming = $ids($model->getPatientUpcomingAppointments($fixture['patient_id']));
    $patientPast = $ids($model->getPatientPastAppointments($fixture['patient_id']));
    // The legacy email query does not select appointment_id; inspect its results separately.
    $emailUpcoming = $model->getUpcomingWithStatus($fixture['email']);
    classificationExpect(!array_filter($emailUpcoming, static fn($row) => in_array($row['status'], ['Completed', 'Cancelled', 'No-show', 'Rejected'], true)), 'Legacy patient Upcoming excludes terminal statuses.');
    foreach ($cases as $case) {
        $terminal = in_array($case['status'], ['Completed', 'Cancelled', 'No-show', 'Rejected'], true);
        $expectedStaffUpcoming = !$terminal && $case['when'] !== 'yesterday';
        $expectedPatientUpcoming = !$terminal && $case['when'] !== 'yesterday';
        classificationExpect(
            in_array($case['id'], $staffUpcoming, true) === $expectedStaffUpcoming
            && in_array($case['id'], $staffPast, true) === !$expectedStaffUpcoming
            && in_array($case['id'], $patientUpcoming, true) === $expectedPatientUpcoming
            && in_array($case['id'], $patientPast, true) === !$expectedPatientUpcoming,
            "{$case['when']} {$case['status']} is classified without Upcoming/Past overlap."
        );
    }
    $deposits = new DepositModel($conn);
    $beforePendingCount = $deposits->getPendingReviewCount();
    foreach ($cases as $case) {
        if ($case['status'] === 'Confirmed') {
            $conn->prepare("INSERT INTO appointment_deposits(appointment_id,status) VALUES(:id,'Verified')")
                ->execute([':id' => $case['id']]);
        }
        if (!in_array($case['status'], ['Awaiting Deposit', 'Payment Under Review'], true)) continue;
        $depositStatus = $case['status'] === 'Payment Under Review' ? 'Under Review' : 'Awaiting Submission';
        $conn->prepare('INSERT INTO appointment_deposits(appointment_id,status,receipt_path) VALUES(:id,:status,:receipt)')
            ->execute([':id' => $case['id'], ':status' => $depositStatus, ':receipt' => $depositStatus === 'Under Review' ? 'test-only-proof.jpg' : null]);
        $deadline = $case['when'] === 'today' ? 'DATE_SUB(NOW(), INTERVAL 1 HOUR)' : 'DATE_ADD(NOW(), INTERVAL 2 DAY)';
        $conn->exec("UPDATE appointments SET payment_deadline_at={$deadline} WHERE appointment_id=" . $case['id']);
    }
    $insert->execute([':patient' => $fixture['patient_id'], ':clinic' => $fixture['clinic_id'], ':schedule' => $fixture['schedule_id'], ':date' => $dates['yesterday'], ':status' => 'Confirmed']);
    $attendedId = (int) $conn->lastInsertId();
    $staffId = (int) $conn->query("SELECT id FROM users WHERE user_role='Dental Assistant' LIMIT 1")->fetchColumn();
    $conn->prepare("INSERT INTO appointment_checkins(appointment_id,arrived_at,checked_in_by_user_id,lookup_method,checkin_status) VALUES(:id,NOW(),:staff,'Code','Ready')")
        ->execute([':id' => $attendedId, ':staff' => $staffId]);
    $conn->prepare("INSERT INTO appointment_deposits(appointment_id,status) VALUES(:id,'Verified')")->execute([':id' => $attendedId]);
    $deposits->expireUnpaidAppointments();
    classificationExpect($conn->query('SELECT status FROM appointments WHERE appointment_id=' . $attendedId)->fetchColumn() === 'Confirmed', 'A recorded check-in prevents automatic No-show even if the appointment status is still Confirmed.');
    classificationExpect($conn->query('SELECT status FROM appointment_deposits WHERE appointment_id=' . $attendedId)->fetchColumn() === 'Verified', 'The attended patient’s verified deposit is not forfeited.');
    foreach ($cases as $case) {
        $expected = $case['status'];
        if ($case['when'] === 'yesterday' && $expected === 'Pending Review') $expected = 'Rejected';
        if ($case['when'] === 'yesterday' && $expected === 'Confirmed') $expected = 'No-show';
        if ($case['when'] !== 'tomorrow' && $expected === 'Awaiting Deposit') $expected = 'Cancelled';
        $row = $conn->query('SELECT status,rejection_reason,cancellation_reason FROM appointments WHERE appointment_id=' . $case['id'])->fetch(PDO::FETCH_ASSOC);
        classificationExpect($row['status'] === $expected, "{$case['when']} {$case['status']} follows the cutoff policy.");
        if ($case['status'] === 'Confirmed') {
            $expectedDeposit = $case['when'] === 'yesterday' ? 'Forfeited' : 'Verified';
            classificationExpect($conn->query('SELECT status FROM appointment_deposits WHERE appointment_id=' . $case['id'])->fetchColumn() === $expectedDeposit, "{$case['when']} confirmed deposit follows the existing forfeiture rule.");
            if ($expected === 'No-show') {
                classificationExpect((int) $conn->query("SELECT COUNT(*) FROM audit_logs WHERE entity_type='appointment' AND entity_id=" . $case['id'])->fetchColumn() === 1, 'Automatic No-show is audited once.');
            }
        }
        if ($expected !== $case['status'] && $expected !== 'No-show') {
            classificationExpect((bool) ($row['rejection_reason'] ?: $row['cancellation_reason']), 'Automatic expiry stores its reason.');
            $auditCount = (int) $conn->query("SELECT COUNT(*) FROM audit_logs WHERE entity_type='appointment' AND entity_id=" . $case['id'])->fetchColumn();
            classificationExpect($auditCount === 1, 'Automatic expiry is audited once.');
        }
    }
    classificationExpect($deposits->getPendingReviewCount() === $beforePendingCount + 3, 'Past-date submitted payments remain included in the staff pending-work count.');
    classificationExpect($deposits->expireUnpaidAppointments() === 0, 'Repeated expiry checks are idempotent.');
} finally {
    $conn->rollBack();
}
echo "Appointment list classification test completed.\n";
