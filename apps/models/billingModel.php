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

    public static function normalizeInsightFilters(array $input): array {
        $period = trim((string) ($input['period'] ?? 'month'));
        if (!in_array($period, ['month', '30days', 'year', 'all', 'custom'], true)) {
            $period = 'month';
        }

        $today = new DateTimeImmutable('today');
        $dateFrom = null;
        $dateTo = null;
        if ($period === 'month') {
            $dateFrom = $today->modify('first day of this month');
            $dateTo = $today;
        } elseif ($period === '30days') {
            $dateFrom = $today->modify('-29 days');
            $dateTo = $today;
        } elseif ($period === 'year') {
            $dateFrom = $today->setDate((int) $today->format('Y'), 1, 1);
            $dateTo = $today;
        } elseif ($period === 'custom') {
            $fromValue = trim((string) ($input['date_from'] ?? ''));
            $toValue = trim((string) ($input['date_to'] ?? ''));
            $dateFrom = DateTimeImmutable::createFromFormat('!Y-m-d', $fromValue) ?: null;
            $dateTo = DateTimeImmutable::createFromFormat('!Y-m-d', $toValue) ?: null;
            if (!$dateFrom || !$dateTo || $dateFrom->format('Y-m-d') !== $fromValue || $dateTo->format('Y-m-d') !== $toValue) {
                throw new InvalidArgumentException('Choose a valid custom settlement date range.');
            }
        }

        if ($dateFrom && $dateTo) {
            if ($dateFrom > $dateTo) {
                throw new InvalidArgumentException('The settlement start date cannot be later than the end date.');
            }
            if ($dateFrom->diff($dateTo)->days > 1826) {
                throw new InvalidArgumentException('Billing insights can cover a maximum of five years at a time.');
            }
        }

        $clinicId = filter_var(
            $input['clinic_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return [
            'period' => $period,
            'date_from' => $dateFrom?->format('Y-m-d'),
            'date_to' => $dateTo?->format('Y-m-d'),
            'clinic_id' => $clinicId ?: null,
        ];
    }

    public function getBillingInsights(array $filters, int $page = 1, int $perPage = 15): array {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $current = $this->getCollectionTotals($filters);
        $comparisonFilters = $this->comparisonFilters($filters);
        $previous = $comparisonFilters ? $this->getCollectionTotals($comparisonFilters) : null;

        $changePercent = null;
        if ($previous && abs($previous['net_collected']) > 0.0001) {
            $changePercent = round(
                (($current['net_collected'] - $previous['net_collected']) / abs($previous['net_collected'])) * 100,
                1
            );
        }

        return [
            'filters' => $filters,
            'meta' => [
                'period_label' => $this->periodLabel($filters),
                'granularity' => $this->trendGranularity($filters),
                'generated_at' => date(DATE_ATOM),
                'definitions' => [
                    'net_collected' => 'Deposits applied plus balances collected at final settlement, less deposits refunded during the selected period.',
                    'settled_visits' => 'Visits with a final billing record, grouped by the date the settlement was paid or recorded.',
                    'average' => 'Net collected divided by settled visits in the selected period.',
                    'deposit_share' => 'Deposits applied as a percentage of gross settlement collections before refunds.',
                ],
            ],
            'summary' => $current + [
                'change_percent' => $changePercent,
                'has_comparison' => $previous !== null,
            ],
            'trend' => $this->getCollectionTrend($filters),
            'clinics' => $this->getClinicCollectionComparison($filters),
            'records' => $this->getSettlementRecords($filters, $page, $perPage),
        ];
    }

    public function getBillingInsightClinics(): array {
        return $this->conn
            ->query('SELECT clinic_id, clinic_name FROM clinics ORDER BY clinic_name')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    private function settlementWhere(array $filters): array {
        $conditions = ["payment.billing_id IS NOT NULL", "payment.payment_status = 'Paid'"];
        $params = [];
        $dateExpression = 'DATE(COALESCE(payment.paid_at, payment.billing_recorded_at))';
        if (!empty($filters['date_from'])) {
            $conditions[] = "{$dateExpression} >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = "{$dateExpression} <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        if (!empty($filters['clinic_id'])) {
            $conditions[] = 'a.clinic_id = :clinic_id';
            $params[':clinic_id'] = (int) $filters['clinic_id'];
        }
        return [implode(' AND ', $conditions), $params];
    }

    private function refundWhere(array $filters): array {
        $conditions = ["deposit.status = 'Refunded'", 'deposit.refunded_at IS NOT NULL'];
        $params = [];
        if (!empty($filters['date_from'])) {
            $conditions[] = 'DATE(deposit.refunded_at) >= :refund_date_from';
            $params[':refund_date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'DATE(deposit.refunded_at) <= :refund_date_to';
            $params[':refund_date_to'] = $filters['date_to'];
        }
        if (!empty($filters['clinic_id'])) {
            $conditions[] = 'appointment.clinic_id = :refund_clinic_id';
            $params[':refund_clinic_id'] = (int) $filters['clinic_id'];
        }
        return [implode(' AND ', $conditions), $params];
    }

    private function getCollectionTotals(array $filters): array {
        [$where, $params] = $this->settlementWhere($filters);
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS settled_visits,
                COALESCE(SUM(GREATEST(COALESCE(payment.deposit_applied, 0), 0)), 0) AS deposits,
                COALESCE(SUM(LEAST(
                    GREATEST(COALESCE(payment.cash_received, 0), 0),
                    GREATEST(COALESCE(payment.remaining_balance, 0), 0)
                )), 0) AS balances
            FROM vw_appointment_overview a
            JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
            WHERE {$where}
        ");
        $stmt->execute($params);
        $settlements = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        [$refundWhere, $refundParams] = $this->refundWhere($filters);
        $refundStmt = $this->conn->prepare("
            SELECT COALESCE(SUM(GREATEST(COALESCE(deposit.amount, 0), 0)), 0)
            FROM appointment_deposits deposit
            JOIN appointments appointment ON appointment.appointment_id = deposit.appointment_id
            WHERE {$refundWhere}
        ");
        $refundStmt->execute($refundParams);

        $visits = (int) ($settlements['settled_visits'] ?? 0);
        $deposits = (float) ($settlements['deposits'] ?? 0);
        $balances = (float) ($settlements['balances'] ?? 0);
        $refunds = (float) $refundStmt->fetchColumn();
        $gross = $deposits + $balances;
        $net = $gross - $refunds;

        return [
            'net_collected' => round($net, 2),
            'gross_collected' => round($gross, 2),
            'deposits' => round($deposits, 2),
            'balances' => round($balances, 2),
            'refunds' => round($refunds, 2),
            'settled_visits' => $visits,
            'average_per_visit' => $visits > 0 ? round($net / $visits, 2) : 0.0,
            'deposit_share' => $gross > 0 ? round(($deposits / $gross) * 100, 1) : 0.0,
        ];
    }

    private function comparisonFilters(array $filters): ?array {
        if (empty($filters['date_from']) || empty($filters['date_to'])) return null;
        $from = new DateTimeImmutable($filters['date_from']);
        $to = new DateTimeImmutable($filters['date_to']);
        $comparison = $filters;

        if ($filters['period'] === 'month') {
            $previousMonth = $from->modify('-1 month');
            $lastDay = (int) $previousMonth->format('t');
            $comparison['date_from'] = $previousMonth->format('Y-m-01');
            $comparison['date_to'] = $previousMonth->setDate(
                (int) $previousMonth->format('Y'),
                (int) $previousMonth->format('m'),
                min((int) $to->format('d'), $lastDay)
            )->format('Y-m-d');
            return $comparison;
        }

        if ($filters['period'] === 'year') {
            $previousYear = (int) $from->format('Y') - 1;
            $comparison['date_from'] = $previousYear . '-01-01';
            $targetMonth = (int) $to->format('m');
            $monthStart = (new DateTimeImmutable("{$previousYear}-{$targetMonth}-01"));
            $comparison['date_to'] = $monthStart->setDate(
                $previousYear,
                $targetMonth,
                min((int) $to->format('d'), (int) $monthStart->format('t'))
            )->format('Y-m-d');
            return $comparison;
        }

        $days = $from->diff($to)->days + 1;
        $comparison['date_to'] = $from->modify('-1 day')->format('Y-m-d');
        $comparison['date_from'] = $from->modify("-{$days} days")->format('Y-m-d');
        return $comparison;
    }

    private function trendGranularity(array $filters): string {
        if (empty($filters['date_from']) || empty($filters['date_to'])) return 'month';
        $days = (new DateTimeImmutable($filters['date_from']))
            ->diff(new DateTimeImmutable($filters['date_to']))->days;
        return $days <= 62 ? 'day' : 'month';
    }

    private function getCollectionTrend(array $filters): array {
        $granularity = $this->trendGranularity($filters);
        $settlementBucket = $granularity === 'day'
            ? "DATE_FORMAT(COALESCE(payment.paid_at, payment.billing_recorded_at), '%Y-%m-%d')"
            : "DATE_FORMAT(COALESCE(payment.paid_at, payment.billing_recorded_at), '%Y-%m-01')";
        [$where, $params] = $this->settlementWhere($filters);
        $stmt = $this->conn->prepare("
            SELECT {$settlementBucket} AS bucket,
                COALESCE(SUM(GREATEST(COALESCE(payment.deposit_applied, 0), 0)), 0) AS deposits,
                COALESCE(SUM(LEAST(
                    GREATEST(COALESCE(payment.cash_received, 0), 0),
                    GREATEST(COALESCE(payment.remaining_balance, 0), 0)
                )), 0) AS balances
            FROM vw_appointment_overview a
            JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
            WHERE {$where}
            GROUP BY bucket
            ORDER BY bucket
        ");
        $stmt->execute($params);
        $buckets = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $buckets[$row['bucket']] = [
                'bucket' => $row['bucket'],
                'deposits' => (float) $row['deposits'],
                'balances' => (float) $row['balances'],
                'refunds' => 0.0,
            ];
        }

        $refundBucket = $granularity === 'day'
            ? "DATE_FORMAT(deposit.refunded_at, '%Y-%m-%d')"
            : "DATE_FORMAT(deposit.refunded_at, '%Y-%m-01')";
        [$refundWhere, $refundParams] = $this->refundWhere($filters);
        $refundStmt = $this->conn->prepare("
            SELECT {$refundBucket} AS bucket,
                COALESCE(SUM(GREATEST(COALESCE(deposit.amount, 0), 0)), 0) AS refunds
            FROM appointment_deposits deposit
            JOIN appointments appointment ON appointment.appointment_id = deposit.appointment_id
            WHERE {$refundWhere}
            GROUP BY bucket
            ORDER BY bucket
        ");
        $refundStmt->execute($refundParams);
        foreach ($refundStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $buckets[$row['bucket']] ??= [
                'bucket' => $row['bucket'],
                'deposits' => 0.0,
                'balances' => 0.0,
                'refunds' => 0.0,
            ];
            $buckets[$row['bucket']]['refunds'] = (float) $row['refunds'];
        }

        ksort($buckets);
        return array_values(array_map(static function (array $row): array {
            $row['net'] = round($row['deposits'] + $row['balances'] - $row['refunds'], 2);
            return $row;
        }, $buckets));
    }

    private function getClinicCollectionComparison(array $filters): array {
        [$where, $params] = $this->settlementWhere($filters);
        $stmt = $this->conn->prepare("
            SELECT a.clinic_id, a.clinic_name, COUNT(*) AS settled_visits,
                COALESCE(SUM(GREATEST(COALESCE(payment.deposit_applied, 0), 0)), 0) AS deposits,
                COALESCE(SUM(LEAST(
                    GREATEST(COALESCE(payment.cash_received, 0), 0),
                    GREATEST(COALESCE(payment.remaining_balance, 0), 0)
                )), 0) AS balances
            FROM vw_appointment_overview a
            JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
            WHERE {$where}
            GROUP BY a.clinic_id, a.clinic_name
        ");
        $stmt->execute($params);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['clinic_id']] = [
                'clinic_id' => (int) $row['clinic_id'],
                'clinic_name' => $row['clinic_name'],
                'settled_visits' => (int) $row['settled_visits'],
                'deposits' => (float) $row['deposits'],
                'balances' => (float) $row['balances'],
                'refunds' => 0.0,
            ];
        }

        [$refundWhere, $refundParams] = $this->refundWhere($filters);
        $refundStmt = $this->conn->prepare("
            SELECT appointment.clinic_id, COALESCE(SUM(GREATEST(COALESCE(deposit.amount, 0), 0)), 0) AS refunds
            FROM appointment_deposits deposit
            JOIN appointments appointment ON appointment.appointment_id = deposit.appointment_id
            WHERE {$refundWhere}
            GROUP BY appointment.clinic_id
        ");
        $refundStmt->execute($refundParams);
        foreach ($refundStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $clinicId = (int) $row['clinic_id'];
            if (!isset($result[$clinicId])) {
                $clinicStmt = $this->conn->prepare('SELECT clinic_name FROM clinics WHERE clinic_id = :clinic_id');
                $clinicStmt->execute([':clinic_id' => $clinicId]);
                $result[$clinicId] = [
                    'clinic_id' => $clinicId,
                    'clinic_name' => $clinicStmt->fetchColumn() ?: 'Clinic',
                    'settled_visits' => 0,
                    'deposits' => 0.0,
                    'balances' => 0.0,
                    'refunds' => 0.0,
                ];
            }
            $result[$clinicId]['refunds'] = (float) $row['refunds'];
        }

        foreach ($result as &$row) {
            $gross = $row['deposits'] + $row['balances'];
            $row['net_collected'] = round($gross - $row['refunds'], 2);
            $row['average_per_visit'] = $row['settled_visits'] > 0
                ? round($row['net_collected'] / $row['settled_visits'], 2)
                : 0.0;
        }
        unset($row);
        usort($result, static fn(array $a, array $b): int => $b['net_collected'] <=> $a['net_collected']);
        return array_values($result);
    }

    private function getSettlementRecords(array $filters, int $page, int $perPage): array {
        [$where, $params] = $this->settlementWhere($filters);
        $countStmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM vw_appointment_overview a
            JOIN vw_appointment_payment_summary payment ON payment.appointment_id = a.appointment_id
            WHERE {$where}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->conn->prepare("
            SELECT a.appointment_id, a.firstname, a.lastname, a.date AS appointment_date,
                a.clinic_name,
                COALESCE((
                    SELECT GROUP_CONCAT(item.service_name_snapshot ORDER BY item.sort_order, item.billing_item_id SEPARATOR ', ')
                    FROM appointment_billing_items item
                    WHERE item.billing_id = payment.billing_id
                ), a.service_name) AS service_name,
                payment.actual_service_amount, payment.deposit_applied,
                payment.remaining_balance, payment.cash_received, payment.payment_status,
                payment.billing_recorded_at, payment.paid_at,
                payment.billing_notes, payment.billing_recorded_by
            FROM vw_appointment_overview a
            JOIN vw_appointment_payment_summary payment ON payment.appointment_id = a.appointment_id
            WHERE {$where}
            ORDER BY COALESCE(payment.paid_at, payment.billing_recorded_at) DESC, a.appointment_id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $items = array_map(static function (array $row): array {
            $amountDue = max(0, (float) ($row['remaining_balance'] ?? 0));
            $cash = max(0, (float) ($row['cash_received'] ?? 0));
            return [
                'appointment_id' => (int) $row['appointment_id'],
                'patient' => trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')),
                'appointment_date' => $row['appointment_date'],
                'settled_at' => $row['paid_at'] ?: $row['billing_recorded_at'],
                'clinic' => $row['clinic_name'],
                'services' => $row['service_name'] ?? '',
                'actual_charge' => (float) ($row['actual_service_amount'] ?? 0),
                'deposit_applied' => (float) ($row['deposit_applied'] ?? 0),
                'amount_due' => $amountDue,
                'cash_tendered' => $cash,
                'balance_collected' => min($cash, $amountDue),
                'change' => max(0, $cash - $amountDue),
                'payment_status' => $row['payment_status'] ?? '',
                'recorded_by' => $row['billing_recorded_by'] ?? '',
                'recorded_at' => $row['billing_recorded_at'] ?? '',
                'notes' => $row['billing_notes'] ?? '',
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    private function periodLabel(array $filters): string {
        if (empty($filters['date_from']) || empty($filters['date_to'])) return 'All settlement history';
        $from = date('M j, Y', strtotime($filters['date_from']));
        $to = date('M j, Y', strtotime($filters['date_to']));
        return $from === $to ? $from : "{$from}–{$to}";
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
