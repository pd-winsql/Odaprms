<?php
require_once '../../../config/conn.php';

$db = new Database();
$conn=$db->connect();

class Appointment {
    private $conn;
    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    public function bookAppointment($lastname, $firstname, $middlename, $age, $gender, 
    $phone_number, $email, $clinic, $service, $date, $time, $status = 'pending') {
        try {    
            $stmt = $this->conn->prepare("INSERT INTO appointments (lastname, firstname, middlename, age, gender, 
            phone_number, email, clinic, service, date, time, status) 
            VALUES (:lastname, :firstname, :middlename, :age, :gender, 
            :phone_number, :email, :clinic, :service, :date, :time, :status)");

            return $stmt->execute([
                ':lastname' => $lastname,
                ':firstname' => $firstname,
                ':middlename' => $middlename,
                ':age' => $age,
                ':gender' => $gender,
                ':phone_number' => $phone_number,
                ':email' => $email,
                ':clinic' => $clinic,
                ':service' => $service,
                ':date' => $date,
                ':time' => $time,
                ':status' => $status
            ]);
        } catch (PDOException $e) {
            error_log("addAppointment error: " . $e->getMessage());
            return false;
        }
    }

    // ===== PATIENT FUNCTIONS =====

    // Patient: view upcoming appointments
    public function getPatientUpcomingAppointments($email) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM appointments 
                WHERE email = :email or username = :email
                AND date >= CURDATE()
                ORDER BY date ASC, time ASC
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
                ORDER BY date DESC, time DESC
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
                    phone_number, email, clinic, service,
                    date, time, status
                FROM appointments 
                WHERE date >= CURDATE()
                AND (email = :email or username = :email)
                ORDER BY date ASC, time ASC
            ");
            $stmt->execute([':email' => $email]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getUpcomingWithStatus error: " . $e->getMessage());
            return [];
        }
    }

    // ===== ADMIN FUNCTIONS =====

    // Admin: view all past appointments
    public function getAdminPastAppointments() {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM appointments 
                WHERE date < CURDATE()
                ORDER BY date DESC, time DESC
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
                SELECT * FROM appointments 
                WHERE date < CURDATE()
                AND clinic = :clinic
                ORDER BY date DESC, time DESC
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
                SELECT lastname, firstname, middlename, age, gender,
                    phone_number, email, clinic, service,
                    date, time, status
                FROM appointments 
                WHERE date >= CURDATE()
                ORDER BY date ASC, time ASC
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
        $allowed = ['pending', 'confirmed', 'cancelled', 'completed'];

        if (!in_array($status, $allowed)) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE appointments 
                SET status = :status 
                WHERE id = :id
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
}