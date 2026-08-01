<?php

class Appointment {
    private $conn;

    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    private function serviceNameSelect() {
        return "(
            SELECT GROUP_CONCAT(ms.service_name ORDER BY ms.display_order, ms.service_name SEPARATOR ', ')
            FROM appointment_services aps
            JOIN services ms ON ms.service_id = aps.service_id
            WHERE aps.appointment_id = a.appointment_id
        )";
    }

    public function bookAppointment($patient_id, $clinic_id, $service_ids, $date, $schedule_id, $status = 'Pending') {
        $service_ids = array_values(array_unique(array_filter(array_map('intval', (array) $service_ids))));
        if (empty($service_ids)) return false;

        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("
                INSERT INTO appointments
                (
                    patient_id,
                    clinic_id,
                    date,
                    schedule_id,
                    status
                )
                VALUES
                (
                    :patient_id,
                    :clinic_id,
                    :date,
                    :schedule_id,
                    :status
                )
            ");
            $inserted = $stmt->execute([
                ':patient_id' => $patient_id,
                ':clinic_id' => $clinic_id,
                ':date' => $date,
                ':schedule_id' => $schedule_id,
                ':status' => $status
            ]);

            if (!$inserted) {
                $this->conn->rollBack();
                return false;
            }

            $appointment_id = (int) $this->conn->lastInsertId();
            $link = $this->conn->prepare("
                INSERT INTO appointment_services (appointment_id, service_id)
                VALUES (:appointment_id, :service_id)
            ");
            foreach ($service_ids as $service_id) {
                $link->execute([
                    ':appointment_id' => $appointment_id,
                    ':service_id' => $service_id,
                ]);
            }

            $this->conn->commit();
            return $appointment_id;
        } catch(PDOException $e){
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log("bookAppointment error: ".$e->getMessage());
            return false;
        }
    }

    // ===== PATIENT FUNCTIONS =====

    // Patient: view upcoming appointments
    public function getPatientUpcomingAppointments($patient_id) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT
                a.*,
                {$serviceName} AS service_name,
                c.clinic_name
                FROM appointments a
                LEFT JOIN clinics c
                ON a.clinic_id = c.clinic_id
                WHERE a.patient_id = :patient_id
                AND a.date >= CURDATE()

                ORDER BY a.date ASC
            ");
            $stmt->execute([':patient_id' => $patient_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getPatientUpcomingAppointments error: " . $e->getMessage());
            return [];
        }
    }

    // Patient: view past appointments
    public function getPatientPastAppointments($patient_id) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT
                a.*,
                {$serviceName} AS service_name,
                c.clinic_name
                FROM appointments a
                LEFT JOIN clinics c
                ON a.clinic_id = c.clinic_id
                WHERE a.patient_id = :patient_id
                AND a.date < CURDATE()

                ORDER BY a.date ASC
            ");
            $stmt->execute([':patient_id' => $patient_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getPatientPastAppointments error: " . $e->getMessage());
            return [];
        }
    }

    // Patient: view upcoming appointments with status
    public function getUpcomingWithStatus($email) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT
                    p.lastname,
                    p.firstname,
                    p.email,
                    c.clinic_name,
                    {$serviceName} AS service_name,
                    a.date,
                    a.status
                FROM appointments a
                LEFT JOIN patients p
                ON a.patient_id = p.patient_id
                LEFT JOIN clinics c
                ON a.clinic_id = c.clinic_id
                WHERE p.email = :email
                AND a.date >= CURDATE()
                ORDER BY a.date ASC
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
                WHERE a.date < CURDATE() 
                AND status = 'Confirmed'
            ");
            $stmt->execute();

            // Update Pending appointments to Cancelled if date is in the past
            $stmt = $this->conn->prepare("
                UPDATE appointments 
                SET status = 'Cancelled'
                WHERE a.date < CURDATE() 
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

            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, p.lastname, p.firstname, p.middlename, p.age, p.gender,
                    p.phone_number, p.email, c.clinic_name, {$serviceName} AS service_name,
                    a.date, a.status
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                WHERE a.date < CURDATE()
                ORDER BY a.date DESC
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
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT a.*, p.lastname, p.firstname, p.middlename, p.age, p.gender,
                    p.phone_number, p.email, c.clinic_name, {$serviceName} AS service_name
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
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
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, p.lastname, p.firstname, p.middlename, p.age, p.gender,
                    p.phone_number, p.email, c.clinic_name, {$serviceName} AS service_name,
                    a.date, a.status
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                WHERE a.date >= CURDATE()
                ORDER BY a.date ASC, a.status ASC, a.created_at ASC
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

    public function countAppointmentsBySchedule($schedule_id)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE schedule_id = :schedule_id
            AND status != 'Cancelled'
        ");

        $stmt->execute([
            ':schedule_id' => $schedule_id
        ]);

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function getAppointmentsByStatus($status) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT a.appointment_id, p.lastname, p.firstname, p.middlename, p.age, p.gender,
                    p.phone_number, p.email, c.clinic_name, {$serviceName} AS service_name, a.date, a.status
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                WHERE a.date >= CURDATE()
                AND a.status = :status
                ORDER BY a.date ASC
            ");

            $stmt->execute([
                ':status' => $status
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getAppointmentsByStatus error: " . $e->getMessage());
            return [];
        }
    }

    public function getPatientTransactionHistory($patient_id) {
        try {
            $serviceName = $this->serviceNameSelect();
            $stmt = $this->conn->prepare("
                SELECT
                    a.appointment_id,
                    {$serviceName} AS service_name,
                    a.date,
                    a.status,
                    c.clinic_name
                FROM appointments a
                LEFT JOIN clinics c ON a.clinic_id = c.clinic_id
                WHERE a.patient_id = :patient_id
                ORDER BY a.date DESC
            ");
            $stmt->execute([':patient_id' => $patient_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getPatientTransactionHistory error: " . $e->getMessage());
            return [];
        }
    }
}
