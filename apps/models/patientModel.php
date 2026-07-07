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

    public function getPatientFull($patient_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM vw_patient_information 
                WHERE patient_id = :patient_id
            ");
            $stmt->execute([':patient_id' => $patient_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getPatientFull error: " . $e->getMessage());
            return null;
        }
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

    public function savePatientForm($data) {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("\n                INSERT INTO patients\n                (user_id, firstname, lastname, middlename, age, gender, phone_number, email, birthdate, civil_status, home_address, work_address, fb_account, occupation, office_contact, guardian_name, guardian_contact, physician_name, physician_contact, physician_address)\n                VALUES\n                (:user_id, :firstname, :lastname, :middlename, :age, :gender, :phone_number, :email, :birthdate, :civil_status, :home_address, :work_address, :fb_account, :occupation, :office_contact, :guardian_name, :guardian_contact, :physician_name, :physician_contact, :physician_address)\n            ");

            $stmt->execute([
                ':user_id' => null,
                ':firstname' => $data['firstname'],
                ':lastname' => $data['lastname'],
                ':middlename' => $data['middlename'],
                ':age' => $data['age'],
                ':gender' => $data['gender'],
                ':phone_number' => $data['phone_number'],
                ':email' => $data['email'],
                ':birthdate' => $data['birthdate'],
                ':civil_status' => $data['civil_status'],
                ':home_address' => $data['home_address'],
                ':work_address' => $data['work_address'],
                ':fb_account' => $data['fb_account'],
                ':occupation' => $data['occupation'],
                ':office_contact' => $data['office_contact'],
                ':guardian_name' => $data['guardian_name'],
                ':guardian_contact' => $data['guardian_contact'],
                ':physician_name' => $data['physician_name'],
                ':physician_contact' => $data['physician_contact'],
                ':physician_address' => $data['physician_address']
            ]);

            $patient_id = $this->conn->lastInsertId();

            $stmt = $this->conn->prepare("\n                INSERT INTO patient_dental_history\n                (patient_id, previous_dentist, last_dental_visit, treatment_done, reason_for_visit, referred_by)\n                VALUES\n                (:patient_id, :previous_dentist, :last_dental_visit, :treatment_done, :reason_for_visit, :referred_by)\n            ");
            $stmt->execute([
                ':patient_id' => $patient_id,
                ':previous_dentist' => $data['previous_dentist'],
                ':last_dental_visit' => $data['last_dental_visit'],
                ':treatment_done' => $data['treatment_done'],
                ':reason_for_visit' => $data['reason_for_visit'],
                ':referred_by' => $data['referred_by']
            ]);

            $stmt = $this->conn->prepare("\n                INSERT INTO patient_medical_history\n                (patient_id, good_health, medical_condition, medical_condition_detail, serious_illness, serious_illness_detail, hospitalized, hospitalized_detail, medication, medication_detail, smoke, alcohol, drugs, allergy, allergy_detail, pregnant, nursing, birth_control, cond_others)\n                VALUES\n                (:patient_id, :good_health, :medical_condition, :medical_condition_detail, :serious_illness, :serious_illness_detail, :hospitalized, :hospitalized_detail, :medication, :medication_detail, :smoke, :alcohol, :drugs, :allergy, :allergy_detail, :pregnant, :nursing, :birth_control, :cond_others)\n            ");
            $stmt->execute([
                ':patient_id' => $patient_id,
                ':good_health' => $data['good_health'],
                ':medical_condition' => $data['medical_condition'],
                ':medical_condition_detail' => $data['medical_condition_detail'],
                ':serious_illness' => $data['serious_illness'],
                ':serious_illness_detail' => $data['serious_illness_detail'],
                ':hospitalized' => $data['hospitalized'],
                ':hospitalized_detail' => $data['hospitalized_detail'],
                ':medication' => $data['medication'],
                ':medication_detail' => $data['medication_detail'],
                ':smoke' => $data['smoke'],
                ':alcohol' => $data['alcohol'],
                ':drugs' => $data['drugs'],
                ':allergy' => $data['allergy'],
                ':allergy_detail' => $data['allergy_detail'],
                ':pregnant' => $data['pregnant'],
                ':nursing' => $data['nursing'],
                ':birth_control' => $data['birth_control'],
                ':cond_others' => $data['cond_others']
            ]);

            if (!empty($data['conditions'])) {
                foreach ($data['conditions'] as $condition) {
                    $stmt = $this->conn->prepare("INSERT INTO patient_conditions (patient_id, `condition`) VALUES (:patient_id, :condition)");
                    $stmt->execute([
                        ':patient_id' => $patient_id,
                        ':condition' => $condition
                    ]);
                }
            }

            if (!empty($data['consent_name']) || !empty($data['consent_for'])) {
                $stmt = $this->conn->prepare("\n                    INSERT INTO patient_consent\n                    (patient_id, consent_name, consent_for, consent_date)\n                    VALUES\n                    (:patient_id, :consent_name, :consent_for, :consent_date)\n                ");
                $stmt->execute([
                    ':patient_id' => $patient_id,
                    ':consent_name' => $data['consent_name'],
                    ':consent_for' => $data['consent_for'],
                    ':consent_date' => $data['consent_date']
                ]);
            }

            $this->conn->commit();
            return $patient_id;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("savePatientForm error: " . $e->getMessage());
            return false;
        }
    }
}