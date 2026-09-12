<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/rescheduleModel.php';
require_once __DIR__ . '/../apps/models/scheduleModel.php';

function rescheduleExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$model = new RescheduleModel($conn);
$schedules = new Schedule($conn);
$appointmentId = 0;
$scheduleIds = [];
$originalSettings = $conn->query('SELECT minimum_booking_lead_days, minimum_reschedule_lead_days FROM site_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

try {
    $patient = $conn->query("SELECT patient_id, user_id FROM patients WHERE user_id IS NOT NULL ORDER BY patient_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $staffUserId = (int) $conn->query("SELECT id FROM users WHERE user_role = 'Dental Assistant' ORDER BY id LIMIT 1")->fetchColumn();
    $clinics = $conn->query('SELECT clinic_id FROM clinics ORDER BY clinic_id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    $serviceId = (int) $conn->query('SELECT service_id FROM services WHERE is_active = 1 ORDER BY service_id LIMIT 1')->fetchColumn();
    rescheduleExpect($patient && $staffUserId > 0 && count($clinics) === 2 && $serviceId > 0, 'Workflow fixtures are available.');

    $conn->exec('UPDATE site_settings SET minimum_booking_lead_days = 7, minimum_reschedule_lead_days = 3 WHERE id = 1');
    $base = new DateTimeImmutable('+60 days');
    $dates = [$base->format('Y-m-d'), $base->modify('+1 day')->format('Y-m-d'), $base->modify('+2 days')->format('Y-m-d')];
    $insertSchedule = $conn->prepare("INSERT INTO schedules (clinic_id, sched_date, start_time, end_time, max_appointments)
        VALUES (:clinic_id, :sched_date, '09:00:00', '12:00:00', 2)");
    foreach ($dates as $index => $date) {
        $insertSchedule->execute([':clinic_id' => $clinics[$index % 2], ':sched_date' => $date]);
        $scheduleIds[] = (int) $conn->lastInsertId();
    }

    $code = 'TEST-' . strtoupper(bin2hex(random_bytes(4)));
    $appointment = $conn->prepare("INSERT INTO appointments
        (patient_id, schedule_id, clinic_id, date, status, deposit_required, appointment_code, confirmed_at)
        VALUES (:patient_id, :schedule_id, :clinic_id, :date, 'Confirmed', 1, :code, NOW())");
    $appointment->execute([
        ':patient_id' => $patient['patient_id'], ':schedule_id' => $scheduleIds[0],
        ':clinic_id' => $clinics[0], ':date' => $dates[0], ':code' => $code,
    ]);
    $appointmentId = (int) $conn->lastInsertId();
    $conn->prepare('INSERT INTO appointment_services (appointment_id, service_id) VALUES (:appointment_id, :service_id)')
        ->execute([':appointment_id' => $appointmentId, ':service_id' => $serviceId]);
    $conn->prepare("INSERT INTO appointment_deposits (appointment_id, amount, status, verified_by_user_id, verified_at)
        VALUES (:appointment_id, 400, 'Verified', :staff_id, NOW())")
        ->execute([':appointment_id' => $appointmentId, ':staff_id' => $staffUserId]);
    $depositId = (int) $conn->lastInsertId();

    $beforeAvailability = array_column($schedules->getAvailableSchedulesByClinic((int) $clinics[1], $dates[1]), 'available_slots', 'schedule_id');
    $beforeSlots = (int) ($beforeAvailability[$scheduleIds[1]] ?? -1);
    $submitted = $model->submitRequest($appointmentId, $scheduleIds[1], 'A different clinic date is more accessible.', (int) $patient['user_id']);
    rescheduleExpect($submitted['success'] ?? false, 'A patient can submit a replacement schedule from another clinic.');
    $requestId = (int) $submitted['request_id'];
    $request = $conn->query("SELECT * FROM appointment_reschedule_requests WHERE request_id = {$requestId}")->fetch(PDO::FETCH_ASSOC);
    rescheduleExpect($request['status'] === 'Pending' && (int) $request['lead_days_snapshot'] === 3, 'The pending request captures the active lead-time policy.');

    $afterAvailability = array_column($schedules->getAvailableSchedulesByClinic((int) $clinics[1], $dates[1]), 'available_slots', 'schedule_id');
    $afterSlots = (int) ($afterAvailability[$scheduleIds[1]] ?? -1);
    rescheduleExpect($afterSlots === $beforeSlots - 1, 'A pending request reserves one target slot.');
    $duplicate = $model->submitRequest($appointmentId, $scheduleIds[2], 'Trying to create a second pending request.', (int) $patient['user_id']);
    rescheduleExpect(!($duplicate['success'] ?? false), 'Only one active reschedule request is allowed per appointment.');

    $conn->exec("UPDATE appointment_reschedule_requests SET created_at = DATE_SUB(NOW(), INTERVAL 17 HOUR) WHERE request_id = {$requestId}");
    $eventIds = array_column($model->getStaffNotificationEvents(), 'id');
    rescheduleExpect(in_array("reschedule:{$requestId}:8h", $eventIds, true) && in_array("reschedule:{$requestId}:16h", $eventIds, true), 'Staff reminders become due at 8 and 16 hours.');

    $approved = $model->approveRequest($requestId, $staffUserId);
    rescheduleExpect($approved['success'] ?? false, 'A Dental Assistant can approve an available held schedule.');
    $updated = $conn->query("SELECT schedule_id, clinic_id, date, appointment_code FROM appointments WHERE appointment_id = {$appointmentId}")->fetch(PDO::FETCH_ASSOC);
    rescheduleExpect((int) $updated['schedule_id'] === $scheduleIds[1] && $updated['appointment_code'] === $code, 'Approval updates the same appointment and preserves its code.');
    rescheduleExpect((int) $conn->query("SELECT COUNT(*) FROM appointment_services WHERE appointment_id = {$appointmentId}")->fetchColumn() === 1
        && (int) $conn->query("SELECT deposit_id FROM appointment_deposits WHERE appointment_id = {$appointmentId}")->fetchColumn() === $depositId,
        'Services and the verified deposit remain attached after approval.');

    $second = $model->submitRequest($appointmentId, $scheduleIds[2], 'I need the following day instead.', (int) $patient['user_id']);
    rescheduleExpect($second['success'] ?? false, 'A later request can be submitted after resolution.');
    $withdrawn = $model->withdrawRequest((int) $second['request_id'], (int) $patient['user_id']);
    rescheduleExpect($withdrawn['success'] ?? false, 'The patient can withdraw a pending request without changing the appointment.');

    $third = $model->submitRequest($appointmentId, $scheduleIds[2], 'Please consider the alternate schedule.', (int) $patient['user_id']);
    $rejected = $model->rejectRequest((int) $third['request_id'], 'The clinic cannot support that window.', $staffUserId);
    rescheduleExpect($rejected['success'] ?? false, 'Staff can reject with a patient-visible reason.');

    $fourth = $model->submitRequest($appointmentId, $scheduleIds[2], 'One last request for expiration coverage.', (int) $patient['user_id']);
    $conn->exec("UPDATE appointment_reschedule_requests SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE request_id = " . (int) $fourth['request_id']);
    rescheduleExpect($model->expirePendingRequests() === 1, 'The server expires an unreviewed request after its deadline.');
    rescheduleExpect($conn->query("SELECT status FROM appointment_reschedule_requests WHERE request_id = " . (int) $fourth['request_id'])->fetchColumn() === 'Expired', 'Expired requests release their holds and keep history.');
} finally {
    if ($appointmentId > 0) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type = 'appointment' AND entity_id = :id")->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointment_deposits WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointment_services WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
        $conn->prepare('DELETE FROM appointments WHERE appointment_id = :id')->execute([':id' => $appointmentId]);
    }
    foreach (array_reverse($scheduleIds) as $scheduleId) {
        $conn->prepare('DELETE FROM schedules WHERE schedule_id = :id')->execute([':id' => $scheduleId]);
    }
    $restore = $conn->prepare('UPDATE site_settings SET minimum_booking_lead_days = :booking, minimum_reschedule_lead_days = :reschedule WHERE id = 1');
    $restore->execute([':booking' => $originalSettings['minimum_booking_lead_days'], ':reschedule' => $originalSettings['minimum_reschedule_lead_days']]);
}

echo "Reschedule workflow test completed.\n";
