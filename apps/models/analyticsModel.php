<?php

class AnalyticsModel
{
    private PDO $conn;

    private const STATUS_ORDER = [
        'Pending Review',
        'Awaiting Deposit',
        'Payment Under Review',
        'Confirmed',
        'Checked In',
        'In Progress',
        'Completed',
        'Cancelled',
        'No-show',
        'Rejected',
    ];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public static function defaultFilters(): array
    {
        return [
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t'),
            'clinic_id' => null,
            'group_by' => 'auto',
        ];
    }

    public static function normalizeFilters(array $input): array
    {
        $filters = self::defaultFilters();

        foreach (['date_from', 'date_to'] as $field) {
            $value = trim((string) ($input[$field] ?? $filters[$field]));
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                throw new InvalidArgumentException('Please provide a valid analytics date range.');
            }
            $filters[$field] = $value;
        }

        if ($filters['date_from'] > $filters['date_to']) {
            throw new InvalidArgumentException('The start date cannot be later than the end date.');
        }

        $from = new DateTimeImmutable($filters['date_from']);
        $to = new DateTimeImmutable($filters['date_to']);
        if ($from->diff($to)->days > 1826) {
            throw new InvalidArgumentException('Analytics can cover a maximum of five years at a time.');
        }

        $clinicId = filter_var(
            $input['clinic_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $filters['clinic_id'] = $clinicId ?: null;

        $groupBy = trim((string) ($input['group_by'] ?? 'auto'));
        $filters['group_by'] = in_array($groupBy, ['auto', 'day', 'month'], true) ? $groupBy : 'auto';
        if ($filters['group_by'] === 'day' && $from->diff($to)->days > 366) {
            throw new InvalidArgumentException('Daily grouping can cover a maximum of one year. Use monthly grouping for longer periods.');
        }

        return $filters;
    }

    public function getClinics(): array
    {
        return $this->conn
            ->query('SELECT clinic_id, clinic_name FROM clinics ORDER BY clinic_name')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDashboardData(array $filters): array
    {
        $granularity = $this->granularity($filters);

        return [
            'filters' => $filters,
            'meta' => [
                'granularity' => $granularity,
                'generated_at' => date(DATE_ATOM),
                'definitions' => [
                    'appointments' => 'All appointment requests scheduled in the selected period.',
                    'completed' => 'Appointments currently marked Completed.',
                    'new_patients' => 'Patient records created in the selected period. A clinic filter includes patients with an appointment at that clinic.',
                    'utilization' => 'Active bookings divided by configured schedule capacity.',
                    'cancellation_rate' => 'Cancelled appointments divided by requests that reached an accepted stage.',
                    'no_show_rate' => 'No-shows divided by completed visits plus no-shows for dates up to today.',
                ],
            ],
            'kpis' => $this->getKpis($filters),
            'appointment_trend' => $this->getAppointmentTrend($filters, $granularity),
            'status_distribution' => $this->getStatusDistribution($filters),
            'top_services' => $this->getTopServices($filters),
            'clinic_comparison' => $this->getClinicComparison($filters),
            'patient_growth' => $this->getPatientGrowth($filters, $granularity),
        ];
    }

    public function getKpis(array $filters): array
    {
        [$where, $params] = $this->appointmentWhere($filters);
        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(a.status = 'Completed') AS completed,
                SUM(a.status = 'Cancelled') AS cancelled,
                SUM(a.status IN ('Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed','Cancelled','No-show')) AS accepted,
                SUM(a.status = 'No-show' AND a.date <= :today) AS no_show,
                SUM(a.status = 'Completed' AND a.date <= :today_for_completed) AS completed_outcomes
            FROM appointments a
            WHERE {$where}
        ";
        $params[':today'] = date('Y-m-d');
        $params[':today_for_completed'] = date('Y-m-d');
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $appointments = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $newPatients = $this->countNewPatients($filters);
        $utilization = $this->getUtilizationSummary($filters);
        $accepted = (int) ($appointments['accepted'] ?? 0);
        $outcomes = (int) ($appointments['no_show'] ?? 0) + (int) ($appointments['completed_outcomes'] ?? 0);

        return [
            'appointments' => (int) ($appointments['total'] ?? 0),
            'completed' => (int) ($appointments['completed'] ?? 0),
            'new_patients' => $newPatients,
            'utilization_rate' => $utilization['rate'],
            'capacity' => $utilization['capacity'],
            'booked' => $utilization['booked'],
            'cancellation_rate' => $accepted > 0
                ? round(((int) ($appointments['cancelled'] ?? 0) / $accepted) * 100, 1)
                : 0.0,
            'no_show_rate' => $outcomes > 0
                ? round(((int) ($appointments['no_show'] ?? 0) / $outcomes) * 100, 1)
                : 0.0,
        ];
    }

    public function getDrilldown(array $filters, string $dimension, string $value = '', int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(25, $perPage));
        $allowed = ['appointments', 'completed', 'cancelled', 'no_show', 'date', 'status', 'service', 'clinic', 'patients', 'patient_bucket', 'schedules'];
        if (!in_array($dimension, $allowed, true)) {
            throw new InvalidArgumentException('Invalid analytics drill-down request.');
        }

        if ($dimension === 'patients' || $dimension === 'patient_bucket') {
            return $this->getPatientDrilldown($filters, $dimension === 'patient_bucket' ? $value : '', $page, $perPage);
        }
        if ($dimension === 'schedules') {
            return $this->getScheduleDrilldown($filters, $page, $perPage);
        }

        return $this->getAppointmentDrilldown($filters, $dimension, $value, $page, $perPage);
    }

    private function getAppointmentTrend(array $filters, string $granularity): array
    {
        [$where, $params] = $this->appointmentWhere($filters);
        $bucketExpression = $granularity === 'month'
            ? "DATE_FORMAT(a.date, '%Y-%m-01')"
            : "DATE_FORMAT(a.date, '%Y-%m-%d')";

        $stmt = $this->conn->prepare("
            SELECT {$bucketExpression} AS bucket, COUNT(*) AS total
            FROM appointments a
            WHERE {$where}
            GROUP BY bucket
            ORDER BY bucket
        ");
        $stmt->execute($params);
        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['bucket']] = (int) $row['total'];
        }

        return $this->fillBuckets($filters, $granularity, $counts);
    }

