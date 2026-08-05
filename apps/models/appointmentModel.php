<?php

require_once __DIR__ . '/auditLogModel.php';

class Appointment {
    private $conn;
    private $auditLog;

    public function __construct($conn) 
    {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
    }

    private function serviceNameSelect() {
        return "(
            SELECT GROUP_CONCAT(ms.service_name ORDER BY ms.display_order, ms.service_name SEPARATOR ', ')
            FROM appointment_services aps
            JOIN services ms ON ms.service_id = aps.service_id
            WHERE aps.appointment_id = a.appointment_id
        )";
    }

    public function bookAppointment($patient_id, $clinic_id, $service_ids, $date, $schedule_id) {
        $service_ids = array_values(array_unique(array_filter(array_map('intval', (array) $service_ids))));
        if (empty($service_ids)) return false;

        try {
            $this->conn->beginTransaction();

            // Lock the schedule so concurrent requests cannot take the final
            // slot at the same time.
            $scheduleStmt = $this->conn->prepare("
                SELECT schedule_id, clinic_id, sched_date, max_appointments
                FROM schedules
                WHERE schedule_id = :schedule_id
                FOR UPDATE
            ");
            $scheduleStmt->execute([':schedule_id' => $schedule_id]);
            $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule
                || (int) $schedule['clinic_id'] !== (int) $clinic_id
                || $schedule['sched_date'] !== $date
                || $schedule['sched_date'] < date('Y-m-d')) {
                $this->conn->rollBack();
                return false;
            }

            $capacityStmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM appointments
                WHERE schedule_id = :schedule_id
                  AND (
                    status IN ('Pending', 'Confirmed', 'Completed')
                    OR (
                        status = 'Awaiting Payment'
                        AND (
                            payment_deadline_at > NOW()
                            OR EXISTS (
                                SELECT 1 FROM appointment_deposits review_deposit
                                WHERE review_deposit.appointment_id = appointments.appointment_id
                                  AND review_deposit.status = 'Under Review'
                            )
                        )
                    )
                  )
            ");
            $capacityStmt->execute([':schedule_id' => $schedule_id]);
            if ((int) $capacityStmt->fetchColumn() >= (int) $schedule['max_appointments']) {
                $this->conn->rollBack();
                return false;
            }

            $settingsStmt = $this->conn->query("
                SELECT deposit_amount, payment_deadline_minutes
                FROM site_settings
                WHERE id = 1
            ");
            $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $depositAmount = (float) ($settings['deposit_amount'] ?? 400.00);
            $deadlineMinutes = max(1, (int) ($settings['payment_deadline_minutes'] ?? 30));
            $paymentToken = bin2hex(random_bytes(32));
            $paymentTokenHash = hash('sha256', $paymentToken);

            $stmt = $this->conn->prepare("
                INSERT INTO appointments
                (
                    patient_id,
                    clinic_id,
                    date,
                    schedule_id,
                    status,
                    deposit_required,
                    payment_deadline_at,
                    payment_access_token_hash
                )
                VALUES
                (
                    :patient_id,
                    :clinic_id,
                    :date,
                    :schedule_id,
                    'Awaiting Payment',
                    1,
                    DATE_ADD(NOW(), INTERVAL {$deadlineMinutes} MINUTE),
                    :payment_access_token_hash
                )
            ");
            $inserted = $stmt->execute([
                ':patient_id' => $patient_id,
                ':clinic_id' => $clinic_id,
                ':date' => $date,
                ':schedule_id' => $schedule_id,
                ':payment_access_token_hash' => $paymentTokenHash,
            ]);

            if (!$inserted) {
                $this->conn->rollBack();
                return false;
            }

            $appointment_id = (int) $this->conn->lastInsertId();
            $link = $this->conn->prepare("
                INSERT INTO appointment_services (appointment_id, service_id)
                VALUES (:appointment_id, :service_id)
            ");
            foreach ($service_ids as $service_id) {
                $link->execute([
                    ':appointment_id' => $appointment_id,
                    ':service_id' => $service_id,
                ]);
            }

            $depositStmt = $this->conn->prepare("
                INSERT INTO appointment_deposits (appointment_id, amount)
                VALUES (:appointment_id, :amount)
            ");
            $depositStmt->execute([
                ':appointment_id' => $appointment_id,
                ':amount' => $depositAmount,
            ]);

            $deadlineStmt = $this->conn->prepare("
                SELECT payment_deadline_at
                FROM appointments
                WHERE appointment_id = :appointment_id
            ");
            $deadlineStmt->execute([':appointment_id' => $appointment_id]);
            $paymentDeadline = $deadlineStmt->fetchColumn();

            $this->conn->commit();
            return [
                'appointment_id' => $appointment_id,
                'payment_token' => $paymentToken,
                'payment_deadline_at' => $paymentDeadline,
                'deposit_amount' => $depositAmount,
            ];
        } catch(Throwable $e){
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log("bookAppointment error: ".$e->getMessage());
            return false;
        }
    }

    // ===== PATIENT FUNCTIONS =====

    // Patient: view upcoming appointments
    public function getPatientUpcomingAppointments($patient_id) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT
                a.*,
                {$serviceName} AS service_name,
                c.clinic_name
                FROM appointments a
                LEFT JOIN clinics c
                ON a.clinic_id = c.clinic_id
                WHERE a.patient_id = :patient_id
                AND a.date >= CURDATE()

                ORDER BY a.date ASC
            ");
            $stmt->execute([':patient_id' => $patient_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getPatientUpcomingAppointments error: " . $e->getMessage());
            return [];
        }
    }

    // Patient: view past appointments
    public function getPatientPastAppointments($patient_id) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT
                a.*,
                {$serviceName} AS service_name,
                c.clinic_name
                FROM appointments a
                LEFT JOIN clinics c
                ON a.clinic_id = c.clinic_id
                WHERE a.patient_id = :patient_id
                AND a.date < CURDATE()

                ORDER BY a.date ASC
            ");
            $stmt->execute([':patient_id' => $patient_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getPatientPastAppointments error: " . $e->getMessage());
            return [];
        }
    }

    // Patient: view upcoming appointments with status
    public function getUpcomingWithStatus($email) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT
                    p.lastname,
                    p.firstname,
                    p.email,
                    c.clinic_name,
                    {$serviceName} AS service_name,
                    a.date,
                    a.status
                FROM appointments a
                LEFT JOIN patients p
                ON a.patient_id = p.patient_id
                LEFT JOIN clinics c
                ON a.clinic_id = c.clinic_id
                WHERE p.email = :email
                AND a.date >= CURDATE()
                ORDER BY a.date ASC
            ");
            $stmt->execute([':email' => $email]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getUpcomingWithStatus error: " . $e->getMessage());
            return [];
        }
    }

    // ===== ADMIN FUNCTIONS =====

    // Admin: view all past appointments
    public function getAdminPastAppointments() {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, p.lastname, p.firstname, p.middlename, p.age, p.gender,
                    p.phone_number, p.email, c.clinic_name, {$serviceName} AS service_name,
                    a.date, a.status,
                    al.performed_by_name AS status_changed_by,
                    al.performed_by_role AS status_changed_by_role,
                    al.performed_at AS status_changed_at
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                LEFT JOIN audit_logs al ON al.audit_log_id = (
                    SELECT al2.audit_log_id
                    FROM audit_logs al2
                    WHERE al2.entity_type = 'appointment'
                    AND al2.entity_id = a.appointment_id
                    AND al2.action = 'status_changed'
                    ORDER BY al2.audit_log_id DESC
                    LIMIT 1
                )
                WHERE a.date < CURDATE()
                  AND (
                    a.deposit_required = 0
                    OR EXISTS (
                        SELECT 1 FROM appointment_deposits ad
                        WHERE ad.appointment_id = a.appointment_id
                          AND ad.status IN ('Verified', 'Transferred')
                    )
                  )
                ORDER BY a.date DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getAdminPastAppointments error: " . $e->getMessage());
            return [];
        }
    }

    // Admin: view past appointments per clinic
    public function getAdminPastAppointmentsByClinic($clinic) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT a.*, p.lastname, p.firstname, p.middlename, p.age, p.gender,
                    p.phone_number, p.email, c.clinic_name, {$serviceName} AS service_name
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                WHERE a.date < CURDATE()
                AND c.clinic_name = :clinic
                AND (
                    a.deposit_required = 0
                    OR EXISTS (
                        SELECT 1 FROM appointment_deposits ad
                        WHERE ad.appointment_id = a.appointment_id
                          AND ad.status IN ('Verified', 'Transferred')
                    )
                )
                ORDER BY a.date DESC
            ");
            $stmt->execute([':clinic' => $clinic]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getAdminPastAppointmentsByClinic error: " . $e->getMessage());
            return [];
        }
    }

    // Admin: view all upcoming appointments with status
    public function getAllUpcomingWithStatus() {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, p.lastname, p.firstname, p.middlename, p.age, p.gender,
                    p.phone_number, p.email, c.clinic_name, {$serviceName} AS service_name,
                    a.date, a.status,
                    al.performed_by_name AS status_changed_by,
                    al.performed_by_role AS status_changed_by_role,
                    al.performed_at AS status_changed_at
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                LEFT JOIN audit_logs al ON al.audit_log_id = (
                    SELECT al2.audit_log_id
                    FROM audit_logs al2
                    WHERE al2.entity_type = 'appointment'
                    AND al2.entity_id = a.appointment_id
                    AND al2.action = 'status_changed'
                    ORDER BY al2.audit_log_id DESC
                    LIMIT 1
                )
                WHERE a.date >= CURDATE()
                  AND (
                    a.deposit_required = 0
                    OR EXISTS (
                        SELECT 1
                        FROM appointment_deposits ad
                        WHERE ad.appointment_id = a.appointment_id
                          AND ad.status IN ('Verified', 'Transferred')
                    )
                  )
                ORDER BY a.date ASC, a.status ASC, a.created_at ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getAllUpcomingWithStatus error: " . $e->getMessage());
            return [];
        }
    }

    // Admin: update appointment status
    public function updateAppointmentStatus($appointment_id, $status, $performedByUserId) {
        $allowedTransitions = [
            'Pending' => ['Confirmed', 'Cancelled'],
            'Awaiting Payment' => ['Cancelled'],
            'Confirmed' => ['Completed', 'Cancelled', 'No-show', 'Rescheduled'],
            'Completed' => [],
            'Cancelled' => [],
            'No-show' => [],
            'Rescheduled' => [],
            'Rejected' => ['Cancelled'],
        ];

        if (!in_array($status, array_keys($allowedTransitions), true)) {
            return ['success' => false, 'message' => 'Invalid appointment status.'];
        }

        try {
            $this->conn->beginTransaction();

            $currentStmt = $this->conn->prepare("
                SELECT status
                FROM appointments
                WHERE appointment_id = :id
                FOR UPDATE
            ");
            $currentStmt->execute([':id' => $appointment_id]);
            $oldStatus = $currentStmt->fetchColumn();

            if ($oldStatus === false) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Appointment not found.'];
            }

            if ($oldStatus === $status) {
                $this->conn->rollBack();
                return ['success' => true, 'changed' => false, 'message' => 'The appointment already has this status.'];
            }

            if (!in_array($status, $allowedTransitions[$oldStatus] ?? [], true)) {
                $this->conn->rollBack();
                return [
                    'success' => false,
                    'message' => "Status cannot be changed from {$oldStatus} to {$status}.",
                ];
            }

            $actor = $this->auditLog->getUserActor($performedByUserId);
            if (!$actor) {
                throw new RuntimeException('The authenticated user could not be found.');
            }

            $stmt = $this->conn->prepare("
                UPDATE appointments
                SET status = :status,
                    confirmed_at = CASE WHEN :status = 'Confirmed' THEN COALESCE(confirmed_at, NOW()) ELSE confirmed_at END,
                    cancelled_at = CASE WHEN :status = 'Cancelled' THEN NOW() ELSE cancelled_at END
                WHERE appointment_id = :id
            ");
            $stmt->execute([
                ':status' => $status,
                ':id'     => $appointment_id,
            ]);

            $audit = $this->auditLog->record(
                'appointment',
                (int) $appointment_id,
                'status_changed',
                "Changed appointment #{$appointment_id} status from {$oldStatus} to {$status}.",
                ['status' => $oldStatus],
                ['status' => $status],
                $actor
            );

            $this->conn->commit();

            return [
                'success' => true,
                'changed' => true,
                'message' => 'Status updated successfully.',
                'audit' => [
                    'performed_by_name' => $actor['name'],
                    'performed_by_role' => $actor['role'],
                    'performed_at' => $audit['performed_at'],
                ],
            ];

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("updateAppointmentStatus error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update status.'];
        }
    }

        public function getLastInsertedId() {
        return $this->conn->lastInsertId();
    }

    public function countAppointmentsBySchedule($schedule_id)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE schedule_id = :schedule_id
              AND (
                status IN ('Pending', 'Confirmed', 'Completed')
                OR (
                    status = 'Awaiting Payment'
                    AND (
                        payment_deadline_at > NOW()
                        OR EXISTS (
                            SELECT 1 FROM appointment_deposits review_deposit
                            WHERE review_deposit.appointment_id = appointments.appointment_id
                              AND review_deposit.status = 'Under Review'
                        )
                    )
                )
              )
        ");

        $stmt->execute([
            ':schedule_id' => $schedule_id
        ]);

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function getAppointmentsByStatus($status) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, p.lastname, p.firstname, p.middlename, p.age, p.gender,
                    p.phone_number, p.email, c.clinic_name, {$serviceName} AS service_name, a.date, a.status
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                WHERE a.date >= CURDATE()
                AND a.status = :status
                AND (
                    a.deposit_required = 0
                    OR EXISTS (
                        SELECT 1
                        FROM appointment_deposits ad
                        WHERE ad.appointment_id = a.appointment_id
                          AND ad.status IN ('Verified', 'Transferred')
                    )
                )
                ORDER BY a.date ASC
            ");

            $stmt->execute([
                ':status' => $status
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getAppointmentsByStatus error: " . $e->getMessage());
            return [];
        }
    }

    public function getPatientTransactionHistory($patient_id) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT
                    a.appointment_id,
                    {$serviceName} AS service_name,
                    a.date,
                    a.status,
                    c.clinic_name
                FROM appointments a
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                WHERE a.patient_id = :patient_id
                ORDER BY a.date DESC
            ");
            $stmt->execute([':patient_id' => $patient_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getPatientTransactionHistory error: " . $e->getMessage());
            return [];
        }
    }
}
