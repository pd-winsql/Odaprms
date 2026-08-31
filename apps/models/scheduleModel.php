<?php

class Schedule {
    private $conn;
    private int $transitionMinutes;

    public function __construct($conn) 
    {
        $this->conn = $conn;
        $rules = require __DIR__ . '/../../config/appointment.php';
        $this->transitionMinutes = max(0, (int) ($rules['clinic_transition_minutes'] ?? 90));
    }

    public static function normalizeTime(string $time): ?string {
        $time = trim($time);
        foreach (['!H:i', '!H:i:s'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $time);
            if ($parsed && $parsed->format($format === '!H:i' ? 'H:i' : 'H:i:s') === $time) {
                return $parsed->format('H:i:s');
            }
        }
        return null;
    }

    public static function usesFiveMinuteIncrement(string $time): bool {
        $normalized = self::normalizeTime($time);
        return $normalized !== null && ((int) substr($normalized, 3, 2)) % 5 === 0;
    }

    public static function formatTimeRange(?string $startTime, ?string $endTime): string {
        if (!$startTime || !$endTime) return '';
        return date('g:i A', strtotime($startTime)) . '–' . date('g:i A', strtotime($endTime));
    }

    public function getTransitionMinutes(): int {
        return $this->transitionMinutes;
    }

