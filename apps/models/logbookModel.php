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
                p.firstname,
                p.lastname,
                p.email,
                p.profile_completed_at,
                c.clinic_name,
                ci.checkin_id,
                ci.arrived_at,
                ci.checkin_status,
                ci.profile_required_at_arrival,
                ci.ready_at,
                COALESCE(d.amount, 0) AS verified_deposit,
                b.billing_id,
                b.actual_service_amount,
                b.deposit_applied,
                b.remaining_balance,
                b.cash_received,
                b.payment_status,
                b.recorded_at AS billing_recorded_at,
                b.notes AS billing_notes,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', st.firstname, st.middlename, st.lastname)), ''),
                    u.email,
                    '—'
                ) AS checked_in_by,
                (
                    SELECT GROUP_CONCAT(s.service_name ORDER BY s.display_order, s.service_name SEPARATOR ', ')
                    FROM appointment_services aps
                    JOIN services s ON s.service_id = aps.service_id
                    WHERE aps.appointment_id = a.appointment_id
                ) AS service_name
            FROM appointments a
            JOIN patients p ON p.patient_id = a.patient_id
            JOIN clinics c ON c.clinic_id = a.clinic_id
            LEFT JOIN appointment_checkins ci ON ci.appointment_id = a.appointment_id
            LEFT JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
                AND d.status IN ('Verified', 'Transferred')
            LEFT JOIN appointment_billings b ON b.appointment_id = a.appointment_id
            LEFT JOIN users u ON u.id = ci.checked_in_by_user_id
            LEFT JOIN staffs st ON st.user_id = u.id
        ";
    }

    public function getForDate($date): array {
        $stmt = $this->conn->prepare($this->baseLogbookQuery() . "
            WHERE a.date = :date
              AND (
                a.deposit_required = 0
                OR EXISTS (
                    SELECT 1 FROM appointment_deposits d
                    WHERE d.appointment_id = a.appointment_id
                      AND d.status IN ('Verified', 'Transferred')
                )
              )
              AND a.status IN ('Confirmed', 'Checked In', 'In Progress', 'Completed', 'No-show', 'Cancelled')
            ORDER BY ci.arrived_at IS NULL, ci.arrived_at ASC, a.created_at ASC
        ");
        $stmt->execute([':date' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getToday(): array {
        return $this->getForDate(date('Y-m-d'));
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
                    ready_at
                    ,lookup_method
                ) VALUES (
                    :appointment_id,
                    NOW(),
                    :user_id,
                    :checkin_status,
                    :profile_required,
                    CASE WHEN :ready_status = 'Ready' THEN NOW() ELSE NULL END,
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
