<?php
require_once '../../../config/conn.php';

$db = new Database();
$conn=$db->connect();

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
            $stmt = $this->conn->prepare("SELECT * FROM clinics WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getClinicById error: " . $e->getMessage());
            return null;
        }
    }

    public function addClinic($name, $address, $phone, $image) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO clinics (name, address, phone, image) VALUES (:name, :address, :phone, :image)");
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
            $stmt = $this->conn->prepare("UPDATE clinics SET name = :name, address = :address, phone = :phone, image = :image WHERE id = :id");
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
            $stmt = $this->conn->prepare("DELETE FROM clinics WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("deleteClinic error: " . $e->getMessage());
            return false;
        }
    }

    public function getClinicForBookings() {
        try {
            $stmt = $this->conn->prepare("SELECT id, name FROM clinics");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getClinicForBookings error: " . $e->getMessage());
            return [];
        }
    }
}