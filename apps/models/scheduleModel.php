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
            $stmt = $this->conn->prepare("SELECT s.*, c.clinic_name FROM schedules s JOIN clinics c ON s.clinic_id = c.clinic_id ORDER BY s.sched_date ASC");
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
                    s.schedule_id,
                    s.clinic_id,
                    s.sched_date,
                    s.max_appointments,

                    COUNT(a.appointment_id) AS total_appointments,

                    (s.max_appointments - COUNT(a.appointment_id)) AS available_slots

                FROM schedules s

                LEFT JOIN appointments a
                    ON s.schedule_id = a.schedule_id
                    AND a.status IN ('Pending', 'Confirmed', 'Completed')

                WHERE s.clinic_id = :clinic_id

                GROUP BY
                    s.schedule_id,
                    s.clinic_id,
                    s.sched_date,
                    s.max_appointments

                ORDER BY s.sched_date ASC
            ");
            $stmt->execute([':clinic_id' => $clinic_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAvailableSchedulesByClinic error: " . $e->getMessage());
            return [];
        }
    }

    public function addSchedule($clinic_id, $sched_date, $max_appointments) {
        try {
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