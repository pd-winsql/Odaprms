<?php

require_once __DIR__ . '/auditLogModel.php';
require_once __DIR__ . '/emailNotificationModel.php';
require_once __DIR__ . '/../helpers/bookingPolicy.php';

final class RescheduleModel
{
    public const APPROVAL_WINDOW_HOURS = 24;
    public const FIRST_REMINDER_HOURS = 8;
    public const SECOND_REMINDER_HOURS = 16;

    private PDO $conn;
    private AuditLog $auditLog;
    private EmailNotificationModel $emailNotifications;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
        $this->emailNotifications = new EmailNotificationModel($conn);
    }

    public function submitRequest(int $appointmentId, int $targetScheduleId, string $reason, int $userId): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            return ['success' => false, 'message' => 'Please provide a reschedule reason between 5 and 500 characters.'];
        }

        $this->expirePendingRequests();
        try {
            $this->conn->beginTransaction();
            $appointmentStmt = $this->conn->prepare("SELECT a.appointment_id, a.patient_id, a.schedule_id,
                    a.clinic_id, a.date, a.status
                FROM appointments a
                INNER JOIN patients p ON p.patient_id = a.patient_id
                WHERE a.appointment_id = :appointment_id AND p.user_id = :user_id
                FOR UPDATE");
            $appointmentStmt->execute([':appointment_id' => $appointmentId, ':user_id' => $userId]);
            $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$appointment || $appointment['status'] !== 'Confirmed') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only your confirmed appointments can be rescheduled.'];
            }

            $pendingStmt = $this->conn->prepare("SELECT request_id FROM appointment_reschedule_requests
                WHERE appointment_id = :appointment_id AND status = 'Pending' AND expires_at > NOW()
                LIMIT 1 FOR UPDATE");
            $pendingStmt->execute([':appointment_id' => $appointmentId]);
            if ($pendingStmt->fetchColumn()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This appointment already has a pending reschedule request.'];
            }

            $scheduleStmt = $this->conn->prepare("SELECT schedule_id, clinic_id, sched_date, start_time,
                    end_time, max_appointments
                FROM schedules WHERE schedule_id = :schedule_id FOR UPDATE");
            $scheduleStmt->execute([':schedule_id' => $targetScheduleId]);
            $target = $scheduleStmt->fetch(PDO::FETCH_ASSOC);
            if (!$target || (int) $target['schedule_id'] === (int) $appointment['schedule_id']) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Please select a different available schedule.'];
            }

            $targetStart = new DateTimeImmutable($target['sched_date'] . ' ' . $target['start_time']);
            if ($targetStart <= new DateTimeImmutable()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'That schedule has already started.'];
            }

            $leadDays = BookingPolicy::minimumRescheduleLeadDays($this->conn);
            $policy = BookingPolicy::assessDate($target['sched_date'], $leadDays);
            if (!$policy['eligible']) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => str_replace('Appointments must be booked', 'Reschedule requests must be submitted', $policy['message'])];
            }

            $sameDay = $this->conn->prepare("SELECT appointment_id FROM appointments
                WHERE patient_id = :patient_id AND appointment_id != :appointment_id AND date = :target_date
                AND status IN ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed')
                LIMIT 1");
            $sameDay->execute([
                ':patient_id' => $appointment['patient_id'],
                ':appointment_id' => $appointmentId,
                ':target_date' => $target['sched_date'],
            ]);
            if ($sameDay->fetchColumn()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'You already have another appointment on that date.'];
            }

            if (!$this->hasCapacity($targetScheduleId, (int) $target['max_appointments'])) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'That schedule no longer has an available slot.'];
            }

            $approvalDeadline = (new DateTimeImmutable())->modify('+' . self::APPROVAL_WINDOW_HOURS . ' hours');
            if ($targetStart < $approvalDeadline) {
                $approvalDeadline = $targetStart;
            }
            if ($approvalDeadline <= new DateTimeImmutable()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'That schedule is too close to hold for review.'];
            }

            $insert = $this->conn->prepare("INSERT INTO appointment_reschedule_requests (
                    appointment_id, requested_by_user_id, original_schedule_id, original_clinic_id,
                    original_date, target_schedule_id, target_clinic_id, target_date, reason,
                    lead_days_snapshot, expires_at
                ) VALUES (
                    :appointment_id, :user_id, :original_schedule_id, :original_clinic_id,
                    :original_date, :target_schedule_id, :target_clinic_id, :target_date, :reason,
                    :lead_days_snapshot, :expires_at
                )");
            $insert->execute([
                ':appointment_id' => $appointmentId,
                ':user_id' => $userId,
                ':original_schedule_id' => $appointment['schedule_id'],
                ':original_clinic_id' => $appointment['clinic_id'],
                ':original_date' => $appointment['date'],
                ':target_schedule_id' => $targetScheduleId,
                ':target_clinic_id' => $target['clinic_id'],
                ':target_date' => $target['sched_date'],
                ':reason' => $reason,
                ':lead_days_snapshot' => $leadDays,
                ':expires_at' => $approvalDeadline->format('Y-m-d H:i:s'),
            ]);
            $requestId = (int) $this->conn->lastInsertId();
            $audit = $this->auditLog->recordForUser(
                'appointment', $appointmentId, 'reschedule_requested',
                "Requested a new schedule for appointment #{$appointmentId}.",
                ['schedule_id' => (int) $appointment['schedule_id'], 'clinic_id' => (int) $appointment['clinic_id'], 'date' => $appointment['date']],
                ['request_id' => $requestId, 'schedule_id' => $targetScheduleId, 'clinic_id' => (int) $target['clinic_id'], 'date' => $target['sched_date'], 'expires_at' => $approvalDeadline->format('Y-m-d H:i:s')],
                $userId
            );
            $notification = $this->emailNotifications->enqueueAppointmentTemplate(
                $appointmentId, 'reschedule_requested', 'Pending', "reschedule:{$requestId}:requested",
                [
                    '{requested_schedule}' => $this->formatSchedule($target),
                    '{approval_deadline}' => $approvalDeadline->format('F j, Y g:i A'),
                ]
            );
            $this->conn->commit();
            return [
                'success' => true,
                'message' => 'Reschedule request sent for clinic approval.',
                'request_id' => $requestId,
                'expires_at' => $approvalDeadline->format(DATE_ATOM),
                'audit' => $audit,
                'notification' => $notification,
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('submitRequest error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to submit the reschedule request. Please try again.'];
        }
    }

    public function approveRequest(int $requestId, int $userId): array
    {
        $this->expirePendingRequests();
        try {
            $this->conn->beginTransaction();
            $request = $this->lockRequest($requestId);
            if (!$request || $request['status'] !== 'Pending' || strtotime($request['expires_at']) <= time()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This reschedule request is no longer available for approval.'];
            }
            $appointmentStmt = $this->conn->prepare('SELECT * FROM appointments WHERE appointment_id = :id FOR UPDATE');
            $appointmentStmt->execute([':id' => $request['appointment_id']]);
            $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$appointment || $appointment['status'] !== 'Confirmed'
                || (int) $appointment['schedule_id'] !== (int) $request['original_schedule_id']) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'The original appointment changed and this request can no longer be approved.'];
            }
            $scheduleStmt = $this->conn->prepare('SELECT * FROM schedules WHERE schedule_id = :id FOR UPDATE');
            $scheduleStmt->execute([':id' => $request['target_schedule_id']]);
            $target = $scheduleStmt->fetch(PDO::FETCH_ASSOC);
            if (!$target || strtotime($target['sched_date'] . ' ' . $target['start_time']) <= time()
                || !$this->hasCapacity((int) $target['schedule_id'], (int) $target['max_appointments'], $requestId)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'The requested schedule is no longer valid or available.'];
            }
            $sameDay = $this->conn->prepare("SELECT appointment_id FROM appointments
                WHERE patient_id = :patient_id AND appointment_id != :appointment_id AND date = :target_date
                AND status IN ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed')
                LIMIT 1");
            $sameDay->execute([
                ':patient_id' => $appointment['patient_id'],
                ':appointment_id' => $appointment['appointment_id'],
                ':target_date' => $target['sched_date'],
            ]);
            if ($sameDay->fetchColumn()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'The patient now has another appointment on the requested date.'];
            }

            $updateAppointment = $this->conn->prepare('UPDATE appointments
                SET schedule_id = :schedule_id, clinic_id = :clinic_id, date = :date
                WHERE appointment_id = :appointment_id');
            $updateAppointment->execute([
                ':schedule_id' => $target['schedule_id'], ':clinic_id' => $target['clinic_id'],
                ':date' => $target['sched_date'], ':appointment_id' => $appointment['appointment_id'],
            ]);
            $resolve = $this->conn->prepare("UPDATE appointment_reschedule_requests
                SET status = 'Approved', reviewed_by_user_id = :user_id, reviewed_at = NOW(), resolved_at = NOW()
                WHERE request_id = :request_id AND status = 'Pending'");
            $resolve->execute([':user_id' => $userId, ':request_id' => $requestId]);
            $audit = $this->auditLog->recordForUser(
                'appointment', (int) $appointment['appointment_id'], 'reschedule_approved',
                "Approved reschedule request #{$requestId} for appointment #{$appointment['appointment_id']}.",
                ['schedule_id' => (int) $appointment['schedule_id'], 'clinic_id' => (int) $appointment['clinic_id'], 'date' => $appointment['date']],
                ['request_id' => $requestId, 'schedule_id' => (int) $target['schedule_id'], 'clinic_id' => (int) $target['clinic_id'], 'date' => $target['sched_date']],
                $userId
            );
            $notification = $this->emailNotifications->enqueueAppointmentTemplate(
                (int) $appointment['appointment_id'], 'reschedule_approved', 'Approved', "reschedule:{$requestId}:approved"
            );
            $this->conn->commit();
            return ['success' => true, 'message' => 'Reschedule approved. The appointment schedule has been updated.', 'audit' => $audit, 'notification' => $notification];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('approveRequest error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to approve the reschedule request.'];
        }
    }

    public function rejectRequest(int $requestId, string $reason, int $userId): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            return ['success' => false, 'message' => 'Please provide a rejection reason between 3 and 500 characters.'];
        }
        return $this->resolveWithoutScheduleChange($requestId, 'Rejected', $reason, $userId);
    }

    public function withdrawRequest(int $requestId, int $userId): array
    {
        $this->expirePendingRequests();
        try {
            $this->conn->beginTransaction();
            $request = $this->lockRequest($requestId);
            if (!$request || $request['status'] !== 'Pending') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This reschedule request is no longer pending.'];
            }
            $owner = $this->conn->prepare('SELECT 1 FROM appointments a JOIN patients p ON p.patient_id = a.patient_id
                WHERE a.appointment_id = :appointment_id AND p.user_id = :user_id');
            $owner->execute([':appointment_id' => $request['appointment_id'], ':user_id' => $userId]);
            if (!$owner->fetchColumn()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'You cannot withdraw this request.'];
            }
            $update = $this->conn->prepare("UPDATE appointment_reschedule_requests
                SET status = 'Withdrawn', resolved_at = NOW() WHERE request_id = :request_id AND status = 'Pending'");
            $update->execute([':request_id' => $requestId]);
            $audit = $this->auditLog->recordForUser(
                'appointment', (int) $request['appointment_id'], 'reschedule_withdrawn',
                "Withdrew reschedule request #{$requestId}.", null, ['request_id' => $requestId, 'status' => 'Withdrawn'], $userId
            );
            $notification = $this->emailNotifications->enqueueAppointmentTemplate(
                (int) $request['appointment_id'], 'reschedule_withdrawn', 'Withdrawn', "reschedule:{$requestId}:withdrawn"
            );
            $this->conn->commit();
            return ['success' => true, 'message' => 'Reschedule request withdrawn. Your original appointment is unchanged.', 'audit' => $audit, 'notification' => $notification];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('withdrawRequest error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to withdraw the reschedule request.'];
        }
    }

    public function getPatientRequests(int $userId): array
    {
        $this->expirePendingRequests();
        $stmt = $this->conn->prepare($this->requestSelect() . " WHERE p.user_id = :user_id
            ORDER BY r.created_at DESC, r.request_id DESC LIMIT 50");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingRequestsForStaff(): array
    {
        $this->expirePendingRequests();
        $stmt = $this->conn->query($this->requestSelect() . " WHERE r.status = 'Pending' AND r.expires_at > NOW()
            ORDER BY r.expires_at ASC, r.request_id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStaffNotificationEvents(): array
    {
        $this->expirePendingRequests();
        $stmt = $this->conn->query($this->requestSelect() . " WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY r.request_id DESC LIMIT 50");
        $events = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $patientName = trim($row['patient_firstname'] . ' ' . $row['patient_lastname']);
            $base = [
                'request_id' => (int) $row['request_id'],
                'appointment_id' => (int) $row['appointment_id'],
                'destination' => 'appointment-content.php',
            ];
            if ($row['status'] === 'Pending') {
                $events[] = $base + [
                    'id' => "reschedule:{$row['request_id']}:submitted",
                    'type' => 'reschedule_requested',
                    'message' => "{$patientName} requested a new appointment schedule.",
                    'created_at' => $row['created_at'],
                ];
            }
            $created = strtotime($row['created_at']);
            if ($row['status'] === 'Pending' && time() >= $created + self::FIRST_REMINDER_HOURS * 3600) {
                $events[] = $base + [
                    'id' => "reschedule:{$row['request_id']}:8h", 'type' => 'reschedule_reminder',
                    'message' => "Reschedule request for {$patientName} has been waiting 8 hours.",
                    'created_at' => date('Y-m-d H:i:s', $created + self::FIRST_REMINDER_HOURS * 3600),
                ];
            }
            if ($row['status'] === 'Pending' && time() >= $created + self::SECOND_REMINDER_HOURS * 3600) {
                $events[] = $base + [
                    'id' => "reschedule:{$row['request_id']}:16h", 'type' => 'reschedule_urgent',
                    'message' => "Reschedule request for {$patientName} expires in about 8 hours.",
                    'created_at' => date('Y-m-d H:i:s', $created + self::SECOND_REMINDER_HOURS * 3600),
                ];
            }
            if ($row['status'] === 'Expired') {
                $events[] = $base + [
                    'id' => "reschedule:{$row['request_id']}:expired", 'type' => 'reschedule_expired',
                    'message' => "Reschedule request for {$patientName} expired without review.",
                    'created_at' => $row['resolved_at'] ?: $row['updated_at'],
                ];
            }
        }
        usort($events, static fn(array $a, array $b) => strcmp($a['created_at'], $b['created_at']));
        return array_slice($events, -50);
    }

    public function getPatientNotificationSnapshot(int $userId): array
    {
        return array_map(static function (array $row): array {
            return [
                'request_id' => (int) $row['request_id'],
                'appointment_id' => (int) $row['appointment_id'],
                'status' => $row['status'],
                'target_date' => $row['target_date'],
                'clinic_name' => $row['target_clinic_name'],
                'rejection_reason' => $row['rejection_reason'],
                'expires_at' => $row['expires_at'],
                'state_changed_at' => $row['resolved_at'] ?: $row['updated_at'],
            ];
        }, $this->getPatientRequests($userId));
    }

    public function expirePendingRequests(): int
    {
        try {
            $stmt = $this->conn->query("SELECT request_id FROM appointment_reschedule_requests
                WHERE status = 'Pending' AND expires_at <= NOW() ORDER BY request_id LIMIT 100");
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $expired = 0;
            foreach ($ids as $requestId) {
                $ownsTransaction = !$this->conn->inTransaction();
                if ($ownsTransaction) $this->conn->beginTransaction();
                $request = $this->lockRequest($requestId);
                if (!$request || $request['status'] !== 'Pending' || strtotime($request['expires_at']) > time()) {
                    if ($ownsTransaction) $this->conn->commit();
                    continue;
                }
                $update = $this->conn->prepare("UPDATE appointment_reschedule_requests SET status = 'Expired', resolved_at = NOW()
                    WHERE request_id = :request_id AND status = 'Pending'");
                $update->execute([':request_id' => $requestId]);
                $this->auditLog->record(
                    'appointment', (int) $request['appointment_id'], 'reschedule_expired',
                    "Expired reschedule request #{$requestId} after its approval window.", null,
                    ['request_id' => $requestId, 'status' => 'Expired'],
                    ['user_id' => null, 'name' => 'System', 'role' => 'System', 'source' => 'System']
                );
                $this->emailNotifications->enqueueAppointmentTemplate(
                    (int) $request['appointment_id'], 'reschedule_expired', 'Expired', "reschedule:{$requestId}:expired",
                    ['{requested_schedule}' => $this->formatRequestTarget($request)]
                );
                if ($ownsTransaction) $this->conn->commit();
                $expired++;
            }
            return $expired;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('expirePendingRequests error: ' . $e->getMessage());
            return 0;
        }
    }

    private function resolveWithoutScheduleChange(int $requestId, string $status, string $reason, int $userId): array
    {
        $this->expirePendingRequests();
        try {
            $this->conn->beginTransaction();
            $request = $this->lockRequest($requestId);
            if (!$request || $request['status'] !== 'Pending') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This reschedule request is no longer pending.'];
            }
            $update = $this->conn->prepare("UPDATE appointment_reschedule_requests
                SET status = :status, reviewed_by_user_id = :user_id, reviewed_at = NOW(),
                    rejection_reason = :reason, resolved_at = NOW()
                WHERE request_id = :request_id AND status = 'Pending'");
            $update->execute([':status' => $status, ':user_id' => $userId, ':reason' => $reason, ':request_id' => $requestId]);
            $audit = $this->auditLog->recordForUser(
                'appointment', (int) $request['appointment_id'], 'reschedule_rejected',
                "Rejected reschedule request #{$requestId}.", null,
                ['request_id' => $requestId, 'status' => $status, 'reason' => $reason], $userId
            );
            $notification = $this->emailNotifications->enqueueAppointmentTemplate(
                (int) $request['appointment_id'], 'reschedule_rejected', $reason, "reschedule:{$requestId}:rejected",
                ['{requested_schedule}' => $this->formatRequestTarget($request)]
            );
            $this->conn->commit();
            return ['success' => true, 'message' => 'Reschedule request rejected. The original appointment is unchanged.', 'audit' => $audit, 'notification' => $notification];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('resolveWithoutScheduleChange error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to reject the reschedule request.'];
        }
    }

    private function lockRequest(int $requestId): array|false
    {
        $stmt = $this->conn->prepare('SELECT r.*, s.start_time AS target_start_time, s.end_time AS target_end_time,
                c.clinic_name AS target_clinic_name
            FROM appointment_reschedule_requests r
            JOIN schedules s ON s.schedule_id = r.target_schedule_id
            JOIN clinics c ON c.clinic_id = r.target_clinic_id
            WHERE r.request_id = :request_id FOR UPDATE');
        $stmt->execute([':request_id' => $requestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function hasCapacity(int $scheduleId, int $capacity, ?int $excludeRequestId = null): bool
    {
        $appointments = $this->conn->prepare("SELECT COUNT(*) FROM appointments WHERE schedule_id = :schedule_id
            AND status IN ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed')");
        $appointments->execute([':schedule_id' => $scheduleId]);
        $holdSql = "SELECT COUNT(*) FROM appointment_reschedule_requests
            WHERE target_schedule_id = :schedule_id AND status = 'Pending' AND expires_at > NOW()";
        $params = [':schedule_id' => $scheduleId];
        if ($excludeRequestId !== null) {
            $holdSql .= ' AND request_id != :exclude_request_id';
            $params[':exclude_request_id'] = $excludeRequestId;
        }
        $holds = $this->conn->prepare($holdSql);
        $holds->execute($params);
        return (int) $appointments->fetchColumn() + (int) $holds->fetchColumn() < $capacity;
    }

    private function requestSelect(): string
    {
        return "SELECT r.*, a.status AS appointment_status, a.appointment_code,
                p.firstname AS patient_firstname, p.lastname AS patient_lastname,
                original_clinic.clinic_name AS original_clinic_name,
                target_clinic.clinic_name AS target_clinic_name,
                original_schedule.start_time AS original_start_time,
                original_schedule.end_time AS original_end_time,
                target_schedule.start_time AS target_start_time,
                target_schedule.end_time AS target_end_time
            FROM appointment_reschedule_requests r
            JOIN appointments a ON a.appointment_id = r.appointment_id
            JOIN patients p ON p.patient_id = a.patient_id
            JOIN clinics original_clinic ON original_clinic.clinic_id = r.original_clinic_id
            JOIN clinics target_clinic ON target_clinic.clinic_id = r.target_clinic_id
            JOIN schedules original_schedule ON original_schedule.schedule_id = r.original_schedule_id
            JOIN schedules target_schedule ON target_schedule.schedule_id = r.target_schedule_id";
    }

    private function formatSchedule(array $schedule): string
    {
        $clinic = $schedule['clinic_name'] ?? $this->clinicName((int) $schedule['clinic_id']);
        return sprintf('%s — %s, %s–%s.', $clinic, date('F j, Y', strtotime($schedule['sched_date'])),
            date('g:i A', strtotime($schedule['start_time'])), date('g:i A', strtotime($schedule['end_time'])));
    }

    private function formatRequestTarget(array $request): string
    {
        return sprintf('%s — %s, %s–%s.', $request['target_clinic_name'],
            date('F j, Y', strtotime($request['target_date'])), date('g:i A', strtotime($request['target_start_time'])),
            date('g:i A', strtotime($request['target_end_time'])));
    }

    private function clinicName(int $clinicId): string
    {
        $stmt = $this->conn->prepare('SELECT clinic_name FROM clinics WHERE clinic_id = :id');
        $stmt->execute([':id' => $clinicId]);
        return (string) ($stmt->fetchColumn() ?: 'Clinic');
    }
}
