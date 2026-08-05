<?php

require_once __DIR__ . '/auditLogModel.php';

class DepositModel {
    private $conn;
    private $auditLog;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
    }

    public function expireUnpaidAppointments(): int {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->query("
                SELECT a.appointment_id, a.status
                FROM appointments a
                JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
                WHERE a.status = 'Awaiting Payment'
                  AND d.status IN ('Awaiting Submission', 'Rejected')
                  AND COALESCE(d.resubmission_deadline_at, a.payment_deadline_at) <= NOW()
                FOR UPDATE
            ");
            $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$expired) {
                $this->conn->commit();
                return 0;
            }

            $appointmentIds = array_map('intval', array_column($expired, 'appointment_id'));
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));

            $depositStmt = $this->conn->prepare("
                UPDATE appointment_deposits
                SET status = 'Expired',
                    rejection_reason = 'Payment submission deadline expired.'
                WHERE appointment_id IN ({$placeholders})
            ");
            $depositStmt->execute($appointmentIds);

            $appointmentStmt = $this->conn->prepare("
                UPDATE appointments
                SET status = 'Cancelled',
                    cancelled_at = NOW(),
                    cancellation_reason = 'Payment submission deadline expired.'
                WHERE appointment_id IN ({$placeholders})
            ");
            $appointmentStmt->execute($appointmentIds);

            $actor = [
                'user_id' => null,
                'name' => 'System',
                'role' => 'System',
                'source' => 'System',
            ];
            foreach ($expired as $row) {
                $this->auditLog->record(
                    'appointment',
                    (int) $row['appointment_id'],
                    'status_changed',
                    "Cancelled appointment #{$row['appointment_id']} after its payment deadline expired.",
                    ['status' => $row['status']],
                    ['status' => 'Cancelled'],
                    $actor
                );
            }

            $this->conn->commit();
            return count($expired);
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('expireUnpaidAppointments error: ' . $e->getMessage());
            return 0;
        }
    }

    public function getPaymentContext($appointmentId, $patientId = null, $token = null) {
        $conditions = ['a.appointment_id = :appointment_id'];
        $params = [':appointment_id' => $appointmentId];

        if ($patientId !== null) {
            $conditions[] = 'a.patient_id = :patient_id';
            $params[':patient_id'] = $patientId;
        } elseif ($token !== null && $token !== '') {
            $conditions[] = 'a.payment_access_token_hash = :token_hash';
            $params[':token_hash'] = hash('sha256', $token);
        } else {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT
                a.appointment_id,
                a.patient_id,
                a.date,
                a.status AS appointment_status,
                a.payment_deadline_at,
                p.firstname,
                p.lastname,
                p.email,
                c.clinic_name,
                d.deposit_id,
                d.amount,
                d.gcash_reference,
                d.receipt_path,
                d.status AS deposit_status,
                d.submitted_at,
                d.verified_at,
                d.rejection_reason,
                d.resubmission_deadline_at,
                (
                    SELECT GROUP_CONCAT(s.service_name ORDER BY s.display_order, s.service_name SEPARATOR ', ')
                    FROM appointment_services aps
                    JOIN services s ON s.service_id = aps.service_id
                    WHERE aps.appointment_id = a.appointment_id
                ) AS service_name
            FROM appointments a
            JOIN patients p ON p.patient_id = a.patient_id
            JOIN clinics c ON c.clinic_id = a.clinic_id
            JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
            WHERE " . implode(' AND ', $conditions) . "
            LIMIT 1
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPatientDeposits($patientId): array {
        $stmt = $this->conn->prepare("
            SELECT
                a.appointment_id,
                a.date,
                a.status AS appointment_status,
                a.payment_deadline_at,
                c.clinic_name,
                d.deposit_id,
                d.amount,
                d.gcash_reference,
                d.status AS deposit_status,
                d.submitted_at,
                d.verified_at,
                d.rejection_reason,
                d.resubmission_deadline_at,
                (
                    SELECT GROUP_CONCAT(s.service_name ORDER BY s.display_order, s.service_name SEPARATOR ', ')
                    FROM appointment_services aps
                    JOIN services s ON s.service_id = aps.service_id
                    WHERE aps.appointment_id = a.appointment_id
                ) AS service_name
            FROM appointments a
            JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
            JOIN clinics c ON c.clinic_id = a.clinic_id
            WHERE a.patient_id = :patient_id
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function submitReceipt($appointmentId, $reference, $receiptPath, $receiptMime): array {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("
                SELECT d.deposit_id, d.status, d.receipt_path,
                       COALESCE(d.resubmission_deadline_at, a.payment_deadline_at) AS effective_deadline,
                       a.status AS appointment_status
                FROM appointment_deposits d
                JOIN appointments a ON a.appointment_id = d.appointment_id
                WHERE d.appointment_id = :appointment_id
                FOR UPDATE
            ");
            $stmt->execute([':appointment_id' => $appointmentId]);
            $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$deposit || $deposit['appointment_status'] !== 'Awaiting Payment') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This appointment is not awaiting payment.'];
            }
            if (!in_array($deposit['status'], ['Awaiting Submission', 'Rejected'], true)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This payment cannot accept another receipt.'];
            }
            if (strtotime($deposit['effective_deadline']) <= time()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'The payment deadline has expired.'];
            }

            $duplicate = $this->conn->prepare("
                SELECT deposit_id
                FROM appointment_deposits
                WHERE gcash_reference = :reference
                  AND deposit_id <> :deposit_id
                LIMIT 1
            ");
            $duplicate->execute([
                ':reference' => $reference,
                ':deposit_id' => $deposit['deposit_id'],
            ]);
            if ($duplicate->fetchColumn()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This GCash reference number has already been submitted.'];
            }

            $update = $this->conn->prepare("
                UPDATE appointment_deposits
                SET gcash_reference = :reference,
                    receipt_path = :receipt_path,
                    receipt_mime = :receipt_mime,
                    status = 'Under Review',
                    submitted_at = NOW(),
                    rejection_reason = NULL,
                    resubmission_deadline_at = NULL
                WHERE deposit_id = :deposit_id
            ");
            $update->execute([
                ':reference' => $reference,
                ':receipt_path' => $receiptPath,
                ':receipt_mime' => $receiptMime,
                ':deposit_id' => $deposit['deposit_id'],
            ]);

            $this->conn->commit();
            return [
                'success' => true,
                'old_receipt_path' => $deposit['receipt_path'],
                'message' => 'Payment proof submitted for verification.',
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('submitReceipt error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to submit the payment proof.'];
        }
    }

    public function getPendingReviews(): array {
        $stmt = $this->conn->query("
            SELECT
                d.deposit_id,
                d.appointment_id,
                d.amount,
                d.gcash_reference,
                d.receipt_mime,
                d.submitted_at,
                p.firstname,
                p.lastname,
                p.email,
                c.clinic_name,
                a.date,
                (
                    SELECT GROUP_CONCAT(s.service_name ORDER BY s.display_order, s.service_name SEPARATOR ', ')
                    FROM appointment_services aps
                    JOIN services s ON s.service_id = aps.service_id
                    WHERE aps.appointment_id = a.appointment_id
                ) AS service_name
            FROM appointment_deposits d
            JOIN appointments a ON a.appointment_id = d.appointment_id
            JOIN patients p ON p.patient_id = a.patient_id
            JOIN clinics c ON c.clinic_id = a.clinic_id
            WHERE d.status = 'Under Review'
              AND a.status = 'Awaiting Payment'
            ORDER BY d.submitted_at ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingReviewCount(): int {
        return (int) $this->conn->query("
            SELECT COUNT(*)
            FROM appointment_deposits d
            JOIN appointments a ON a.appointment_id = d.appointment_id
            WHERE d.status = 'Under Review'
              AND a.status = 'Awaiting Payment'
        ")->fetchColumn();
    }

    public function getReceiptForStaff($depositId) {
        $stmt = $this->conn->prepare("
            SELECT receipt_path, receipt_mime
            FROM appointment_deposits
            WHERE deposit_id = :deposit_id
              AND receipt_path IS NOT NULL
        ");
        $stmt->execute([':deposit_id' => $depositId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function verify($depositId, $userId): array {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("
                SELECT d.appointment_id, d.status AS deposit_status, a.status AS appointment_status
                FROM appointment_deposits d
                JOIN appointments a ON a.appointment_id = d.appointment_id
                WHERE d.deposit_id = :deposit_id
                FOR UPDATE
            ");
            $stmt->execute([':deposit_id' => $depositId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['deposit_status'] !== 'Under Review' || $row['appointment_status'] !== 'Awaiting Payment') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This payment is no longer awaiting verification.'];
            }

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Verifier account not found.');

            $depositUpdate = $this->conn->prepare("
                UPDATE appointment_deposits
                SET status = 'Verified', verified_by_user_id = :user_id, verified_at = NOW()
                WHERE deposit_id = :deposit_id
            ");
            $depositUpdate->execute([':user_id' => $userId, ':deposit_id' => $depositId]);

            $appointmentUpdate = $this->conn->prepare("
                UPDATE appointments
                SET status = 'Confirmed', confirmed_at = NOW()
                WHERE appointment_id = :appointment_id
            ");
            $appointmentUpdate->execute([':appointment_id' => $row['appointment_id']]);

            $this->auditLog->record(
                'appointment',
                (int) $row['appointment_id'],
                'status_changed',
                "Verified the deposit and confirmed appointment #{$row['appointment_id']}.",
                ['status' => 'Awaiting Payment', 'deposit_status' => 'Under Review'],
                ['status' => 'Confirmed', 'deposit_status' => 'Verified'],
                $actor
            );

            $this->conn->commit();
            return ['success' => true, 'message' => 'Payment verified and appointment confirmed.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('verify deposit error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to verify this payment.'];
        }
    }

    public function reject($depositId, $userId, $reason): array {
        try {
            $this->conn->beginTransaction();
            $settings = $this->conn->query("SELECT payment_deadline_minutes FROM site_settings WHERE id = 1")
                ->fetch(PDO::FETCH_ASSOC) ?: [];
            $minutes = max(1, (int) ($settings['payment_deadline_minutes'] ?? 30));

            $stmt = $this->conn->prepare("
                SELECT d.appointment_id, d.status, a.status AS appointment_status
                FROM appointment_deposits d
                JOIN appointments a ON a.appointment_id = d.appointment_id
                WHERE d.deposit_id = :deposit_id
                FOR UPDATE
            ");
            $stmt->execute([':deposit_id' => $depositId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['status'] !== 'Under Review' || $row['appointment_status'] !== 'Awaiting Payment') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This payment is no longer awaiting verification.'];
            }

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Verifier account not found.');

            $update = $this->conn->prepare("
                UPDATE appointment_deposits
                SET status = 'Rejected',
                    rejection_reason = :reason,
                    resubmission_deadline_at = DATE_ADD(NOW(), INTERVAL {$minutes} MINUTE),
                    verified_by_user_id = :user_id,
                    verified_at = NOW()
                WHERE deposit_id = :deposit_id
            ");
            $update->execute([
                ':reason' => $reason,
                ':user_id' => $userId,
                ':deposit_id' => $depositId,
            ]);
            $this->conn->prepare("
                UPDATE appointments
                SET payment_deadline_at = DATE_ADD(NOW(), INTERVAL {$minutes} MINUTE)
                WHERE appointment_id = :appointment_id
            ")->execute([':appointment_id' => $row['appointment_id']]);

            $this->auditLog->record(
                'appointment',
                (int) $row['appointment_id'],
                'payment_rejected',
                "Rejected the payment proof for appointment #{$row['appointment_id']}.",
                ['deposit_status' => 'Under Review'],
                ['deposit_status' => 'Rejected', 'reason' => $reason],
                $actor
            );

            $this->conn->commit();
            return ['success' => true, 'message' => 'Payment rejected. The patient has 30 minutes to resubmit.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('reject deposit error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to reject this payment.'];
        }
    }
}
