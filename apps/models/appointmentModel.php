<?php

class Appointment {
    private $conn;
    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    public function bookAppointment($patient_id, $clinic_id, $service, $date, $schedule_id, $status = 'Pending') {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO appointments
                (
                    patient_id,
                    clinic_id,
                    service,
                    date,
                    schedule_id,
                    status
                )
                VALUES
                (
                    :patient_id,
                    :clinic_id,
                    :service,
                    :date,
                    :schedule_id,
                    :status
                )
            ");
            return $stmt->execute([
                ':patient_id' => $patient_id,
                ':clinic_id' => $clinic_id,
                ':service' => $service,
                ':date' => $date,
                ':schedule_id' => $schedule_id,
                ':status' => $status
            ]);
        } catch(PDOException $e){
            error_log("bookAppointment error: ".$e->getMessage());
            return false;
        }
    }

    // ===== PATIENT FUNCTIONS =====

    // Patient: view upcoming appointments
    public function getPatientUpcomingAppointments($email) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM appointments 
                WHERE email = :email
                AND date >= CURDATE()
                ORDER BY date ASC
            ");
            $stmt->execute([':email' => $email]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getPatientUpcomingAppointments error: " . $e->getMessage());
            return [];
        }
    }

    // Patient: view past appointments
    public function getPatientPastAppointments($email) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM appointments 
                WHERE email = :email
                AND date < CURDATE()
                ORDER BY date DESC
            ");
            $stmt->execute([':email' => $email]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getPatientPastAppointments error: " . $e->getMessage());
            return [];
        }
    }

    // Patient: view upcoming appointments with status
    public function getUpcomingWithStatus($email) {
        try {
            $stmt = $this->conn->prepare("
                SELECT lastname, firstname, middlename, age, gender,
                    phone_number, email, clinic_id, service,
                    date, status
                FROM appointments 
                WHERE date >= CURDATE()
                AND email = :email
                ORDER BY date ASC
            ");
            $stmt->execute([':email' => $email]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getUpcomingWithStatus error: " . $e->getMessage());
            return [];
        }
    }

    // ===== ADMIN FUNCTIONS =====

    // Auto-update statuses for past appointments
    public function autoUpdatePastAppointmentStatuses() {
        try {
            // Update Confirmed appointments to Completed if date is in the past
            $stmt = $this->conn->prepare("
                UPDATE appointments 
                SET status = 'Completed'
                WHERE date < CURDATE() 
                AND status = 'Confirmed'
            ");
            $stmt->execute();

            // Update Pending appointments to Cancelled if date is in the past
            $stmt = $this->conn->prepare("
                UPDATE appointments 
                SET status = 'Cancelled'
                WHERE date < CURDATE() 
                AND status = 'Pending'
            ");
            $stmt->execute();

        } catch (PDOException $e) {
            error_log("autoUpdatePastAppointmentStatuses error: " . $e->getMessage());
        }
    }

    // Admin: view all past appointments
    public function getAdminPastAppointments() {
        try {
            // Auto-update statuses before fetching
            $this->autoUpdatePastAppointmentStatuses();

            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, p.lastname, p.firstname, p.middlename, p.age, p.gender,
                    p.phone_number, p.email, c.clinic_name, a.service,
                    a.date, a.status
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id 
                WHERE date < CURDATE()
                ORDER BY date DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getAdminPastAppointments error: " . $e->getMessage());
            return [];
        }
    }

    // Admin: view past appointments per clinic
    public function getAdminPastAppointmentsByClinic($clinic) {
        try {
            $stmt = $this->conn->prepare("
                SELECT a.*, p.lastname, p.firstname, p.middlename, p.age, p.gender, p.phone_number, p.email, c.clinic_name
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                WHERE a.date < CURDATE()
                AND c.clinic_name = :clinic
                ORDER BY a.date DESC
            ");
            $stmt->execute([':clinic' => $clinic]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getAdminPastAppointmentsByClinic error: " . $e->getMessage());
            return [];
        }
    }

    // Admin: view all upcoming appointments with status
    public function getAllUpcomingWithStatus() {
        try {
            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, p.lastname, p.firstname, p.middlename, p.age, p.gender,
                    p.phone_number, p.email, c.clinic_name, a.service,
                    a.date, a.status
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                WHERE date >= CURDATE()
                ORDER BY date ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getAllUpcomingWithStatus error: " . $e->getMessage());
            return [];
        }
    }

    // Admin: update appointment status
    public function updateAppointmentStatus($appointment_id, $status) {
        $allowed = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];

        if (!in_array($status, $allowed)) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE appointments 
                SET status = :status 
                WHERE appointment_id = :id
            ");
            return $stmt->execute([
                ':status' => $status,
                ':id'     => $appointment_id,
            ]);

        } catch (PDOException $e) {
            error_log("updateAppointmentStatus error: " . $e->getMessage());
            return false;
        }
    }

        public function getLastInsertedId() {
        return $this->conn->lastInsertId();
    }
}