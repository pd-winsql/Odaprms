<?php

require_once __DIR__ . '/auditLogModel.php';
require_once __DIR__ . '/emailNotificationModel.php';
require_once __DIR__ . '/../helpers/paymentSettings.php';

class Appointment {
    private $conn;
    private $auditLog;
    private $emailNotifications;

    public function __construct($conn) 
    {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
        $this->emailNotifications = new EmailNotificationModel($conn);
    }

    public function getServiceDetailsForAppointments(array $appointmentIds): array {
        $appointmentIds = array_values(array_unique(array_filter(array_map('intval', $appointmentIds))));
        if (!$appointmentIds) return [];

        try {
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
            $stmt = $this->conn->prepare("
                SELECT
                    aps.appointment_id,
                    s.service_id,
                    s.service_name,
                    s.service_description,
                    s.service_icon,
                    c.category_name
                FROM appointment_services aps
                JOIN services s ON s.service_id = aps.service_id
                LEFT JOIN service_categories c ON c.category_id = s.category_id
                WHERE aps.appointment_id IN ({$placeholders})
                ORDER BY aps.appointment_id, s.display_order, s.service_name
            ");
            $stmt->execute($appointmentIds);

            $grouped = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $service) {
                $grouped[(int) $service['appointment_id']][] = $service;
            }
            return $grouped;
        } catch (PDOException $e) {
            error_log('getServiceDetailsForAppointments error: ' . $e->getMessage());
            return [];
        }
    }

    public function bookAppointment($patient_id, $clinic_id, $service_ids, $date, $schedule_id, $performedByUserId = null) {
        // Normalize and validate service IDs: cast to ints, remove falsy values,
        // deduplicate and reindex the array. If no services remain, abort.
        $service_ids = array_values(array_unique(array_filter(array_map('intval', (array) $service_ids))));
        if (empty($service_ids)) return false;

        try {
            $this->conn->beginTransaction();

            // -----------------------------------------------------------------
            // Block bookings for this patient to serialize concurrent requests.
            // This uses SELECT ... FOR UPDATE to obtain a row lock on the
            // patient record. It prevents two simultaneous booking requests
            // from both passing the "one appointment per day" check.
            // -----------------------------------------------------------------
            $patientStmt = $this->conn->prepare("
                SELECT patient_id
                FROM patients
                WHERE patient_id = :patient_id
                FOR UPDATE
            ");
            $patientStmt->execute([':patient_id' => $patient_id]);
            if (!$patientStmt->fetchColumn()) {
                // If the patient doesn't exist, rollback and return an error.
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Patient profile not found.'];
            }

            // -----------------------------------------------------------------
            // Lock the schedule row to prevent two requests consuming the same
            // final slot simultaneously. Then validate the schedule belongs to
            // the requested clinic, matches the requested date, and is not in
            // the past.
            // -----------------------------------------------------------------
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
                // Invalid schedule (mismatch or past date) — rollback and abort.
                $this->conn->rollBack();
                return false;
            }

            // -----------------------------------------------------------------
            // Prevent the patient from having more than one active appointment
            // on the same date. The status filter covers all states that should
            // be considered a "conflicting" appointment.
            // -----------------------------------------------------------------
            $sameDayAppointment = $this->conn->prepare("
                SELECT appointment_id
                FROM appointments
                WHERE patient_id = :patient_id
                    AND date = :appointment_date
                    AND status IN (
                        'Pending Review', 'Awaiting Deposit', 'Payment Under Review',
                        'Confirmed', 'Checked In', 'In Progress', 'Completed',
                        'Pending', 'Awaiting Payment'
                    )
                LIMIT 1
            ");
            $sameDayAppointment->execute([
                ':patient_id' => $patient_id,
                ':appointment_date' => $date,
            ]);
            if ($sameDayAppointment->fetchColumn()) {
                // Found an existing appointment on the same date — rollback and
                // notify the caller so they can pick another date/schedule.
                $this->conn->rollBack();
                return [
                    'success' => false,
                    'message' => 'You already have an appointment on this date. Please choose a different schedule.',
                ];
            }

            // -----------------------------------------------------------------
            // Enforce schedule capacity: count active appointments for this
            // schedule and compare with max_appointments. If full, abort.
            // -----------------------------------------------------------------
            $capacityStmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM appointments
                WHERE schedule_id = :schedule_id
                    AND status IN ('Pending Review', 'Awaiting Deposit', 'Payment Under Review', 'Confirmed', 'Checked In', 'In Progress', 'Completed')
            ");
            $capacityStmt->execute([':schedule_id' => $schedule_id]);
            if ((int) $capacityStmt->fetchColumn() >= (int) $schedule['max_appointments']) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'No available slots remain for this schedule.'];
            }

            // -----------------------------------------------------------------
            // Create the appointment record with initial status 'Pending Review'.
            // deposit_required is set to 1 by default here and payment_deadline
            // left NULL until a deposit workflow is triggered.
            // -----------------------------------------------------------------
            $stmt = $this->conn->prepare("
                INSERT INTO appointments
                (
                    patient_id,
                    clinic_id,
                    date,
                    schedule_id,
                    status,
                    deposit_required,
                    payment_deadline_at
                )
                VALUES
                (
                    :patient_id,
                    :clinic_id,
                    :date,
                    :schedule_id,
                    'Pending Review',
                    1,
                    NULL
                )
            ");
            $inserted = $stmt->execute([
                ':patient_id' => $patient_id,
                ':clinic_id' => $clinic_id,
                ':date' => $date,
                ':schedule_id' => $schedule_id,
            ]);

            if (!$inserted) {
                // Failed to insert appointment — rollback and return false.
                $this->conn->rollBack();
                return false;
            }

            // Attach each requested service to the newly created appointment.
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

            // Record an audit entry describing the submitted appointment.
            // If a staff user triggered the booking, include their actor info,
            // otherwise attribute it to the patient.
            $actor = $performedByUserId ? $this->auditLog->getUserActor($performedByUserId) : null;
            $this->auditLog->record(
                'appointment', $appointment_id, 'appointment_requested',
                "Submitted appointment request #{$appointment_id} for staff review.",
                null, ['status' => 'Pending Review'],
                $actor ?: ['user_id' => null, 'name' => 'Patient', 'role' => 'Patient', 'source' => 'User']
            );

            // Commit the transaction and return success + created appointment id.
            $this->conn->commit();
            return [
                'success' => true,
                'appointment_id' => $appointment_id,
                'status' => 'Pending Review',
                'message' => 'Appointment request submitted for clinic review.',
            ];
        } catch(Throwable $e){
            // Ensure the transaction is rolled back on any error and log it.
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log("bookAppointment error: ".$e->getMessage());
            return ['success' => false, 'message' => 'Booking failed. Please try again.'];
        }
    }