    private function getStatusDistribution(array $filters): array
    {
        [$where, $params] = $this->appointmentWhere($filters);
        $stmt = $this->conn->prepare("
            SELECT a.status, COUNT(*) AS total
            FROM appointments a
            WHERE {$where}
            GROUP BY a.status
        ");
        $stmt->execute($params);

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        $result = [];
        foreach (self::STATUS_ORDER as $status) {
            $result[] = ['label' => $status, 'value' => $counts[$status] ?? 0];
            unset($counts[$status]);
        }
        foreach ($counts as $status => $value) {
            $result[] = ['label' => $status, 'value' => $value];
        }

        return $result;
    }

    private function getTopServices(array $filters): array
    {
        [$where, $params] = $this->appointmentWhere($filters);
        $stmt = $this->conn->prepare("
            SELECT s.service_id AS id, s.service_name AS label, COUNT(DISTINCT aps.appointment_id) AS value
            FROM appointment_services aps
            JOIN appointments a ON a.appointment_id = aps.appointment_id
            JOIN services s ON s.service_id = aps.service_id
            WHERE {$where}
            GROUP BY s.service_id, s.service_name
            ORDER BY value DESC, s.service_name
            LIMIT 6
        ");
        $stmt->execute($params);

        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'label' => $row['label'],
            'value' => (int) $row['value'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getClinicComparison(array $filters): array
    {
        $params = [
            ':date_from' => $filters['date_from'],
            ':date_to' => $filters['date_to'],
        ];
        $clinicWhere = '';
        if ($filters['clinic_id']) {
            $clinicWhere = 'WHERE c.clinic_id = :clinic_id';
            $params[':clinic_id'] = $filters['clinic_id'];
        }

        $stmt = $this->conn->prepare("
            SELECT
                c.clinic_id,
                c.clinic_name AS label,
                COUNT(DISTINCT a.appointment_id) AS appointments,
                COUNT(DISTINCT CASE WHEN a.status = 'Completed' THEN a.appointment_id END) AS completed,
                COALESCE(capacity.total_capacity, 0) AS capacity,
                COALESCE(capacity.total_booked, 0) AS booked
            FROM clinics c
            LEFT JOIN appointments a
                ON a.clinic_id = c.clinic_id
                AND a.date BETWEEN :date_from AND :date_to
            LEFT JOIN (
                SELECT clinic_id, SUM(capacity) AS total_capacity, SUM(booked) AS total_booked
                FROM vw_schedule_utilization
                WHERE sched_date BETWEEN :capacity_from AND :capacity_to
                GROUP BY clinic_id
            ) capacity ON capacity.clinic_id = c.clinic_id
            {$clinicWhere}
            GROUP BY c.clinic_id, c.clinic_name, capacity.total_capacity, capacity.total_booked
            ORDER BY appointments DESC, c.clinic_name
        ");
        $params[':capacity_from'] = $filters['date_from'];
        $params[':capacity_to'] = $filters['date_to'];
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            $capacity = (int) $row['capacity'];
            $booked = (int) $row['booked'];
            return [
                'id' => (int) $row['clinic_id'],
                'label' => $row['label'],
                'appointments' => (int) $row['appointments'],
                'completed' => (int) $row['completed'],
                'utilization_rate' => $capacity > 0 ? round(($booked / $capacity) * 100, 1) : 0.0,
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getPatientGrowth(array $filters, string $granularity): array
    {
        [$where, $params] = $this->patientWhere($filters);
        $bucketExpression = $granularity === 'month'
            ? "DATE_FORMAT(p.created_at, '%Y-%m-01')"
            : "DATE_FORMAT(p.created_at, '%Y-%m-%d')";

        $stmt = $this->conn->prepare("
            SELECT {$bucketExpression} AS bucket, COUNT(DISTINCT p.patient_id) AS total
            FROM patients p
            WHERE {$where}
            GROUP BY bucket
            ORDER BY bucket
        ");
        $stmt->execute($params);
        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['bucket']] = (int) $row['total'];
        }

        return $this->fillBuckets($filters, $granularity, $counts);
    }

    private function countNewPatients(array $filters): int
    {
        [$where, $params] = $this->patientWhere($filters);
        $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT p.patient_id) FROM patients p WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function getUtilizationSummary(array $filters): array
    {
        $params = [
            ':date_from' => $filters['date_from'],
            ':date_to' => $filters['date_to'],
        ];
        $clinic = '';
        if ($filters['clinic_id']) {
            $clinic = ' AND clinic_id = :clinic_id';
            $params[':clinic_id'] = $filters['clinic_id'];
        }

        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(capacity), 0) AS capacity, COALESCE(SUM(booked), 0) AS booked
            FROM vw_schedule_utilization
            WHERE sched_date BETWEEN :date_from AND :date_to {$clinic}
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $capacity = (int) ($row['capacity'] ?? 0);
        $booked = (int) ($row['booked'] ?? 0);

        return [
            'capacity' => $capacity,
            'booked' => $booked,
            'rate' => $capacity > 0 ? round(($booked / $capacity) * 100, 1) : 0.0,
        ];
    }

    private function appointmentWhere(array $filters): array
    {
        $where = 'a.date BETWEEN :date_from AND :date_to';
        $params = [
            ':date_from' => $filters['date_from'],
            ':date_to' => $filters['date_to'],
        ];
        if ($filters['clinic_id']) {
            $where .= ' AND a.clinic_id = :clinic_id';
            $params[':clinic_id'] = $filters['clinic_id'];
        }
        return [$where, $params];
    }

    private function patientWhere(array $filters): array
    {
        $where = 'p.created_at >= :patient_date_from AND p.created_at < DATE_ADD(:patient_date_to, INTERVAL 1 DAY)';
        $params = [
            ':patient_date_from' => $filters['date_from'],
            ':patient_date_to' => $filters['date_to'],
        ];
        if ($filters['clinic_id']) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM appointments patient_appointment
                WHERE patient_appointment.patient_id = p.patient_id
                  AND patient_appointment.clinic_id = :patient_clinic_id
            )';
            $params[':patient_clinic_id'] = $filters['clinic_id'];
        }
        return [$where, $params];
    }

    private function granularity(array $filters): string
    {
        if (($filters['group_by'] ?? 'auto') !== 'auto') {
            return $filters['group_by'];
        }
        $from = new DateTimeImmutable($filters['date_from']);
        $to = new DateTimeImmutable($filters['date_to']);
        return $from->diff($to)->days > 62 ? 'month' : 'day';
    }

    private function fillBuckets(array $filters, string $granularity, array $counts): array
    {
        $cursor = new DateTimeImmutable($filters['date_from']);
        $end = new DateTimeImmutable($filters['date_to']);
        if ($granularity === 'month') {
            $cursor = $cursor->modify('first day of this month');
            $end = $end->modify('first day of this month');
        }

        $rows = [];
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $rows[] = [
                'bucket' => $key,
                'label' => $granularity === 'month' ? $cursor->format('M Y') : $cursor->format('M j'),
                'value' => $counts[$key] ?? 0,
            ];
            $cursor = $cursor->modify($granularity === 'month' ? '+1 month' : '+1 day');
        }
        return $rows;
    }

    private function getAppointmentDrilldown(array $filters, string $dimension, string $value, int $page, int $perPage): array
    {
        [$where, $params] = $this->appointmentWhere($filters);
        $title = 'Appointments';

        if ($dimension === 'completed') {
            $where .= " AND a.status = 'Completed'";
            $title = 'Completed Visits';
        } elseif ($dimension === 'cancelled') {
            $where .= " AND a.status = 'Cancelled'";
            $title = 'Cancelled Appointments';
        } elseif ($dimension === 'no_show') {
            $where .= " AND a.status = 'No-show' AND a.date <= :drill_today";
            $params[':drill_today'] = date('Y-m-d');
            $title = 'No-show Appointments';
        } elseif ($dimension === 'status') {
            if (!in_array($value, array_merge(self::STATUS_ORDER, ['Pending', 'Awaiting Payment', 'Rescheduled']), true)) {
                throw new InvalidArgumentException('Invalid appointment status.');
            }
            $where .= ' AND a.status = :drill_status';
            $params[':drill_status'] = $value;
            $title = $value . ' Appointments';
        } elseif ($dimension === 'service') {
            $serviceId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$serviceId) throw new InvalidArgumentException('Invalid service selection.');
            $where .= ' AND EXISTS (
                SELECT 1 FROM appointment_services drill_service
                WHERE drill_service.appointment_id = a.appointment_id
                  AND drill_service.service_id = :drill_service_id
            )';
            $params[':drill_service_id'] = $serviceId;
            $name = $this->lookupName('services', 'service_id', 'service_name', (int) $serviceId);
            $title = ($name ?: 'Selected Service') . ' Appointments';
        } elseif ($dimension === 'clinic') {
            $clinicId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$clinicId) throw new InvalidArgumentException('Invalid clinic selection.');
            $where .= ' AND a.clinic_id = :drill_clinic_id';
            $params[':drill_clinic_id'] = $clinicId;
            $name = $this->lookupName('clinics', 'clinic_id', 'clinic_name', (int) $clinicId);
            $title = ($name ?: 'Selected Clinic') . ' Appointments';
        } elseif ($dimension === 'date') {
            [$bucketFrom, $bucketTo, $bucketLabel] = $this->bucketRange($value, $this->granularity($filters));
            $where .= ' AND a.date BETWEEN :drill_bucket_from AND :drill_bucket_to';
            $params[':drill_bucket_from'] = $bucketFrom;
            $params[':drill_bucket_to'] = $bucketTo;
            $title = 'Appointments · ' . $bucketLabel;
        }

        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM vw_appointment_overview a WHERE {$where}");
        $countStmt->execute($params);
        $pagination = $this->pagination((int) $countStmt->fetchColumn(), $page, $perPage);
        $offset = ($pagination['page'] - 1) * $pagination['per_page'];

