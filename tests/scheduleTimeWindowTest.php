<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/scheduleModel.php';
require_once __DIR__ . '/../apps/models/clinicModel.php';

function scheduleWindowExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

function scheduleWindowTime(int $minutes): string
{
    return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
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
    $defaultStart = Schedule::normalizeTime((string) ($clinics[0]['default_start_time'] ?? ''));
    $defaultEnd = Schedule::normalizeTime((string) ($clinics[0]['default_end_time'] ?? ''));
    scheduleWindowExpect(
        $defaultStart !== null && $defaultEnd !== null && $defaultStart < $defaultEnd
        && Schedule::usesFiveMinuteIncrement($defaultStart)
        && Schedule::usesFiveMinuteIncrement($defaultEnd),
        'Clinic default hours are available.'
    );
    scheduleWindowExpect(Schedule::normalizeTime('10:00') === '10:00:00', 'Opening times are normalized.');
    scheduleWindowExpect(Schedule::normalizeTime('25:00') === null, 'Invalid times are rejected.');
    scheduleWindowExpect(Schedule::usesFiveMinuteIncrement('10:05'), 'Five-minute schedule increments are accepted.');
    scheduleWindowExpect(!Schedule::usesFiveMinuteIncrement('10:02'), 'Off-step schedule minutes are rejected.');
    $storedTransitionMinutes = (int) $conn->query(
        'SELECT clinic_transition_minutes FROM site_settings WHERE id = 1'
    )->fetchColumn();
    $transitionMinutes = $scheduleModel->getTransitionMinutes();
    scheduleWindowExpect(
        $transitionMinutes === $storedTransitionMinutes,
        'The schedule model uses the saved clinic separation policy.'
    );
    $probeTransitionMinutes = $storedTransitionMinutes === 35 ? 40 : 35;
    $conn->beginTransaction();
    try {
        $conn->prepare('UPDATE site_settings SET clinic_transition_minutes = :minutes WHERE id = 1')
            ->execute([':minutes' => $probeTransitionMinutes]);
        $dynamicScheduleModel = new Schedule($conn);
        scheduleWindowExpect(
            $dynamicScheduleModel->getTransitionMinutes() === $probeTransitionMinutes,
            'A changed clinic separation is loaded dynamically on the next request.'
        );
    } finally {
        $conn->rollBack();
    }

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

    $exactTransitionStart = 12 * 60 + $transitionMinutes;
    $shortTransitionStart = max(0, $exactTransitionStart - 1);
    $secondWindowEnd = $exactTransitionStart + 60;
    scheduleWindowExpect(
        $scheduleModel->findWindowConflict($secondClinicId, $date, scheduleWindowTime($shortTransitionStart), scheduleWindowTime($secondWindowEnd)) !== null,
        'A cross-clinic transition shorter than the saved policy is rejected.'
    );
    scheduleWindowExpect(
        $scheduleModel->findWindowConflict($secondClinicId, $date, scheduleWindowTime($exactTransitionStart), scheduleWindowTime($secondWindowEnd)) === null,
        'A cross-clinic transition matching the saved policy is accepted.'
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
        'start_time' => scheduleWindowTime($exactTransitionStart),
        'end_time' => scheduleWindowTime($secondWindowEnd),
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