    // ===== PATIENT FUNCTIONS =====

    // Patient: view upcoming appointments
    public function getPatientUpcomingAppointments($patient_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT a.*
                FROM vw_appointment_overview a
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
            $stmt = $this->conn->prepare("
                SELECT a.*
                FROM vw_appointment_overview a
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
            $stmt = $this->conn->prepare("
                SELECT
                    a.lastname,
                    a.firstname,
                    a.email,
                    a.clinic_name,
                    a.service_name,
                    a.date,
                    a.status
                FROM vw_appointment_overview a
                WHERE a.email = :email
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
            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, a.lastname, a.firstname, a.middlename, a.age, a.gender,
                    a.phone_number, a.email, a.clinic_name, a.service_name,
                    a.date, a.status, a.payment_deadline_at, a.appointment_code,
                    payment.deposit_id, payment.deposit_amount, payment.gcash_reference,
                    payment.deposit_status, payment.submitted_at, payment.verified_at,
                    payment.payment_rejection_reason,
                    payment.resubmission_deadline_at, payment.refund_reason, payment.refunded_at,
                    payment.has_receipt, payment.receipt_mime,
                    payment.payment_verified_by,
                    payment.payment_verified_by_role,
                    status_change.status_changed_by,
                    status_change.status_changed_by_role,
                    status_change.status_changed_at
                FROM vw_appointment_overview a
                LEFT JOIN vw_appointment_payment_summary payment
                    ON payment.appointment_id = a.appointment_id
                LEFT JOIN vw_appointment_latest_status_change status_change
                    ON status_change.appointment_id = a.appointment_id
                WHERE a.date < CURDATE()
                    AND a.status NOT IN ('Pending Review', 'Awaiting Deposit', 'Payment Under Review')
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
            $stmt = $this->conn->prepare("
                SELECT a.*
                FROM vw_appointment_overview a
                WHERE a.date < CURDATE()
                AND a.clinic_name = :clinic
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
            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, a.lastname, a.firstname, a.middlename, a.age, a.gender,
                    a.phone_number, a.email, a.clinic_name, a.service_name,
                    a.date, a.status, a.payment_deadline_at, a.appointment_code,
                    payment.deposit_id, payment.deposit_amount, payment.gcash_reference,
                    payment.deposit_status, payment.submitted_at, payment.verified_at,
                    payment.payment_rejection_reason,
                    payment.resubmission_deadline_at, payment.refund_reason, payment.refunded_at,
                    payment.has_receipt, payment.receipt_mime,
                    payment.payment_verified_by,
                    payment.payment_verified_by_role,
                    status_change.status_changed_by,
                    status_change.status_changed_by_role,
                    status_change.status_changed_at
                FROM vw_appointment_overview a
                LEFT JOIN vw_appointment_payment_summary payment
                    ON payment.appointment_id = a.appointment_id
                LEFT JOIN vw_appointment_latest_status_change status_change
                    ON status_change.appointment_id = a.appointment_id
                WHERE a.date >= CURDATE()
                    OR a.status IN ('Pending Review', 'Awaiting Deposit', 'Payment Under Review')
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
    public function updateAppointmentStatus($appointment_id, $status, $performedByUserId, $reason = '') {
        // Define allowed state transitions for appointments. This map drives
        // validation so only permitted transitions are accepted.
        $allowedTransitions = [
            'Pending Review' => ['Awaiting Deposit', 'Rejected'],
            'Awaiting Deposit' => ['Cancelled'],
            'Payment Under Review' => [],
            'Confirmed' => ['Checked In', 'Cancelled', 'No-show'],
            'Checked In' => ['In Progress'],
            'In Progress' => ['Completed'],
            'Completed' => [],
            'Cancelled' => [],
            'No-show' => [],
            'Rejected' => [],
        ];

        // Quick validation: ensure the requested target status is one of the
        // statuses known to the system (appears in allowedTransitions values).
        if (!in_array($status, array_merge(...array_values($allowedTransitions)), true)) {
            return ['success' => false, 'message' => 'Invalid appointment status.'];
        }

        try {
            // Start a DB transaction so the multi-step status update is atomic.
            $this->conn->beginTransaction();

            // Lock the appointment row to read the current status and prevent
            // concurrent status changes from racing with this update.
            $currentStmt = $this->conn->prepare("
                SELECT status
                FROM appointments
                WHERE appointment_id = :id
                FOR UPDATE
            ");
            $currentStmt->execute([':id' => $appointment_id]);
            $oldStatus = $currentStmt->fetchColumn();

            // If appointment not found, rollback and return an error.
            if ($oldStatus === false) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Appointment not found.'];
            }

            // If no change is necessary, rollback the transaction and return
            // a success response indicating nothing changed.
            if ($oldStatus === $status) {
                $this->conn->rollBack();
                return ['success' => true, 'changed' => false, 'message' => 'The appointment already has this status.'];
            }

            // Validate that the transition from oldStatus -> status is allowed
            // according to the allowedTransitions map.
            if (!in_array($status, $allowedTransitions[$oldStatus] ?? [], true)) {
                $this->conn->rollBack();
                return [
                    'success' => false,
                    'message' => "Status cannot be changed from {$oldStatus} to {$status}.",
                ];
            }

            // If rejecting an appointment, require a non-empty reason.
            if ($status === 'Rejected' && trim($reason) === '') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'A rejection reason is required.'];
            }

            // Additional readiness checks when transitioning to 'In Progress'.
            // These ensure the patient profile and check-in state are correct,
            // that the appointment is for today and the patient is actually
            // next in the queue (respecting any one-time "serve next" override).
            if ($status === 'In Progress') {
                $readiness = $this->conn->prepare("
                    SELECT a.date, p.profile_status, ci.checkin_status,
                        ci.queue_status, ci.serve_next_at
                    FROM appointments a JOIN patients p ON p.patient_id=a.patient_id
                    LEFT JOIN appointment_checkins ci ON ci.appointment_id=a.appointment_id
                    WHERE a.appointment_id=:id
                    FOR UPDATE
                ");
                $readiness->execute([':id'=>$appointment_id]);
                $ready=$readiness->fetch(PDO::FETCH_ASSOC);

                // Ensure patient profile is complete and check-in status is 'Ready'.
                if(!$ready || $ready['profile_status']!=='Complete' || $ready['checkin_status']!=='Ready'){
                    $this->conn->rollBack();
                    return ['success'=>false,'message'=>'Complete the entire patient profile before starting treatment.'];
                }

                // Appointment must be scheduled for today and be in the 'Waiting' queue state.
                if ($ready['date'] !== date('Y-m-d') || $ready['queue_status'] !== 'Waiting') {
                    $this->conn->rollBack();
                    return ['success'=>false,'message'=>'Only a waiting patient in today\'s queue can start treatment.'];
                }

                // Prevent two patients being 'In Progress' at once: lock and check
                // for any other appointment already in progress.
                $active = $this->conn->prepare("
                    SELECT appointment_id
                    FROM appointments
                    WHERE status = 'In Progress' AND appointment_id <> :id
                    FOR UPDATE
                ");
                $active->execute([':id' => $appointment_id]);
                if ($active->fetchColumn() !== false) {
                    $this->conn->rollBack();
                    return ['success'=>false,'message'=>'Complete the current patient\'s visit before starting the next patient.'];
                }

                // Determine who is next in the queue using consistent ordering.
                // This query respects a one-time 'serve_next_at' override and
                // then falls back to FIFO by arrival/queue_entered time.
                $next = $this->conn->query("
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
                      /* Serve Next is checked inside this transaction so two staff actions cannot bypass each other. */
                        CASE WHEN ci.serve_next_at IS NOT NULL THEN 0 ELSE 1 END,
                        ci.serve_next_at DESC,
                        COALESCE(ci.queue_entered_at, ci.arrived_at) ASC,
                        ci.arrived_at ASC,
                        ci.checkin_id ASC
                    LIMIT 1
                    FOR UPDATE
                ")->fetchColumn();

                // If this appointment is not the current next patient, abort.
                if ($next === false || (int) $next !== (int) $appointment_id) {
                    $this->conn->rollBack();
                    return ['success'=>false,'message'=>'This patient is not next in the queue. Use Today\'s Logbook to adjust the queue first.'];
                }

                /* Starting treatment consumes the one-time override; normal FIFO resumes afterward. */
                $this->conn->prepare("
                    UPDATE appointment_checkins
                    SET serve_next_at = NULL, serve_next_reason = NULL, serve_next_by_user_id = NULL
                    WHERE appointment_id = :id
                ")->execute([':id' => $appointment_id]);
            }

            // Resolve the actor (the user performing the status change) for
            // audit logging. Throwing here causes the transaction to rollback
            // in the catch block below.
            $actor = $this->auditLog->getUserActor($performedByUserId);
            if (!$actor) {
                throw new RuntimeException('The authenticated user could not be found.');
            }

            // Business rules related to deposits and payment when accepting
            // an appointment for payment or marking a no-show/cancellation.
            if ($oldStatus === 'Pending Review' && $status === 'Awaiting Deposit') {
                // Create or update a deposit record with the configured amount
                // and payment deadline minutes (defaults used when missing).
                $settings = $this->conn->query("SELECT deposit_amount, payment_deadline_minutes FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
                $amount = (float) ($settings['deposit_amount'] ?? 400);
                $minutes = max(1, (int) ($settings['payment_deadline_minutes'] ?? 480));
                $this->conn->prepare("
                    INSERT INTO appointment_deposits (appointment_id, amount, status)
                    VALUES (:appointment_id, :amount, 'Awaiting Submission')
                    ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = 'Awaiting Submission'
                ")->execute([':appointment_id' => $appointment_id, ':amount' => $amount]);
            }
            if ($status === 'No-show') {
                // Forfeiture: if the deposit was already verified, mark it forfeited.
                $this->conn->prepare("UPDATE appointment_deposits SET status='Forfeited', refund_reason='Patient did not attend the confirmed appointment.' WHERE appointment_id=:id AND status='Verified'")
                    ->execute([':id'=>$appointment_id]);
            }
            if ($status === 'Cancelled') {
                // When clinic cancels, mark verified deposits as 'For Refund',
                // and otherwise mark them expired.
                $this->conn->prepare("
                    UPDATE appointment_deposits SET
                        status = CASE WHEN status='Verified' THEN 'For Refund' ELSE 'Expired' END,
                        refund_reason = CASE WHEN status='Verified' THEN 'Clinic cancelled the appointment.' ELSE refund_reason END
                    WHERE appointment_id=:id AND status IN ('Verified','Awaiting Submission','Rejected')
                ")->execute([':id'=>$appointment_id]);
            }

            // Update the appointment row. Use CASE WHEN to set reviewer and
            // timestamps only for specific status transitions while preserving
            // existing values for other transitions.
            $stmt = $this->conn->prepare("
                UPDATE appointments SET
                    status = :status,
                    reviewed_by_user_id = CASE WHEN :status IN ('Awaiting Deposit','Rejected') THEN :reviewer ELSE reviewed_by_user_id END,
                    reviewed_at = CASE WHEN :status IN ('Awaiting Deposit','Rejected') THEN NOW() ELSE reviewed_at END,
                    accepted_for_payment_at = CASE WHEN :status = 'Awaiting Deposit' THEN NOW() ELSE accepted_for_payment_at END,
                    payment_deadline_at = CASE WHEN :status = 'Awaiting Deposit' THEN DATE_ADD(NOW(), INTERVAL :deadline_minutes MINUTE) ELSE payment_deadline_at END,
                    rejected_at = CASE WHEN :status = 'Rejected' THEN NOW() ELSE rejected_at END,
                    rejection_reason = CASE WHEN :status = 'Rejected' THEN :reason ELSE rejection_reason END,
                    treatment_started_at = CASE WHEN :status = 'In Progress' THEN NOW() ELSE treatment_started_at END,
                    completed_at = CASE WHEN :status = 'Completed' THEN NOW() ELSE completed_at END,
                    cancelled_at = CASE WHEN :status = 'Cancelled' THEN NOW() ELSE cancelled_at END
                WHERE appointment_id = :id
            ");
            $stmt->execute([
                ':status' => $status,
                ':reviewer' => $performedByUserId,
                ':deadline_minutes' => isset($minutes) ? $minutes : 480,
                ':reason' => trim($reason) ?: null,
                ':id'     => $appointment_id,
            ]);

            // Create an audit record for the status change. The audit contains
            // the old and new statuses and who performed the change.
            $audit = $this->auditLog->record(
                'appointment',
                (int) $appointment_id,
                'status_changed',
                "Changed appointment #{$appointment_id} status from {$oldStatus} to {$status}.",
                ['status' => $oldStatus],
                ['status' => $status],
                $actor
            );

            // Queue notification inside the same transaction as the status
            // change. A separate browser request handles the slow SMTP call.
            $notification = null;
            $notificationTemplates = [
                'Awaiting Deposit' => 'appointment_awaiting_deposit',
                'Rejected' => 'appointment_rejected',
                'Cancelled' => 'appointment_cancelled',
            ];
            if (isset($notificationTemplates[$status])) {
                $notification = $this->emailNotifications->enqueueAppointmentTemplate(
                    (int) $appointment_id,
                    $notificationTemplates[$status],
                    trim($reason) !== '' ? trim($reason) : $status,
                    'audit:' . $audit['audit_log_id'] . ':' . $notificationTemplates[$status]
                );
            }

            // Commit the transaction now that all updates and side-effects are done.
            $this->conn->commit();

            // Return a contextual success message depending on the new status,
            // along with basic audit info for the caller to display.
            return [
                'success' => true,
                'changed' => true,
                'message' => $status === 'Awaiting Deposit'
                    ? 'Appointment accepted. The patient now has ' . vdFormatDurationMinutes($minutes) . ' to submit the ' . vdFormatPesoAmount($amount) . ' deposit.'
                    : ($status === 'In Progress' ? 'Treatment started for the next patient.' : 'Status updated successfully.'),
                'audit' => [
                    'performed_by_name' => $actor['name'],
                    'performed_by_role' => $actor['role'],
                    'performed_at' => $audit['performed_at'],
                ],
                'appointment' => [
                    'id' => (int) $appointment_id,
                    'status' => $status,
                ],
                'notification' => $notification,
            ];

        } catch (Throwable $e) {
            // On any error, roll back the transaction and log the exception.
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
                AND status IN ('Pending Review', 'Awaiting Deposit', 'Payment Under Review', 'Confirmed', 'Checked In', 'In Progress', 'Completed')
        ");

        $stmt->execute([
            ':schedule_id' => $schedule_id
        ]);

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function getAppointmentsByStatus($status) {
        try {
            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, a.lastname, a.firstname, a.middlename, a.age, a.gender,
                    a.phone_number, a.email, a.clinic_name, a.service_name, a.date, a.status
                FROM vw_appointment_overview a
                WHERE a.date >= CURDATE()
                AND a.status = :status
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
            $stmt = $this->conn->prepare("
                SELECT
                    a.appointment_id,
                    a.service_name,
                    a.date,
                    a.status,
                    a.clinic_name
                FROM vw_appointment_overview a
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
