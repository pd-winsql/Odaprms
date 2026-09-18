<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/scheduleModel.php';

$conn = (new Database())->connect();
if (!$conn) throw new RuntimeException('Database unavailable.');
$model = new Schedule($conn);
$schedules = $conn->query("SELECT s.schedule_id,
    EXISTS(SELECT 1 FROM appointments a WHERE a.schedule_id = s.schedule_id) AS has_appointments,
    EXISTS(SELECT 1 FROM appointment_reschedule_requests r
        WHERE r.original_schedule_id = s.schedule_id OR r.target_schedule_id = s.schedule_id) AS has_reschedules
    FROM schedules s")->fetchAll(PDO::FETCH_ASSOC);
if (!$schedules) throw new RuntimeException('No schedules available to verify.');
foreach ($schedules as $schedule) {
    $expected = $schedule['has_appointments']
        ? 'Schedules with appointment records cannot be deleted.'
        : ($schedule['has_reschedules'] ? 'Schedules linked to reschedule requests cannot be deleted.' : null);
    if ($model->getDeletionBlockReason($schedule['schedule_id']) !== $expected) {
        throw new RuntimeException('Incorrect deletion guard for schedule ' . $schedule['schedule_id']);
    }
}
echo 'PASS: Reference guards match all ' . count($schedules) . " schedules; no data was changed.\n";
