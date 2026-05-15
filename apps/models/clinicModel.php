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

    public function addClinic($name, $address, $phone, $image) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO clinics (clinic_name, clinic_address, clinic_contact, clinic_image) VALUES (:name, :address, :phone, :image)");
            return $stmt->execute([
                ':name' => $name,
                ':address' => $address,
                ':phone' => $phone,
                ':image' => $image
            ]);
        } catch (PDOException $e) {
            error_log("addClinic error: " . $e->getMessage());
            return false;
        }
    }

    public function updateClinic($id, $name, $address, $phone, $image) {
        try {
            $stmt = $this->conn->prepare("UPDATE clinics SET clinic_name = :name, clinic_address = :address, clinic_contact = :phone, clinic_image = :image WHERE clinic_id = :id");
            return $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':address' => $address,
                ':phone' => $phone,
                ':image' => $image
            ]);
        } catch (PDOException $e) {
            error_log("updateClinic error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteClinic($id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM clinics WHERE clinic_id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("deleteClinic error: " . $e->getMessage());
            return false;
        }
    }
}