        $stmt = $this->conn->prepare("
            SELECT a.appointment_id, a.date, a.patient_name, COALESCE(a.clinic_name, 'Unassigned') AS clinic_name,
                   COALESCE(a.service_name, 'No service') AS service_name, a.status
            FROM vw_appointment_overview a
            WHERE {$where}
            ORDER BY a.date DESC, a.appointment_id DESC
            LIMIT {$pagination['per_page']} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'title' => $title,
            'kind' => 'appointments',
            'columns' => ['Date', 'Patient', 'Clinic', 'Services', 'Status'],
            'rows' => array_map(static fn(array $row): array => [
                date('M j, Y', strtotime($row['date'])),
                $row['patient_name'],
                $row['clinic_name'],
                $row['service_name'],
                $row['status'],
            ], $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'pagination' => $pagination,
        ];
    }

    private function getPatientDrilldown(array $filters, string $bucket, int $page, int $perPage): array
    {
        $patientFilters = $filters;
        $title = 'New Patients';
        if ($bucket !== '') {
            [$patientFilters['date_from'], $patientFilters['date_to'], $label] = $this->bucketRange($bucket, $this->granularity($filters));
            $title .= ' · ' . $label;
        }
        [$where, $params] = $this->patientWhere($patientFilters);
        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM patients p WHERE {$where}");
        $countStmt->execute($params);
        $pagination = $this->pagination((int) $countStmt->fetchColumn(), $page, $perPage);
        $offset = ($pagination['page'] - 1) * $pagination['per_page'];

        $stmt = $this->conn->prepare("
            SELECT p.patient_id, CONCAT_WS(' ', p.firstname, NULLIF(p.middlename, ''), p.lastname) AS patient_name,
                   p.email, p.phone_number, p.created_at
            FROM patients p
            WHERE {$where}
            ORDER BY p.created_at DESC, p.patient_id DESC
            LIMIT {$pagination['per_page']} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'title' => $title,
            'kind' => 'patients',
            'columns' => ['Registered', 'Patient', 'Email', 'Contact'],
            'rows' => array_map(static fn(array $row): array => [
                date('M j, Y', strtotime($row['created_at'])),
                trim($row['patient_name']),
                $row['email'] ?: '—',
                $row['phone_number'] ?: '—',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'pagination' => $pagination,
        ];
    }

    private function getScheduleDrilldown(array $filters, int $page, int $perPage): array
    {
        $where = 'sched_date BETWEEN :date_from AND :date_to';
        $params = [':date_from' => $filters['date_from'], ':date_to' => $filters['date_to']];
        if ($filters['clinic_id']) {
            $where .= ' AND clinic_id = :schedule_clinic_id';
            $params[':schedule_clinic_id'] = $filters['clinic_id'];
        }
        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM vw_schedule_utilization WHERE {$where}");
        $countStmt->execute($params);
        $pagination = $this->pagination((int) $countStmt->fetchColumn(), $page, $perPage);
        $offset = ($pagination['page'] - 1) * $pagination['per_page'];

        $stmt = $this->conn->prepare("
            SELECT sched_date, start_time, end_time, clinic_name, capacity, booked, available_slots, utilization_rate
            FROM vw_schedule_utilization
            WHERE {$where}
            ORDER BY sched_date DESC, clinic_name
            LIMIT {$pagination['per_page']} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'title' => 'Schedule Utilization',
            'kind' => 'schedules',
            'columns' => ['Date', 'Window', 'Clinic', 'Capacity', 'Booked', 'Open', 'Utilization'],
            'rows' => array_map(static fn(array $row): array => [
                date('M j, Y', strtotime($row['sched_date'])),
                date('g:i A', strtotime($row['start_time'])) . '–' . date('g:i A', strtotime($row['end_time'])),
                $row['clinic_name'],
                (int) $row['capacity'],
                (int) $row['booked'],
                (int) $row['available_slots'],
                $row['utilization_rate'] . '%',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'pagination' => $pagination,
        ];
    }

    private function pagination(int $total, int $page, int $perPage): array
    {
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = $total > 0 ? min($page * $perPage, $total) : 0;
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function bucketRange(string $value, string $granularity): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid analytics period selection.');
        }
        if ($granularity === 'month') {
            return [$date->format('Y-m-01'), $date->format('Y-m-t'), $date->format('F Y')];
        }
        return [$value, $value, $date->format('F j, Y')];
    }

    private function lookupName(string $table, string $idColumn, string $nameColumn, int $id): string
    {
        $allowed = [
            'services' => ['service_id', 'service_name'],
            'clinics' => ['clinic_id', 'clinic_name'],
        ];
        if (($allowed[$table] ?? null) !== [$idColumn, $nameColumn]) return '';
        $stmt = $this->conn->prepare("SELECT {$nameColumn} FROM {$table} WHERE {$idColumn} = :lookup_id");
        $stmt->execute([':lookup_id' => $id]);
        return (string) ($stmt->fetchColumn() ?: '');
    }
}
