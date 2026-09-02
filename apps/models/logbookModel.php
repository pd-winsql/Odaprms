<?php

require_once __DIR__ . '/auditLogModel.php';

class LogbookModel
{
    private $conn;
    private $auditLog;

    // Sets up the database connection and audit logger.
    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
    }

    // Builds the shared query used to retrieve logbook details.
    private function baseLogbookQuery(): string
    {
        return "
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.patient_id,
                a.date, a.start_time, a.end_time,
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
                /* Serve Next is a one-time staff override; these fields explain who changed the order and why. */
                ci.serve_next_at,
                ci.serve_next_reason,
                ci.serve_next_by_user_id,
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
                payment.billing_recorded_by,
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

    // Gets logbook entries for a selected date in queue order.
    public function getForDate($date): array
    {
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
                    WHEN a.status = 'Checked In' AND ci.queue_status = 'On Hold' THEN 3
                    WHEN ci.arrived_at IS NOT NULL THEN 4
                    ELSE 5
                END,
                /* A staff-selected patient is first; everyone else keeps FIFO order. */
                CASE WHEN ci.serve_next_at IS NOT NULL THEN 0 ELSE 1 END,
                ci.serve_next_at DESC,
                COALESCE(ci.queue_entered_at, ci.arrived_at, a.created_at) ASC,
                ci.arrived_at ASC,
                a.created_at ASC
        ");
        $stmt->execute([':date' => $date]);
        return $this->annotateQueue($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Gets the historical dates that contain at least one visible logbook record.
    public function getRecordDates(): array
    {
        $stmt = $this->conn->query("
            SELECT DISTINCT a.date
            FROM vw_appointment_overview a
            LEFT JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
            WHERE a.date <= CURDATE()
              AND (
                a.deposit_required = 0
                OR payment.deposit_status IN ('Verified', 'Transferred')
              )
              AND a.status IN ('Confirmed', 'Checked In', 'In Progress', 'Completed', 'No-show', 'Cancelled')
            ORDER BY a.date ASC
        ");

        return array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    // Gets all logbook entries scheduled for today.
    public function getToday(): array
    {
        return $this->getForDate(date('Y-m-d'));
    }

    // Adds queue position and treatment flags to each entry.
    private function annotateQueue(array $rows): array
    {
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

    // Finds the appointment currently first in the ready queue.
    private function getNextReadyAppointmentId(bool $forUpdate = false): ?int
    {
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
              /* Keep the treatment-start decision identical to the displayed queue. */
              CASE WHEN ci.serve_next_at IS NOT NULL THEN 0 ELSE 1 END,
              ci.serve_next_at DESC,
              COALESCE(ci.queue_entered_at, ci.arrived_at) ASC,
              ci.arrived_at ASC,
              ci.checkin_id ASC
            LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
        $value = $this->conn->query($sql)->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    // Returns the next patient waiting to be treated.
    public function getNextPatient(): ?array
    {
        foreach ($this->getToday() as $row) {
            if ($row['is_next']) return $row;
        }
        return null;
    }

    // Locks and returns an appointment's queue record for an update.
    private function queueRecordForUpdate(int $appointmentId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT a.appointment_id, a.status AS appointment_status, a.date,
                   p.firstname, p.lastname, p.profile_status,
                   ci.checkin_status, ci.queue_status, ci.serve_next_at
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

    // Cleans and validates a reason for changing queue order.
    private function validateQueueReason(string $reason): ?string
    {
        $reason = trim($reason);
        if (strlen($reason) < 3) return null;
        return substr($reason, 0, 255);
    }

    // Places the next ready patient on hold and records the action.
    public function placeOnHold(int $appointmentId, int $userId, string $reason): array
    {
        $reason = $this->validateQueueReason($reason);
        if ($reason === null) return ['success' => false, 'message' => 'A short reason is required to place the patient on hold.'];

        try {
            $this->conn->beginTransaction();
            $record = $this->queueRecordForUpdate($appointmentId);
            if (
                !$record || $record['date'] !== date('Y-m-d') || $record['appointment_status'] !== 'Checked In'
                || $record['checkin_status'] !== 'Ready' || $record['queue_status'] !== 'Waiting'
            ) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only a ready patient in today\'s active queue can be placed on hold.'];
            }
            if ($this->getNextReadyAppointmentId(true) !== $appointmentId) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only the next patient can be placed on hold.'];
            }

            /* On Hold removes the patient from the active queue and cancels any pending Serve Next override. */
            $this->conn->prepare("
                UPDATE appointment_checkins
                SET queue_status = 'On Hold', queue_reason = :reason,
                    serve_next_at = NULL, serve_next_reason = NULL, serve_next_by_user_id = NULL,
                    queue_updated_by_user_id = :user_id, queue_updated_at = NOW()
                WHERE appointment_id = :appointment_id
            ")->execute([':reason' => $reason, ':user_id' => $userId, ':appointment_id' => $appointmentId]);

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Staff account not found.');
            $this->auditLog->record(
                'appointment',
                $appointmentId,
                'queue_placed_on_hold',
                "Placed appointment #{$appointmentId} on hold in the patient queue.",
                ['queue_status' => 'Waiting'],
                ['queue_status' => 'On Hold', 'reason' => $reason],
                $actor
            );
            $this->conn->commit();
            return ['success' => true, 'message' => 'Patient placed on hold. The next ready patient is now first in queue.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('placeOnHold error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to place the patient on hold.'];
        }
    }

    // Returns an on-hold patient to the end of the queue.
    public function returnToQueue(int $appointmentId, int $userId): array
    {
        try {
            $this->conn->beginTransaction();
            $record = $this->queueRecordForUpdate($appointmentId);
            if (
                !$record || $record['date'] !== date('Y-m-d') || $record['appointment_status'] !== 'Checked In'
                || $record['queue_status'] !== 'On Hold'
            ) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only an on-hold patient from today can return to the queue.'];
            }

            $this->conn->prepare("
                UPDATE appointment_checkins
                SET queue_status = 'Waiting', queue_entered_at = NOW(),
                    queue_reason = NULL, queue_updated_by_user_id = :user_id, queue_updated_at = NOW()
                WHERE appointment_id = :appointment_id
            ")->execute([':user_id' => $userId, ':appointment_id' => $appointmentId]);

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Staff account not found.');
            $this->auditLog->record(
                'appointment',
                $appointmentId,
                'queue_returned',
                "Returned appointment #{$appointmentId} to the patient queue.",
                ['queue_status' => 'On Hold'],
                ['queue_status' => 'Waiting'],
                $actor
            );
            $this->conn->commit();
            return ['success' => true, 'message' => 'Patient returned to the end of the normal queue.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('returnToQueue error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to return the patient to the queue.'];
        }
    }

    // Moves a selected ready patient to the front of the queue.
    public function serveNext(int $appointmentId, int $userId, string $reason): array
    {
        $reason = $this->validateQueueReason($reason);
        if ($reason === null) return ['success' => false, 'message' => 'A reason is required to serve this patient next.'];

        try {
            $this->conn->beginTransaction();
            $record = $this->queueRecordForUpdate($appointmentId);
            if (
                !$record || $record['date'] !== date('Y-m-d') || $record['appointment_status'] !== 'Checked In'
                || $record['checkin_status'] !== 'Ready' || $record['queue_status'] !== 'Waiting'
            ) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only a ready patient in today\'s active queue can be served next.'];
            }
            if ($record['serve_next_at'] !== null) {
                $this->conn->rollBack();
                return ['success' => true, 'changed' => false, 'message' => 'This patient is already selected to be served next.'];
            }

            /* Only one patient can own the one-time Serve Next override for today's queue. */
            $this->conn->exec("
                UPDATE appointment_checkins ci
                JOIN appointments a ON a.appointment_id = ci.appointment_id
                SET ci.serve_next_at = NULL, ci.serve_next_reason = NULL, ci.serve_next_by_user_id = NULL
                WHERE a.date = CURDATE() AND ci.serve_next_at IS NOT NULL
            ");
            $this->conn->prepare("
                UPDATE appointment_checkins
                SET serve_next_at = NOW(), serve_next_reason = :reason, serve_next_by_user_id = :user_id,
                    queue_updated_by_user_id = :user_id, queue_updated_at = NOW()
                WHERE appointment_id = :appointment_id
            ")->execute([':reason' => $reason, ':user_id' => $userId, ':appointment_id' => $appointmentId]);

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Staff account not found.');
            $this->auditLog->record(
                'appointment',
                $appointmentId,
                'queue_serve_next',
                "Selected appointment #{$appointmentId} to be served next.",
                null,
                ['serve_next' => true, 'reason' => $reason],
                $actor
            );
            $this->conn->commit();
            return ['success' => true, 'message' => 'Patient selected to be served next.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('serveNext error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to select the patient to be served next.'];
        }
    }

    // Finds today's confirmed appointments using a code or patient name.
    public function lookupToday(string $term): array
    {
        $term = trim($term);
        if ($term === '') return [];

        $isCode = preg_match('/^AVC-[A-Z0-9]+$/i', $term) === 1;
        $where = $isCode
            ? 'UPPER(a.appointment_code) = :term'
            : "(
                    a.firstname LIKE :term
                    OR a.lastname LIKE :term
                    OR CONCAT_WS(' ', a.firstname, a.lastname) LIKE :term
                    OR CONCAT_WS(' ', a.lastname, a.firstname) LIKE :term
               )";
        $sql = $this->baseLogbookQuery() . "
            WHERE a.date = CURDATE()
              AND a.status = 'Confirmed'
              AND {$where}
            ORDER BY a.lastname ASC, a.firstname ASC, a.created_at ASC
            LIMIT 10
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':term' => $isCode ? strtoupper($term) : '%' . $term . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['lookup_method'] = $isCode ? 'Code' : 'Patient Search';
        unset($row);
        return $rows;
    }

    // Checks in a confirmed patient and adds them to today's queue.
    public function checkIn($appointmentId, $userId, string $lookupMethod = 'Code'): array
    {
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
            if (
                (int) $appointment['deposit_required'] === 1
                && !in_array($appointment['deposit_status'], ['Verified', 'Transferred'], true)
            ) {
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
            // Every arrival receives a quick staff profile review before entering the ready queue.
            $checkinStatus = 'Profile Required';
            $insert = $this->conn->prepare("
                INSERT INTO appointment_checkins (
                    appointment_id,
                    arrived_at,
                    checked_in_by_user_id,
                    checkin_status,
                    profile_required_at_arrival,
                    ready_at,
                    queue_status,
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
                    : 'Arrival recorded. Review and confirm the patient profile before treatment.',
                'status' => $checkinStatus,
                'patient_id' => (int) $appointment['patient_id'],
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('checkIn error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to record the patient arrival.'];
        }
    }

    // Marks a checked-in patient as ready after completing their profile.
    public function markReadyAfterProfile($patientId): void
    {
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
