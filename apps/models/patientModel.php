<?php

class Patient {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getPatient($patient_id) {
        $stmt = $this->conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePatient($patient_id, $data) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
        }
        $sql = "UPDATE patients SET " . implode(', ', $fields) . " WHERE patient_id = ?";
        $stmt = $this->conn->prepare($sql);
        $values = array_values($data);
        $values[] = $patient_id;
        return $stmt->execute($values);
    }

    public function deletePatient($patient_id) {
        $stmt = $this->conn->prepare("DELETE FROM patients WHERE patient_id = ?");
        return $stmt->execute([$patient_id]);
    }

    public function getAllPatients() {
        $stmt = $this->conn->query("SELECT * FROM patients");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchPatients($query) {
        $stmt = $this->conn->prepare("SELECT * FROM patients WHERE lastname LIKE ? OR firstname LIKE ?");
        $likeQuery = '%' . $query . '%';
        $stmt->execute([$likeQuery, $likeQuery]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addPatient($lastname, $firstname, $middlename, $age, $gender, $phone_number, $email) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO patients (lastname, firstname, middlename, age, gender, phone_number, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$lastname, $firstname, $middlename, $age, $gender, $phone_number, $email]);
        } catch (PDOException $e) {
            error_log("addPatient error: " . $e->getMessage());
            return false;
        }
}
