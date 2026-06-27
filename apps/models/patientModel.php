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

    public function filterPatients($from = null, $to = null, $clinic_id = null, $query = null) {
        $sql = "SELECT DISTINCT p.* FROM patients p LEFT JOIN appointments a ON p.patient_id = a.patient_id WHERE 1=1";
        $params = [];

        if (!empty($from)) {
            $sql .= " AND DATE(p.created_at) >= :from";
            $params[':from'] = $from;
        }
        if (!empty($to)) {
            $sql .= " AND DATE(p.created_at) <= :to";
            $params[':to'] = $to;
        }
        if (!empty($clinic_id)) {
            $sql .= " AND a.clinic_id = :clinic_id";
            $params[':clinic_id'] = $clinic_id;
        }
        if (!empty($query)) {
            $sql .= " AND (p.lastname LIKE :q OR p.firstname LIKE :q)";
            $params[':q'] = '%' . $query . '%';
        }

        $sql .= " ORDER BY p.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchPatients($query) {
        $stmt = $this->conn->prepare("SELECT * FROM patients WHERE lastname LIKE ? OR firstname LIKE ?");
        $likeQuery = '%' . $query . '%';
        $stmt->execute([$likeQuery, $likeQuery]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPatient($firstname, $lastname, $middlename, $age, $gender, $phone_number, $email) {

        try {

            $stmt = $this->conn->prepare("
                INSERT INTO patients
                (firstname, lastname, middlename, age, gender, phone_number, email)
                VALUES
                (:firstname, :lastname, :middlename, :age, :gender, :phone_number, :email)
            ");

            $stmt->execute([
                ':firstname' => $firstname,
                ':lastname' => $lastname,
                ':middlename' => $middlename,
                ':age' => $age,
                ':gender' => $gender,
                ':phone_number' => $phone_number,
                ':email' => $email
            ]);
            return $this->conn->lastInsertId();
        } catch(PDOException $e){
            error_log("createPatient error: ".$e->getMessage());
            return false;
        }
    }
}