<?php

require_once __DIR__ . '/auditLogModel.php';

class BillingModel {
    private $conn;
    private $auditLog;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
    }

    public function getStaffBillings(): array {
        $stmt = $this->conn->query("
            SELECT a.appointment_id, a.date, a.status AS appointment_status,
                   a.firstname, a.lastname, a.clinic_name,
                   payment.verified_deposit,
                   payment.actual_service_amount, payment.deposit_applied, payment.remaining_balance,
                   payment.cash_received, payment.payment_status,
                   payment.billing_id, payment.billing_recorded_at AS recorded_at,
                   payment.paid_at, payment.billing_notes AS notes,
                   payment.billing_recorded_by AS recorded_by,
                   a.service_name
            FROM vw_appointment_overview a
            JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
            WHERE payment.billing_id IS NOT NULL
            ORDER BY payment.billing_recorded_at DESC, a.appointment_id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function settleAndCompleteVisit(int $appointmentId, float $serviceAmount, float $cashTendered, int $userId, string $notes = ''): array {
        if ($serviceAmount < 0 || $cashTendered < 0) {
            return ['success' => false, 'message' => 'Amounts cannot be negative.'];
        }

        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("
                SELECT a.status, COALESCE(d.amount, 0) AS deposit_amount, b.billing_id
                FROM appointments a
                LEFT JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
                    AND d.status IN ('Verified', 'Transferred')
                LEFT JOIN appointment_billings b ON b.appointment_id = a.appointment_id
                WHERE a.appointment_id = :appointment_id
                FOR UPDATE
            ");
            $stmt->execute([':appointment_id' => $appointmentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Appointment not found.'];
            }
            if ($row['status'] === 'Completed' && $row['billing_id']) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This visit has already been billed and completed.'];
            }
            if ($row['status'] !== 'In Progress') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only an in-progress visit can be completed.'];
            }

            $depositApplied = min((float) $row['deposit_amount'], $serviceAmount);
            $amountDue = max(0, $serviceAmount - $depositApplied);
            if ($cashTendered < $amountDue) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Cash tendered must cover the full amount due.'];
            }
            $change = max(0, $cashTendered - $amountDue);
            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Staff account not found.');

            $billing = $this->conn->prepare("
                INSERT INTO appointment_billings
                    (appointment_id, actual_service_amount, deposit_applied, remaining_balance,
                     cash_received, payment_status, recorded_by_user_id, recorded_at, paid_at, notes)
                VALUES
                    (:appointment_id, :service_amount, :deposit, :amount_due,
                     :cash_tendered, 'Paid', :user_id, NOW(), NOW(), :notes)
            ");
            $billing->execute([
                ':appointment_id' => $appointmentId,
                ':service_amount' => $serviceAmount,
                ':deposit' => $depositApplied,
                ':amount_due' => $amountDue,
                ':cash_tendered' => $cashTendered,
                ':user_id' => $userId,
                ':notes' => trim($notes) ?: null,
            ]);

            $this->conn->prepare("UPDATE appointments SET status='Completed', completed_at=NOW() WHERE appointment_id=:id")
                ->execute([':id' => $appointmentId]);

            $billingValues = [
                'service_amount' => $serviceAmount,
                'deposit_applied' => $depositApplied,
                'amount_due' => $amountDue,
                'cash_tendered' => $cashTendered,
                'change' => $change,
                'payment_status' => 'Paid',
            ];
            $this->auditLog->record('appointment', $appointmentId, 'cash_billing_recorded',
                "Recorded the final cash billing for appointment #{$appointmentId}.", null, $billingValues, $actor);
            $this->auditLog->record('appointment', $appointmentId, 'status_changed',
                "Completed appointment #{$appointmentId} after full settlement.",
                ['status' => 'In Progress'], ['status' => 'Completed', 'payment_status' => 'Paid'], $actor);

            $this->conn->commit();
            return [
                'success' => true,
                'message' => 'Payment recorded and visit completed.',
                'payment_status' => 'Paid',
                'deposit_applied' => $depositApplied,
                'amount_due' => $amountDue,
                'cash_tendered' => $cashTendered,
                'change' => $change,
            ];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ((string) $e->getCode() === '23000') {
                return ['success' => false, 'message' => 'This visit has already been billed.'];
            }
            error_log('settleAndCompleteVisit error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to record payment and complete this visit.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('settleAndCompleteVisit error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to record payment and complete this visit.'];
        }
    }

    public function recordCashPayment(int $appointmentId, float $serviceAmount, float $cashReceived, int $userId, string $notes = ''): array {
        if ($serviceAmount < 0 || $cashReceived < 0) return ['success' => false, 'message' => 'Amounts cannot be negative.'];
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("
                SELECT a.status, COALESCE(d.amount, 0) AS deposit_amount
                FROM appointments a
                LEFT JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
                    AND d.status IN ('Verified', 'Transferred')
                WHERE a.appointment_id = :appointment_id FOR UPDATE
            ");
            $stmt->execute([':appointment_id' => $appointmentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !in_array($row['status'], ['Checked In', 'In Progress', 'Completed'], true)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Cash billing is available after check-in.'];
            }
            $deposit = min((float) $row['deposit_amount'], $serviceAmount);
            $balance = max(0, $serviceAmount - $deposit);
            $status = $cashReceived >= $balance ? 'Paid' : ($cashReceived > 0 ? 'Partially Paid' : 'Unpaid');
            $upsert = $this->conn->prepare("
                INSERT INTO appointment_billings
                    (appointment_id, actual_service_amount, deposit_applied, remaining_balance,
                     cash_received, payment_status, recorded_by_user_id, recorded_at, paid_at, notes)
                VALUES
                    (:appointment_id, :service_amount, :deposit, :balance,
                     :cash_received, :status, :user_id, NOW(), CASE WHEN :paid_status = 'Paid' THEN NOW() ELSE NULL END, :notes)
                ON DUPLICATE KEY UPDATE
                    actual_service_amount = VALUES(actual_service_amount), deposit_applied = VALUES(deposit_applied),
                    remaining_balance = VALUES(remaining_balance), cash_received = VALUES(cash_received),
                    payment_status = VALUES(payment_status), recorded_by_user_id = VALUES(recorded_by_user_id),
                    recorded_at = NOW(), paid_at = CASE WHEN VALUES(payment_status) = 'Paid' THEN NOW() ELSE NULL END,
                    notes = VALUES(notes)
            ");
            $upsert->execute([
                ':appointment_id' => $appointmentId, ':service_amount' => $serviceAmount,
                ':deposit' => $deposit, ':balance' => $balance, ':cash_received' => $cashReceived,
                ':status' => $status, ':paid_status' => $status, ':user_id' => $userId,
                ':notes' => trim($notes) ?: null,
            ]);
            $actor = $this->auditLog->getUserActor($userId);
            $this->auditLog->record('appointment', $appointmentId, 'cash_billing_recorded',
                "Recorded the final cash billing for appointment #{$appointmentId}.", null,
                ['service_amount' => $serviceAmount, 'deposit_applied' => $deposit, 'remaining_balance' => $balance, 'cash_received' => $cashReceived, 'payment_status' => $status], $actor);
            $this->conn->commit();
            return ['success' => true, 'message' => 'Cash billing recorded.', 'payment_status' => $status];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('recordCashPayment error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to record the cash billing.'];
        }
    }
}
