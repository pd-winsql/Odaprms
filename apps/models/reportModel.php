<?php

class ReportModel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public static function defaultFilters()
    {
        return [
            'report_type' => 'appointments',
            'date_from'   => date('Y-m-01'),
            'date_to'     => date('Y-m-t'),
            'clinic_id'   => null,
            'service_id'  => null,
            'status'      => '',
        ];
    }

    public static function normalizeFilters($input)
    {
        $filters = self::defaultFilters();
        $types = ['appointments', 'utilization'];
        $statuses = ['Pending Review', 'Awaiting Deposit', 'Payment Under Review', 'Confirmed', 'Checked In', 'In Progress', 'Completed', 'Cancelled', 'No-show', 'Rejected'];

        $type = $input['report_type'] ?? $filters['report_type'];
        $filters['report_type'] = in_array($type, $types, true) ? $type : 'appointments';

        foreach (['date_from', 'date_to'] as $field) {
            $value = trim($input[$field] ?? $filters[$field]);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                throw new InvalidArgumentException('Please provide a valid report date range.');
            }
            $filters[$field] = $value;
        }

        if ($filters['date_from'] > $filters['date_to']) {
            throw new InvalidArgumentException('The start date cannot be later than the end date.');
        }

        foreach (['clinic_id', 'service_id'] as $field) {
            $value = filter_var($input[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $filters[$field] = $value ?: null;
        }

        $status = trim($input['status'] ?? '');
        $filters['status'] = in_array($status, $statuses, true) ? $status : '';

        // Service and status do not apply to schedule-capacity reporting.
        if ($filters['report_type'] === 'utilization') {
            $filters['service_id'] = null;
            $filters['status'] = '';
        }

        return $filters;
    }

    public function getClinics()
    {
        $stmt = $this->conn->query('SELECT clinic_id, clinic_name FROM clinics ORDER BY clinic_name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getServices()
    {
        $stmt = $this->conn->query(
            'SELECT service_id, service_name FROM services WHERE is_active = 1 ORDER BY display_order, service_name'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAppointmentReport($filters)
    {
        $conditions = ['a.date BETWEEN :date_from AND :date_to'];
        $params = [
            ':date_from' => $filters['date_from'],
            ':date_to'   => $filters['date_to'],
        ];

        if ($filters['clinic_id']) {
            $conditions[] = 'a.clinic_id = :clinic_id';
            $params[':clinic_id'] = $filters['clinic_id'];
        }
        if ($filters['status'] !== '') {
            $conditions[] = 'a.status = :status';
            $params[':status'] = $filters['status'];
        }
        if ($filters['service_id']) {
            $conditions[] = 'EXISTS (
                SELECT 1 FROM appointment_services service_filter
                WHERE service_filter.appointment_id = a.appointment_id
                  AND service_filter.service_id = :service_id
            )';
            $params[':service_id'] = $filters['service_id'];
        }

        $sql = "
            SELECT
                a.appointment_id,
                a.patient_name,
                COALESCE(a.clinic_name, 'Unassigned') AS clinic_name,
                COALESCE(a.service_name, 'No service') AS service_name,
                a.date,
                a.start_time,
                a.end_time,
                a.status
            FROM vw_appointment_overview a
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY a.date DESC, a.appointment_id DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClinicUtilizationReport($filters)
    {
        $params = [
            ':date_from' => $filters['date_from'],
            ':date_to'   => $filters['date_to'],
        ];
        $clinicCondition = '';
        if ($filters['clinic_id']) {
            $clinicCondition = 'WHERE c.clinic_id = :clinic_id';
            $params[':clinic_id'] = $filters['clinic_id'];
        }

        $sql = "
            SELECT
                c.clinic_id,
                c.clinic_name,
                COUNT(schedule_totals.schedule_id) AS scheduled_days,
                COALESCE(SUM(schedule_totals.capacity), 0) AS capacity,
                COALESCE(SUM(schedule_totals.booked), 0) AS booked,
                COALESCE(SUM(schedule_totals.completed), 0) AS completed,
                COALESCE(SUM(schedule_totals.cancelled), 0) AS cancelled,
                GREATEST(
                    COALESCE(SUM(schedule_totals.capacity), 0) - COALESCE(SUM(schedule_totals.booked), 0),
                    0
                ) AS available_slots,
                CASE
                    WHEN COALESCE(SUM(schedule_totals.capacity), 0) = 0 THEN 0
                    ELSE ROUND(
                        COALESCE(SUM(schedule_totals.booked), 0) * 100 /
                        SUM(schedule_totals.capacity), 1
                    )
                END AS utilization_rate
            FROM clinics c
            LEFT JOIN vw_schedule_utilization schedule_totals
                ON schedule_totals.clinic_id = c.clinic_id
                AND schedule_totals.sched_date BETWEEN :date_from AND :date_to
            {$clinicCondition}
            GROUP BY c.clinic_id, c.clinic_name
            ORDER BY utilization_rate DESC, c.clinic_name
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
