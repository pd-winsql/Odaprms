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
                   p.firstname, p.lastname, c.clinic_name,
                   COALESCE(d.amount, 0) AS verified_deposit,
                   b.actual_service_amount, b.deposit_applied, b.remaining_balance,
                   b.cash_received, COALESCE(b.payment_status, 'Unpaid') AS payment_status,
                   b.recorded_at, b.paid_at,
                   (SELECT GROUP_CONCAT(s.service_name ORDER BY s.display_order SEPARATOR ', ')
                    FROM appointment_services aps JOIN services s ON s.service_id = aps.service_id
                    WHERE aps.appointment_id = a.appointment_id) AS service_name
            FROM appointments a
            JOIN patients p ON p.patient_id = a.patient_id
            JOIN clinics c ON c.clinic_id = a.clinic_id
            LEFT JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
                AND d.status IN ('Verified', 'Transferred')
            LEFT JOIN appointment_billings b ON b.appointment_id = a.appointment_id
            WHERE a.status IN ('Checked In', 'In Progress', 'Completed')
            ORDER BY a.date DESC, a.appointment_id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
