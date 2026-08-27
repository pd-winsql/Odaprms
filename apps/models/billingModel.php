<?php

require_once __DIR__ . '/auditLogModel.php';

class BillingModel {
    private $conn;
    private $auditLog;
    private $maxServicesPerVisit;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
        $appointmentRules = require __DIR__ . '/../../config/appointment.php';
        $this->maxServicesPerVisit = max(1, (int) ($appointmentRules['max_services_per_visit'] ?? 5));
    }

    private function normalizeServiceIds(array $serviceIds): array {
        return array_values(array_unique(array_filter(array_map('intval', $serviceIds))));
    }

    private function getAppointmentServicesForUpdate(int $appointmentId): array {
        $stmt = $this->conn->prepare("
            SELECT aps.service_id, aps.quantity, aps.unit_price_snapshot,
                   s.service_name, s.is_active, s.display_order
            FROM appointment_services aps
            JOIN services s ON s.service_id = aps.service_id
            WHERE aps.appointment_id = :appointment_id
            ORDER BY s.display_order, s.service_name
            FOR UPDATE
        ");
        $stmt->execute([':appointment_id' => $appointmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validateSelectedServices(array $serviceIds, array $existingServices): array {
        if (!$serviceIds) {
            throw new InvalidArgumentException('Select at least one service performed.');
        }

        $existingIds = array_map('intval', array_column($existingServices, 'service_id'));
        $comparison = $serviceIds;
        $existingComparison = $existingIds;
        sort($comparison);
        sort($existingComparison);
        $changed = $comparison !== $existingComparison;

        // Older appointments may already exceed the new limit. They can be
        // settled unchanged, but any edited selection must respect the limit.
        if ($changed && count($serviceIds) > $this->maxServicesPerVisit) {
            throw new InvalidArgumentException(
                "You can select up to {$this->maxServicesPerVisit} services per visit."
            );
        }

        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $stmt = $this->conn->prepare("
            SELECT service_id, service_name, is_active, display_order
            FROM services
            WHERE service_id IN ({$placeholders})
            ORDER BY display_order, service_name
        ");
        $stmt->execute($serviceIds);
        $selectedServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($selectedServices) !== count($serviceIds)) {
            throw new InvalidArgumentException('One or more selected services are invalid.');
        }

        foreach ($selectedServices as $service) {
            $serviceId = (int) $service['service_id'];
            if ((int) $service['is_active'] !== 1 && !in_array($serviceId, $existingIds, true)) {
                throw new InvalidArgumentException('Inactive services cannot be newly added to a visit.');
            }
        }

        return ['changed' => $changed, 'services' => $selectedServices];
    }

    private function replaceAppointmentServices(
        int $appointmentId,
        array $serviceIds,
        array $existingServices
    ): void {
        $existingById = [];
        foreach ($existingServices as $service) {
            $existingById[(int) $service['service_id']] = $service;
        }

        $this->conn->prepare('DELETE FROM appointment_services WHERE appointment_id = :appointment_id')
            ->execute([':appointment_id' => $appointmentId]);

        $values = [];
        $params = [];
        foreach ($serviceIds as $index => $serviceId) {
            $values[] = "(:appointment_id_{$index}, :service_id_{$index}, :quantity_{$index}, :price_{$index})";
            $existing = $existingById[$serviceId] ?? null;
            $params[":appointment_id_{$index}"] = $appointmentId;
            $params[":service_id_{$index}"] = $serviceId;
            $params[":quantity_{$index}"] = $existing['quantity'] ?? 1;
            $params[":price_{$index}"] = $existing['unit_price_snapshot'] ?? null;
        }

        $insert = $this->conn->prepare("
            INSERT INTO appointment_services
                (appointment_id, service_id, quantity, unit_price_snapshot)
            VALUES " . implode(', ', $values)
        );
        $insert->execute($params);
    }

    /**
     * Keeps receipt line snapshots complete while per-service pricing is being
     * phased in. A known appointment price wins; otherwise only a single-service
     * bill can safely inherit the entered treatment total.
     */
    private function syncBillingItems(int $billingId, int $appointmentId, float $serviceAmount): void {
        $items = $this->conn->prepare("
            INSERT INTO appointment_billing_items
                (billing_id, service_id, service_name_snapshot, quantity, unit_price, pricing_source, sort_order)
            SELECT
                :billing_id,
                s.service_id,
                s.service_name,
                aps.quantity,
                CASE
                    WHEN aps.unit_price_snapshot IS NOT NULL THEN aps.unit_price_snapshot
                    WHEN service_count.total_services = 1
                        THEN :service_amount / NULLIF(aps.quantity, 0)
                    ELSE NULL
                END,
                CASE
                    WHEN aps.unit_price_snapshot IS NOT NULL THEN 'appointment-snapshot'
                    WHEN service_count.total_services = 1 THEN 'billing-total'
                    ELSE 'legacy-unknown'
                END,
                s.display_order
            FROM appointment_services aps
            JOIN services s ON s.service_id = aps.service_id
            JOIN (
                SELECT appointment_id, COUNT(*) AS total_services
                FROM appointment_services
                WHERE appointment_id = :count_appointment_id
                GROUP BY appointment_id
            ) service_count ON service_count.appointment_id = aps.appointment_id
            WHERE aps.appointment_id = :appointment_id
            ON DUPLICATE KEY UPDATE
                service_name_snapshot = VALUES(service_name_snapshot),
                quantity = VALUES(quantity),
                unit_price = VALUES(unit_price),
                pricing_source = VALUES(pricing_source),
                sort_order = VALUES(sort_order)
        ");
        $items->execute([
            ':billing_id' => $billingId,
            ':service_amount' => $serviceAmount,
            ':count_appointment_id' => $appointmentId,
            ':appointment_id' => $appointmentId,
        ]);
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
                   COALESCE(
                       (
                           SELECT GROUP_CONCAT(item.service_name_snapshot ORDER BY item.sort_order, item.billing_item_id SEPARATOR ', ')
                           FROM appointment_billing_items item
                           WHERE item.billing_id = payment.billing_id
                       ),
                       a.service_name
                   ) AS service_name
            FROM vw_appointment_overview a
            JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
            WHERE payment.billing_id IS NOT NULL
            ORDER BY payment.billing_recorded_at DESC, a.appointment_id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function settleAndCompleteVisit(
        int $appointmentId,
        float $serviceAmount,
        float $cashTendered,
        int $userId,
        string $notes = '',
        array $serviceIds = [],
        string $serviceChangeReason = ''
    ): array {
        if ($serviceAmount < 0 || $cashTendered < 0) {
            return ['success' => false, 'message' => 'Amounts cannot be negative.'];
        }
        $serviceIds = $this->normalizeServiceIds($serviceIds);
        if (!$serviceIds) {
            return ['success' => false, 'message' => 'Select at least one service performed.'];
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

            $existingServices = $this->getAppointmentServicesForUpdate($appointmentId);
            $serviceValidation = $this->validateSelectedServices($serviceIds, $existingServices);
            $servicesChanged = $serviceValidation['changed'];
            $selectedServices = $serviceValidation['services'];
            $serviceChangeReason = trim($serviceChangeReason);
            if ($servicesChanged && strlen($serviceChangeReason) < 3) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Enter a short reason for changing the performed services.'];
            }
            if (strlen($serviceChangeReason) > 255) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'The service change reason cannot exceed 255 characters.'];
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

            if ($servicesChanged) {
                $this->replaceAppointmentServices($appointmentId, $serviceIds, $existingServices);
                $oldServices = array_map(static fn(array $service): array => [
                    'service_id' => (int) $service['service_id'],
                    'service_name' => $service['service_name'],
                ], $existingServices);
                $newServices = array_map(static fn(array $service): array => [
                    'service_id' => (int) $service['service_id'],
                    'service_name' => $service['service_name'],
                ], $selectedServices);
                $this->auditLog->record(
                    'appointment',
                    $appointmentId,
                    'appointment_services_changed',
                    "Updated the performed services for appointment #{$appointmentId} during final billing.",
                    ['services' => $oldServices],
                    ['services' => $newServices, 'reason' => $serviceChangeReason],
                    $actor
                );
            }

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
            $this->syncBillingItems((int) $this->conn->lastInsertId(), $appointmentId, $serviceAmount);

            $this->conn->prepare("UPDATE appointments SET status='Completed', completed_at=NOW() WHERE appointment_id=:id")
                ->execute([':id' => $appointmentId]);

            $billingValues = [
                'service_amount' => $serviceAmount,
                'deposit_applied' => $depositApplied,
                'amount_due' => $amountDue,
                'cash_tendered' => $cashTendered,
                'change' => $change,
                'payment_status' => 'Paid',
                'services' => array_map(static fn(array $service): array => [
                    'service_id' => (int) $service['service_id'],
                    'service_name' => $service['service_name'],
                ], $selectedServices),
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
        } catch (InvalidArgumentException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
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
                    billing_id = LAST_INSERT_ID(billing_id),
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
            $this->syncBillingItems((int) $this->conn->lastInsertId(), $appointmentId, $serviceAmount);
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
