<?php

class Clinic {
    private $conn;
    public function __construct($conn) 
    {
        $this->conn = $conn;        
    }

    public function getAllClinics() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM clinics");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAllClinics error: " . $e->getMessage());
            return [];
        }
    }

    public function getClinicById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM clinics WHERE clinic_id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getClinicById error: " . $e->getMessage());
            return null;
        }
    }

    public function updateClinic($id, $name, $address, $embedUrl) {
        try {
            $stmt = $this->conn->prepare("UPDATE clinics SET clinic_name = :name, clinic_address = :address, embed_url = :embed_url WHERE clinic_id = :id");
            return $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':address' => $address,
                ':embed_url' => $embedUrl
            ]);
        } catch (PDOException $e) {
            error_log("updateClinic error: " . $e->getMessage());
            return false;
        }
    }

    public function updateDefaultHours(int $id, string $startTime, string $endTime): bool {
        try {
            $stmt = $this->conn->prepare("
                UPDATE clinics
                SET default_start_time = :start_time, default_end_time = :end_time
                WHERE clinic_id = :id
            ");
            return $stmt->execute([
                ':id' => $id,
                ':start_time' => $startTime,
                ':end_time' => $endTime,
            ]) && $stmt->rowCount() >= 0;
        } catch (PDOException $e) {
            error_log('updateDefaultHours error: ' . $e->getMessage());
            return false;
        }
    }

    public function clinicNameExists(string $name): bool {
        $stmt = $this->conn->prepare("SELECT 1 FROM clinics WHERE LOWER(TRIM(clinic_name)) = LOWER(TRIM(:name)) LIMIT 1");
        $stmt->execute([':name' => $name]);
        return (bool) $stmt->fetchColumn();
    }

    public function createClinic(string $name, string $address, ?string $embedUrl): int {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO clinics (clinic_name, clinic_address, clinic_contact, embed_url)
                VALUES (:name, :address, '', :embed_url)
            ");
            $stmt->execute([
                ':name' => $name,
                ':address' => $address,
                ':embed_url' => $embedUrl,
            ]);
            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log('createClinic error: ' . $e->getMessage());
            return 0;
        }
    }

}