    public function getSchedulesByClinic($clinic_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM schedules
                WHERE clinic_id = :clinic_id
            ");

            $stmt->execute([
                ':clinic_id' => $clinic_id
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getSchedulesByClinic error: " . $e->getMessage());
            return [];
        }
    }

    public function getAllSchedules() {
        try {
            $stmt = $this->conn->prepare("
                SELECT schedule_id, clinic_id, sched_date, start_time, end_time,
                    capacity AS max_appointments, clinic_name
                FROM vw_schedule_utilization
                ORDER BY sched_date ASC, start_time ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAllSchedules error: " . $e->getMessage());
            return [];
        }
    }

    public function getUpcomingSchedulesByClinic($clinic_id) {
        $stmt = $this->conn->prepare("
            SELECT * FROM schedules
            WHERE clinic_id = :clinic_id 
            AND TIMESTAMP(sched_date, start_time) >= NOW()
            ORDER BY sched_date ASC, start_time ASC
        ");
        $stmt->execute([':clinic_id' => $clinic_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailableSchedulesByClinic($clinic_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    schedule_id,
                    clinic_id,
                    sched_date,
                    start_time,
                    end_time,
                    capacity AS max_appointments,
                    booked AS total_appointments,
                    available_slots
                FROM vw_schedule_utilization
                WHERE clinic_id = :clinic_id
                  AND TIMESTAMP(sched_date, start_time) >= NOW()
                ORDER BY sched_date ASC, start_time ASC
            ");
            $stmt->execute([':clinic_id' => $clinic_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAvailableSchedulesByClinic error: " . $e->getMessage());
            return [];
        }
    }

    public function existsScheduleForClinicDate($clinicId, $schedDate, $excludeScheduleId = null): bool
    {
        try {
            $sql = 'SELECT COUNT(*) FROM schedules WHERE clinic_id = :clinic_id AND sched_date = :sched_date';
            $params = [':clinic_id' => $clinicId, ':sched_date' => $schedDate];
            if ($excludeScheduleId) {
                $sql .= ' AND schedule_id != :exclude_id';
                $params[':exclude_id'] = $excludeScheduleId;
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("existsScheduleForClinicDate error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Returns the first schedule that violates either the one-window-per-
     * clinic rule or the required transition time between different clinics.
     */
    public function findWindowConflict(
        int $clinicId,
        string $schedDate,
        string $startTime,
        string $endTime,
        ?int $excludeScheduleId = null,
        bool $lockRows = false
    ): ?array {
        $sql = 'SELECT schedule_row.schedule_id, schedule_row.clinic_id,
                    schedule_row.sched_date, schedule_row.start_time, schedule_row.end_time,
                    clinic.clinic_name
                FROM schedules schedule_row
                JOIN clinics clinic ON clinic.clinic_id = schedule_row.clinic_id
                WHERE schedule_row.sched_date = :sched_date';
        $params = [':sched_date' => $schedDate];
        if ($excludeScheduleId) {
            $sql .= ' AND schedule_row.schedule_id != :exclude_id';
            $params[':exclude_id'] = $excludeScheduleId;
        }
        $sql .= ' ORDER BY schedule_row.start_time';
        if ($lockRows) $sql .= ' FOR UPDATE';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $candidateStart = strtotime("1970-01-01 {$startTime} UTC");
        $candidateEnd = strtotime("1970-01-01 {$endTime} UTC");
        $gapSeconds = $this->transitionMinutes * 60;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $existing) {
            if ((int) $existing['clinic_id'] === $clinicId) return $existing;
            $existingStart = strtotime('1970-01-01 ' . $existing['start_time'] . ' UTC');
            $existingEnd = strtotime('1970-01-01 ' . $existing['end_time'] . ' UTC');
            $separated = $candidateStart >= $existingEnd + $gapSeconds
                || $existingStart >= $candidateEnd + $gapSeconds;
            if (!$separated) return $existing;
        }
        return null;
    }

    /**
     * Inserts a validated group of schedules as one database transaction.
     * Each item must contain sched_date and max_appointments.
     */
    public function addSchedules($clinic_id, array $schedules): array {
        try {
            $this->conn->beginTransaction();
            $insert = $this->conn->prepare(
                'INSERT INTO schedules (clinic_id, sched_date, start_time, end_time, max_appointments)
                 VALUES (:clinic_id, :sched_date, :start_time, :end_time, :max_appointments)'
            );

            foreach ($schedules as $schedule) {
                $conflict = $this->findWindowConflict(
                    (int) $clinic_id,
                    $schedule['sched_date'],
                    $schedule['start_time'],
                    $schedule['end_time'],
                    null,
                    true
                );
                if ($conflict) {
                    $this->conn->rollBack();
                    return ['success' => false, 'conflict' => $conflict];
                }
                $insert->execute([
                    ':clinic_id' => $clinic_id,
                    ':sched_date' => $schedule['sched_date'],
                    ':start_time' => $schedule['start_time'],
                    ':end_time' => $schedule['end_time'],
                    ':max_appointments' => $schedule['max_appointments'],
                ]);
            }

            $this->conn->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('addSchedules error: ' . $e->getMessage());
            return ['success' => false];
        }
    }

    public function updateScheduleWindow(
        int $scheduleId,
        int $clinicId,
        string $schedDate,
        string $startTime,
        string $endTime,
        int $maxAppointments
    ): array {
        try {
            $this->conn->beginTransaction();
            $booked = $this->getBookedCountForSchedule($scheduleId, true);
            if ($booked < 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Unable to verify existing bookings.'];
            }
            if ($booked > 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Clinic, date, and time are locked because this schedule has bookings.'];
            }
            $conflict = $this->findWindowConflict($clinicId, $schedDate, $startTime, $endTime, $scheduleId, true);
            if ($conflict) {
                $this->conn->rollBack();
                return ['success' => false, 'conflict' => $conflict];
            }
            $stmt = $this->conn->prepare("UPDATE schedules
                SET clinic_id = :clinic_id, sched_date = :sched_date, start_time = :start_time,
                    end_time = :end_time, max_appointments = :max_appointments
                WHERE schedule_id = :schedule_id");
            $stmt->execute([
                ':schedule_id' => $scheduleId,
                ':clinic_id' => $clinicId,
                ':sched_date' => $schedDate,
                ':start_time' => $startTime,
                ':end_time' => $endTime,
                ':max_appointments' => $maxAppointments
            ]);
            $this->conn->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log("updateScheduleWindow error: " . $e->getMessage());
            return ['success' => false];
        }
    }

    public function deleteSchedule($schedule_id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM schedules WHERE schedule_id = :schedule_id");
            return $stmt->execute([':schedule_id' => $schedule_id]);
        } catch (PDOException $e) {
            error_log("deleteSchedule error: " . $e->getMessage());
            return false;
        }
    }

    public function updateMaxAppointments($schedule_id, $max_appointments) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE schedules SET max_appointments = :max
                WHERE schedule_id = :id
            ");
            return $stmt->execute([
                ':max' => $max_appointments,
                ':id'  => $schedule_id,
            ]);
        } catch (PDOException $e) {
            error_log("updateMaxAppointments error: " . $e->getMessage());
            return false;
        }
    }

    public function getBookedCountForSchedule($schedule_id, bool $lockSchedule = false): int {
        try {
            if ($lockSchedule) {
                $lock = $this->conn->prepare('SELECT schedule_id FROM schedules WHERE schedule_id = :schedule_id FOR UPDATE');
                $lock->execute([':schedule_id' => $schedule_id]);
                if (!$lock->fetchColumn()) return -1;
            }
            $stmt = $this->conn->prepare("
                SELECT booked
                FROM vw_schedule_utilization
                WHERE schedule_id = :schedule_id
            ");
            $stmt->execute([':schedule_id' => $schedule_id]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('getBookedCountForSchedule error: ' . $e->getMessage());
            return -1;
        }
    }

    public function getScheduleById($schedule_id) {
        try {

            $stmt = $this->conn->prepare("
                SELECT *
                FROM schedules
                WHERE schedule_id = :schedule_id
            ");

            $stmt->execute([
                ':schedule_id' => $schedule_id
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            error_log("getScheduleById error: " . $e->getMessage());

            return false;
        }
    }
}
