<?php

require_once __DIR__ . '/auditLogModel.php';

class LogbookModel {
    private $conn;
    private $auditLog;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
    }

    private function baseLogbookQuery(): string {
        return "
            SELECT
                a.appointment_id,
                a.patient_id,
                a.date,
                a.status AS appointment_status,
                a.firstname,
                a.lastname,
                a.email,
                a.profile_completed_at,
                a.clinic_name,
                ci.checkin_id,
                ci.arrived_at,
                ci.checkin_status,
                ci.profile_required_at_arrival,
                ci.ready_at,
                ci.queue_status,
                ci.queue_priority,
                ci.queue_entered_at,
                ci.queue_reason,
                ci.queue_updated_at,
                payment.verified_deposit,
                payment.billing_id,
                payment.actual_service_amount,
                payment.deposit_applied,
                payment.remaining_balance,
                payment.cash_received,
                payment.payment_status,
                payment.billing_recorded_at,
                payment.billing_notes,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', st.firstname, st.middlename, st.lastname)), ''),
                    u.email,
                    '—'
                ) AS checked_in_by,
                a.service_name
            FROM vw_appointment_overview a
            LEFT JOIN appointment_checkins ci ON ci.appointment_id = a.appointment_id
            LEFT JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
            LEFT JOIN users u ON u.id = ci.checked_in_by_user_id
            LEFT JOIN staffs st ON st.user_id = u.id
        ";
    }

    public function getForDate($date): array {
        $stmt = $this->conn->prepare($this->baseLogbookQuery() . "
            WHERE a.date = :date
              AND (
                a.deposit_required = 0
                OR payment.deposit_status IN ('Verified', 'Transferred')
              )
              AND a.status IN ('Confirmed', 'Checked In', 'In Progress', 'Completed', 'No-show', 'Cancelled')
            ORDER BY
                CASE
                    WHEN a.status = 'In Progress' THEN 0
                    WHEN a.status = 'Checked In' AND ci.checkin_status = 'Ready' AND ci.queue_status = 'Waiting' THEN 1
                    WHEN a.status = 'Checked In' AND ci.checkin_status = 'Profile Required' THEN 2
                    WHEN a.status = 'Checked In' AND ci.queue_status = 'Deferred' THEN 3
                    WHEN ci.arrived_at IS NOT NULL THEN 4
                    ELSE 5
                END,
                CASE WHEN ci.queue_priority = 'Emergency' THEN 0 ELSE 1 END,
                COALESCE(ci.queue_entered_at, ci.arrived_at, a.created_at) ASC,
                ci.arrived_at ASC,
                a.created_at ASC
        ");
        $stmt->execute([':date' => $date]);
        return $this->annotateQueue($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getToday(): array {
        return $this->getForDate(date('Y-m-d'));
    }

    private function annotateQueue(array $rows): array {
        $position = 0;
        foreach ($rows as &$row) {
            $eligible = $row['appointment_status'] === 'Checked In'
                && $row['checkin_status'] === 'Ready'
                && $row['queue_status'] === 'Waiting';
            $row['queue_position'] = $eligible ? ++$position : null;
            $row['is_next'] = $eligible && $position === 1;
            $row['is_in_treatment'] = $row['appointment_status'] === 'In Progress';
        }
        unset($row);
        return $rows;
    }

    private function getNextReadyAppointmentId(bool $forUpdate = false): ?int {
        $sql = "
            SELECT a.appointment_id
            FROM appointments a
            JOIN appointment_checkins ci ON ci.appointment_id = a.appointment_id
            JOIN patients p ON p.patient_id = a.patient_id
            WHERE a.date = CURDATE()
              AND a.status = 'Checked In'
              AND p.profile_status = 'Complete'
              AND ci.checkin_status = 'Ready'
              AND ci.queue_status = 'Waiting'
            ORDER BY
              CASE WHEN ci.queue_priority = 'Emergency' THEN 0 ELSE 1 END,
              COALESCE(ci.queue_entered_at, ci.arrived_at) ASC,
              ci.arrived_at ASC,
              ci.checkin_id ASC
            LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
        $value = $this->conn->query($sql)->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    public function getNextPatient(): ?array {
        foreach ($this->getToday() as $row) {
            if ($row['is_next']) return $row;
        }
        return null;
    }

    private function queueRecordForUpdate(int $appointmentId): ?array {
        $stmt = $this->conn->prepare("
            SELECT a.appointment_id, a.status AS appointment_status, a.date,
                   p.firstname, p.lastname, p.profile_status,
                   ci.checkin_status, ci.queue_status, ci.queue_priority
            FROM appointments a
            JOIN patients p ON p.patient_id = a.patient_id
            JOIN appointment_checkins ci ON ci.appointment_id = a.appointment_id
            WHERE a.appointment_id = :appointment_id
            FOR UPDATE
        ");
        $stmt->execute([':appointment_id' => $appointmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function validateQueueReason(string $reason): ?string {
        $reason = trim($reason);
        if (strlen($reason) < 3) return null;
        return substr($reason, 0, 255);
    }

    public function deferNextPatient(int $appointmentId, int $userId, string $reason): array {
        $reason = $this->validateQueueReason($reason);
        if ($reason === null) return ['success' => false, 'message' => 'A short reason is required to defer the patient.'];

        try {
            $this->conn->beginTransaction();
            $record = $this->queueRecordForUpdate($appointmentId);
            if (!$record || $record['date'] !== date('Y-m-d') || $record['appointment_status'] !== 'Checked In'
                || $record['checkin_status'] !== 'Ready' || $record['queue_status'] !== 'Waiting') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only a ready patient in today\'s active queue can be deferred.'];
            }
            if ($this->getNextReadyAppointmentId(true) !== $appointmentId) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only the next patient can be deferred.'];
            }

            $this->conn->prepare("
                UPDATE appointment_checkins
                SET queue_status = 'Deferred', queue_reason = :reason,
                    queue_updated_by_user_id = :user_id, queue_updated_at = NOW()
                WHERE appointment_id = :appointment_id
            ")->execute([':reason' => $reason, ':user_id' => $userId, ':appointment_id' => $appointmentId]);

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Staff account not found.');
            $this->auditLog->record('appointment', $appointmentId, 'queue_deferred',
                "Deferred appointment #{$appointmentId} in the patient queue.",
                ['queue_status' => 'Waiting'], ['queue_status' => 'Deferred', 'reason' => $reason], $actor);
            $this->conn->commit();
            return ['success' => true, 'message' => 'Patient placed on hold. The next ready patient is now first in queue.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('deferNextPatient error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to defer the patient.'];
        }
    }

    public function returnToQueue(int $appointmentId, int $userId): array {
        try {
            $this->conn->beginTransaction();
            $record = $this->queueRecordForUpdate($appointmentId);
            if (!$record || $record['date'] !== date('Y-m-d') || $record['appointment_status'] !== 'Checked In'
                || $record['queue_status'] !== 'Deferred') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only a deferred patient from today can return to the queue.'];
            }

            $this->conn->prepare("
                UPDATE appointment_checkins
                SET queue_status = 'Waiting', queue_priority = 'Normal', queue_entered_at = NOW(),
                    queue_reason = NULL, queue_updated_by_user_id = :user_id, queue_updated_at = NOW()
                WHERE appointment_id = :appointment_id
            ")->execute([':user_id' => $userId, ':appointment_id' => $appointmentId]);

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Staff account not found.');
            $this->auditLog->record('appointment', $appointmentId, 'queue_returned',
                "Returned appointment #{$appointmentId} to the patient queue.",
                ['queue_status' => 'Deferred'], ['queue_status' => 'Waiting', 'queue_priority' => 'Normal'], $actor);
            $this->conn->commit();
            return ['success' => true, 'message' => 'Patient returned to the end of the normal queue.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('returnToQueue error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to return the patient to the queue.'];
        }
    }

    public function prioritizeEmergency(int $appointmentId, int $userId, string $reason): array {
        $reason = $this->validateQueueReason($reason);
        if ($reason === null) return ['success' => false, 'message' => 'An emergency reason is required.'];

        try {
            $this->conn->beginTransaction();
            $record = $this->queueRecordForUpdate($appointmentId);
            if (!$record || $record['date'] !== date('Y-m-d') || $record['appointment_status'] !== 'Checked In'
                || $record['checkin_status'] !== 'Ready' || $record['queue_status'] !== 'Waiting') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only a ready patient in today\'s queue can receive emergency priority.'];
            }
            if ($record['queue_priority'] === 'Emergency') {
                $this->conn->rollBack();
                return ['success' => true, 'changed' => false, 'message' => 'This patient already has emergency priority.'];
            }

            $this->conn->prepare("
                UPDATE appointment_checkins
                SET queue_priority = 'Emergency', queue_reason = :reason,
                    queue_updated_by_user_id = :user_id, queue_updated_at = NOW()
                WHERE appointment_id = :appointment_id
            ")->execute([':reason' => $reason, ':user_id' => $userId, ':appointment_id' => $appointmentId]);

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Staff account not found.');
            $this->auditLog->record('appointment', $appointmentId, 'queue_emergency_priority',
                "Gave appointment #{$appointmentId} emergency queue priority.",
                ['queue_priority' => 'Normal'], ['queue_priority' => 'Emergency', 'reason' => $reason], $actor);
            $this->conn->commit();
            return ['success' => true, 'message' => 'Emergency priority applied. The queue has been updated.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('prioritizeEmergency error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to prioritize the patient.'];
        }
    }

    public function lookupToday(string $term): array {
        $term = strtoupper(trim($term));
        if (!preg_match('/^AVC-[A-Z0-9]+$/', $term)) return [];
        $sql = $this->baseLogbookQuery() . "
            WHERE a.date = CURDATE()
              AND a.status = 'Confirmed'
              AND UPPER(a.appointment_code) = :term
            ORDER BY a.created_at ASC LIMIT 10
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':term' => $term]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['lookup_method'] = 'Code';
        return $rows;
    }

    public function checkIn($appointmentId, $userId, string $lookupMethod = 'Code'): array {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("
                SELECT
                    a.appointment_id,
                    a.patient_id,
                    a.date,
                    a.status,
                    a.deposit_required,
                    p.profile_completed_at,
                    d.status AS deposit_status
                FROM appointments a
                JOIN patients p ON p.patient_id = a.patient_id
                LEFT JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
                WHERE a.appointment_id = :appointment_id
                FOR UPDATE
            ");
            $stmt->execute([':appointment_id' => $appointmentId]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment || $appointment['date'] !== date('Y-m-d')) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only appointments scheduled for today can be checked in.'];
            }
            if ($appointment['status'] !== 'Confirmed') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only confirmed appointments can be checked in.'];
            }
            if ((int) $appointment['deposit_required'] === 1
                && !in_array($appointment['deposit_status'], ['Verified', 'Transferred'], true)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'The appointment deposit has not been verified.'];
            }

            $existing = $this->conn->prepare("SELECT checkin_id, checkin_status FROM appointment_checkins WHERE appointment_id = :appointment_id");
            $existing->execute([':appointment_id' => $appointmentId]);
            $existingRow = $existing->fetch(PDO::FETCH_ASSOC);
            if ($existingRow) {
                $this->conn->rollBack();
                return ['success' => true, 'message' => 'The patient is already checked in.', 'status' => $existingRow['checkin_status']];
            }

            $profileRequired = empty($appointment['profile_completed_at']);
            $checkinStatus = $profileRequired ? 'Profile Required' : 'Ready';
            $insert = $this->conn->prepare("
                INSERT INTO appointment_checkins (
                    appointment_id,
                    arrived_at,
                    checked_in_by_user_id,
                    checkin_status,
                    profile_required_at_arrival,
                    ready_at,
                    queue_status,
                    queue_priority,
                    queue_entered_at,
                    lookup_method
                ) VALUES (
                    :appointment_id,
                    NOW(),
                    :user_id,
                    :checkin_status,
                    :profile_required,
                    CASE WHEN :ready_status = 'Ready' THEN NOW() ELSE NULL END,
                    'Waiting',
                    'Normal',
                    NOW(),
                    :lookup_method
                )
            ");
            $insert->execute([
                ':appointment_id' => $appointmentId,
                ':user_id' => $userId,
                ':checkin_status' => $checkinStatus,
                ':profile_required' => $profileRequired ? 1 : 0,
                ':ready_status' => $checkinStatus,
                ':lookup_method' => in_array($lookupMethod, ['Code', 'Patient Search'], true) ? $lookupMethod : 'Code',
            ]);

            $this->conn->prepare("UPDATE appointments SET status = 'Checked In' WHERE appointment_id = :appointment_id")
                ->execute([':appointment_id' => $appointmentId]);

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Staff account not found.');
            $this->auditLog->record(
                'appointment',
                $appointmentId,
                'patient_checked_in',
                "Checked in the patient for appointment #{$appointmentId}.",
                null,
                ['status' => 'Checked In', 'checkin_status' => $checkinStatus, 'profile_required' => $profileRequired, 'lookup_method' => $lookupMethod],
                $actor
            );

            $this->conn->commit();
            return [
                'success' => true,
                'message' => $profileRequired
                    ? 'Arrival recorded. Complete the patient form before treatment.'
                    : 'Arrival recorded. The patient is ready.',
                'status' => $checkinStatus,
                'patient_id' => (int) $appointment['patient_id'],
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('checkIn error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to record the patient arrival.'];
        }
    }

    public function markReadyAfterProfile($patientId): void {
        $stmt = $this->conn->prepare("
            UPDATE appointment_checkins ci
            JOIN appointments a ON a.appointment_id = ci.appointment_id
            SET ci.checkin_status = 'Ready', ci.ready_at = NOW()
            WHERE a.patient_id = :patient_id
              AND a.date = CURDATE()
              AND ci.checkin_status = 'Profile Required'
        ");
        $stmt->execute([':patient_id' => $patientId]);
    }
}
