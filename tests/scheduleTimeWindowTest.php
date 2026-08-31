<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/scheduleModel.php';
require_once __DIR__ . '/../apps/models/clinicModel.php';

function scheduleWindowExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$scheduleModel = new Schedule($conn);
$clinicModel = new Clinic($conn);
$clinics = $clinicModel->getAllClinics();
$createdIds = [];

try {
    scheduleWindowExpect(count($clinics) >= 2, 'Two clinic fixtures are available.');
    $firstClinicId = (int) $clinics[0]['clinic_id'];
    $secondClinicId = (int) $clinics[1]['clinic_id'];
    scheduleWindowExpect(
        ($clinics[0]['default_start_time'] ?? '') === '08:00:00'
        && ($clinics[0]['default_end_time'] ?? '') === '17:00:00',
        'Clinic default hours are available.'
    );
    scheduleWindowExpect(Schedule::normalizeTime('10:00') === '10:00:00', 'Opening times are normalized.');
    scheduleWindowExpect(Schedule::normalizeTime('25:00') === null, 'Invalid times are rejected.');
    scheduleWindowExpect(Schedule::usesFiveMinuteIncrement('10:05'), 'Five-minute schedule increments are accepted.');
    scheduleWindowExpect(!Schedule::usesFiveMinuteIncrement('10:02'), 'Off-step schedule minutes are rejected.');

    $date = null;
    $dateCheck = $conn->prepare('SELECT COUNT(*) FROM schedules WHERE sched_date = :date');
    for ($days = 120; $days <= 500; $days++) {
        $candidate = date('Y-m-d', strtotime("+{$days} days"));
        $dateCheck->execute([':date' => $candidate]);
        if ((int) $dateCheck->fetchColumn() === 0) {
            $date = $candidate;
            break;
        }
    }
    scheduleWindowExpect($date !== null, 'An isolated future date is available.');

    $first = $scheduleModel->addSchedules($firstClinicId, [[
        'sched_date' => $date,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'max_appointments' => 8,
    ]]);
    scheduleWindowExpect(($first['success'] ?? false) === true, 'The first clinic window is created.');
    $createdIds[] = (int) $conn->query(
        'SELECT schedule_id FROM schedules WHERE clinic_id=' . $firstClinicId . ' AND sched_date=' . $conn->quote($date)
    )->fetchColumn();

    scheduleWindowExpect(
        $scheduleModel->findWindowConflict($secondClinicId, $date, '13:29:00', '17:00:00') !== null,
        'An 89-minute cross-clinic transition is rejected.'
    );
    scheduleWindowExpect(
        $scheduleModel->findWindowConflict($secondClinicId, $date, '13:30:00', '17:00:00') === null,
        'An exact 90-minute cross-clinic transition is accepted.'
    );
    scheduleWindowExpect(
        $scheduleModel->findWindowConflict($secondClinicId, $date, '11:00:00', '14:00:00') !== null,
        'Overlapping clinic windows are rejected.'
    );
    scheduleWindowExpect(
        $scheduleModel->findWindowConflict($firstClinicId, $date, '18:00:00', '19:00:00') !== null,
        'A clinic cannot have a second window on the same date.'
    );

    $second = $scheduleModel->addSchedules($secondClinicId, [[
        'sched_date' => $date,
        'start_time' => '13:30:00',
        'end_time' => '17:00:00',
        'max_appointments' => 8,
    ]]);
    scheduleWindowExpect(($second['success'] ?? false) === true, 'The second clinic can use the same date with a valid window.');
    $createdIds[] = (int) $conn->query(
        'SELECT schedule_id FROM schedules WHERE clinic_id=' . $secondClinicId . ' AND sched_date=' . $conn->quote($date)
    )->fetchColumn();

    $sameDate = $conn->prepare('SELECT COUNT(*) FROM schedules WHERE sched_date = :date');
    $sameDate->execute([':date' => $date]);
    scheduleWindowExpect((int) $sameDate->fetchColumn() === 2, 'Both clinics retain schedules on the same date.');
} finally {
    foreach (array_reverse(array_filter($createdIds)) as $scheduleId) {
        $conn->prepare('DELETE FROM schedules WHERE schedule_id = :id')->execute([':id' => $scheduleId]);
    }
}

echo "Schedule time-window test completed.\n";
