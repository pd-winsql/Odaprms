<?php

class Schedule {
    private $conn;
    public function __construct($conn) 
    {
        $this->conn = $conn;        
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
                SELECT schedule_id, clinic_id, sched_date, capacity AS max_appointments, clinic_name
                FROM vw_schedule_utilization
                ORDER BY sched_date ASC
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
            AND sched_date >= CURDATE()
            ORDER BY sched_date ASC
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
                    capacity AS max_appointments,
                    booked AS total_appointments,
                    available_slots
                FROM vw_schedule_utilization
                WHERE clinic_id = :clinic_id
                  AND sched_date >= CURDATE()
                ORDER BY sched_date ASC
            ");
            $stmt->execute([':clinic_id' => $clinic_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAvailableSchedulesByClinic error: " . $e->getMessage());
            return [];
        }
    }

    public function existsScheduleOnDate($sched_date, $exclude_schedule_id = null)
    {
        try {
            if ($exclude_schedule_id) {
                $stmt = $this->conn->prepare("SELECT COUNT(*) FROM schedules WHERE sched_date = :sched_date AND schedule_id != :exclude_id");
                $stmt->execute([':sched_date' => $sched_date, ':exclude_id' => $exclude_schedule_id]);
            } else {
                $stmt = $this->conn->prepare("SELECT COUNT(*) FROM schedules WHERE sched_date = :sched_date");
                $stmt->execute([':sched_date' => $sched_date]);
            }
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("existsScheduleOnDate error: " . $e->getMessage());
            return false;
        }
    }

    public function addSchedule($clinic_id, $sched_date, $max_appointments) {
        try {
            // Prevent overlapping schedules across all clinics: same date cannot be reused.
            if ($this->existsScheduleOnDate($sched_date)) {
                return false;
            }

            $stmt = $this->conn->prepare("INSERT INTO schedules (clinic_id, sched_date, max_appointments) VALUES (:clinic_id, :sched_date, :max_appointments)");
            return $stmt->execute([
                ':clinic_id' => $clinic_id,
                ':sched_date' => $sched_date,
                ':max_appointments' => $max_appointments
            ]);
        } catch (PDOException $e) {
            error_log("addSchedule error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Inserts a validated group of schedules as one database transaction.
     * Each item must contain sched_date and max_appointments.
     */
    public function addSchedules($clinic_id, array $schedules): bool {
        try {
            // Keep a bulk submission atomic so partial schedules are never saved.
            $this->conn->beginTransaction();
            $insert = $this->conn->prepare(
                'INSERT INTO schedules (clinic_id, sched_date, max_appointments)
                 VALUES (:clinic_id, :sched_date, :max_appointments)'
            );

            // Reuse one prepared statement for each validated schedule row.
            foreach ($schedules as $schedule) {
                $insert->execute([
                    ':clinic_id' => $clinic_id,
                    ':sched_date' => $schedule['sched_date'],
                    ':max_appointments' => $schedule['max_appointments'],
                ]);
            }

            return $this->conn->commit();
        } catch (PDOException $e) {
            // Undo any rows already inserted when one row fails.
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('addSchedules error: ' . $e->getMessage());
            return false;
        }
    }

    public function updateSchedule($schedule_id, $clinic_id, $sched_date, $max_appointments) {
        try {
            $stmt = $this->conn->prepare("UPDATE schedules SET clinic_id = :clinic_id, sched_date = :sched_date, max_appointments = :max_appointments WHERE schedule_id = :schedule_id");
            return $stmt->execute([
                ':schedule_id' => $schedule_id,
                ':clinic_id' => $clinic_id,
                ':sched_date' => $sched_date,
                ':max_appointments' => $max_appointments
            ]);
        } catch (PDOException $e) {
            error_log("updateSchedule error: " . $e->getMessage());
            return false;
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

    public function getBookedCountForSchedule($schedule_id): int {
        try {
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
