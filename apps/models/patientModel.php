<?php

require_once __DIR__ . '/auditLogModel.php';

class Patient {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getPatient($patient_id) {
        $stmt = $this->conn->prepare("SELECT * FROM patients WHERE patient_id = :patient_id");
        $stmt->execute([
            ':patient_id' => $patient_id
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPatientByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM patients WHERE email = :email");
        $stmt->execute([
            ':email' => $email
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPatientByUserId($user_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM patients WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getPatientByUserId error: " . $e->getMessage());
            return null;
        }
    }

    public static function normalizeIdentityName($value): string {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)), 'UTF-8');
    }

    public static function normalizePhone($value): string {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) === 12 && str_starts_with($digits, '63')) return '0' . substr($digits, 2);
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) return '0' . $digits;
        return $digits;
    }

    public static function identityMatchKey(array $data): string {
        return hash('sha256', implode('|', [
            self::normalizeIdentityName($data['firstname'] ?? ''),
            self::normalizeIdentityName($data['middlename'] ?? ''),
            self::normalizeIdentityName($data['lastname'] ?? ''),
            self::normalizeIdentityName($data['suffix'] ?? ''),
            trim((string) ($data['birthdate'] ?? '')),
            self::normalizePhone($data['phone_number'] ?? ''),
        ]));
    }

    public function findExactIdentity(array $data) {
        $stmt = $this->conn->prepare("SELECT * FROM patients WHERE identity_match_key = :identity_match_key LIMIT 1");
        $stmt->execute([':identity_match_key' => self::identityMatchKey($data)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findPossibleIdentityMatches(array $data): array {
        $stmt = $this->conn->prepare("
            SELECT patient_id FROM patients
            WHERE LOWER(TRIM(firstname)) = :firstname
              AND LOWER(TRIM(lastname)) = :lastname
              AND birthdate = :birthdate
              AND (identity_match_key IS NULL OR identity_match_key <> :identity_match_key)
        ");
        $stmt->execute([
            ':firstname' => self::normalizeIdentityName($data['firstname'] ?? ''),
            ':lastname' => self::normalizeIdentityName($data['lastname'] ?? ''),
            ':birthdate' => $data['birthdate'],
            ':identity_match_key' => self::identityMatchKey($data),
        ]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function getActiveLinkAuthorization(int $patientId, string $email) {
        $stmt = $this->conn->prepare("
            SELECT * FROM patient_account_link_authorizations
            WHERE patient_id = :patient_id AND LOWER(authorized_email) = LOWER(:email)
              AND status = 'Active' AND expires_at > NOW()
            ORDER BY authorization_id DESC LIMIT 1
        ");
        $stmt->execute([':patient_id' => $patientId, ':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function authorizeAccountLink(int $patientId, string $email, int $userId): array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => 'Enter a valid email address.'];
        try {
            $patient = $this->getPatient($patientId);
            if (!$patient || !empty($patient['user_id'])) return ['success' => false, 'message' => 'This patient is already linked to an account.'];
            $this->conn->prepare("UPDATE patient_account_link_authorizations SET status='Revoked' WHERE patient_id=:patient_id AND status='Active'")
                ->execute([':patient_id' => $patientId]);
            $this->conn->prepare("
                INSERT INTO patient_account_link_authorizations
                    (patient_id, authorized_email, authorized_by_user_id, expires_at)
                VALUES (:patient_id, :email, :user_id, DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ")->execute([':patient_id'=>$patientId, ':email'=>strtolower(trim($email)), ':user_id'=>$userId]);
            $audit = new AuditLog($this->conn); $actor = $audit->getUserActor($userId);
            $audit->record('patient',$patientId,'account_link_authorized',"Authorized account linking for patient #{$patientId}.",null,['email'=>$email,'expires_in_hours'=>24],$actor);
            return ['success'=>true,'message'=>'Account linking authorized for 24 hours. Ask the patient to register using this email and their matching information.'];
        } catch (Throwable $e) { error_log('authorizeAccountLink error: '.$e->getMessage()); return ['success'=>false,'message'=>'Unable to authorize account linking.']; }
    }

    public function createRegisteredPatient(int $userId, array $data, string $email): int {
        $stmt = $this->conn->prepare("
            INSERT INTO patients
                (user_id, firstname, middlename, lastname, suffix, birthdate, age, gender,
                 phone_number, email, profile_status, identity_match_key)
            VALUES
                (:user_id, :firstname, :middlename, :lastname, :suffix, :birthdate, :age, :gender,
                 :phone_number, :email, 'Incomplete', :identity_match_key)
        ");
        $birthdate = new DateTimeImmutable($data['birthdate']);
        $stmt->execute([
            ':user_id' => $userId,
            ':firstname' => trim($data['firstname']),
            ':middlename' => trim($data['middlename'] ?? '') ?: null,
            ':lastname' => trim($data['lastname']),
            ':suffix' => trim($data['suffix'] ?? '') ?: null,
            ':birthdate' => $data['birthdate'],
            ':age' => $birthdate->diff(new DateTimeImmutable('today'))->y,
            ':gender' => $data['gender'],
            ':phone_number' => self::normalizePhone($data['phone_number']),
            ':email' => $email,
            ':identity_match_key' => self::identityMatchKey($data),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function flagPossibleDuplicates(int $newPatientId, array $candidateIds): void {
        $stmt = $this->conn->prepare("
            INSERT IGNORE INTO patient_duplicate_reviews
                (new_patient_id, possible_existing_patient_id)
            VALUES (:new_patient_id, :candidate_id)
        ");
        foreach ($candidateIds as $candidateId) {
            if ((int) $candidateId !== $newPatientId) {
                $stmt->execute([':new_patient_id' => $newPatientId, ':candidate_id' => (int) $candidateId]);
            }
        }
    }

    public function getPatientFull($patient_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT v.*, p.profile_completed_at, p.profile_completed_by_user_id
                FROM vw_patient_information v
                JOIN patients p ON p.patient_id = v.patient_id
                WHERE v.patient_id = :patient_id
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

    public function createPatient($user_id, $firstname, $lastname, $middlename, $age, $gender, $phone_number, $email, $birthdate = null) {

        try {

            $stmt = $this->conn->prepare("
                INSERT INTO patients
                (user_id, firstname, lastname, middlename, age, gender, phone_number, email, birthdate)
                VALUES
                (:user_id, :firstname, :lastname, :middlename, :age, :gender, :phone_number, :email, :birthdate)
            ");

            $stmt->execute([
                ':user_id' => $user_id,
                ':firstname' => $firstname,
                ':lastname' => $lastname,
                ':middlename' => $middlename,
                ':age' => $age,
                ':gender' => $gender,
                ':phone_number' => $phone_number,
                ':email' => $email,
                ':birthdate' => $birthdate ?: null
            ]);
            return $this->conn->lastInsertId();
        } catch(PDOException $e){
            error_log("createPatient error: ".$e->getMessage());
            return false;
        }
    }

    public function fillMissingBookingDetails($patient_id, $data) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE patients SET
                    firstname = COALESCE(NULLIF(firstname, ''), :firstname),
                    lastname = COALESCE(NULLIF(lastname, ''), :lastname),
                    middlename = COALESCE(NULLIF(middlename, ''), :middlename),
                    age = COALESCE(age, :age),
                    gender = COALESCE(NULLIF(gender, ''), :gender),
                    phone_number = COALESCE(NULLIF(phone_number, ''), :phone_number),
                    birthdate = COALESCE(birthdate, :birthdate)
                WHERE patient_id = :patient_id
            ");
            return $stmt->execute([
                ':firstname' => $data['firstname'] ?: null,
                ':lastname' => $data['lastname'] ?: null,
                ':middlename' => $data['middlename'] ?: null,
                ':age' => $data['age'],
                ':gender' => $data['gender'] ?: null,
                ':phone_number' => $data['phone_number'] ?: null,
                ':birthdate' => $data['birthdate'] ?: null,
                ':patient_id' => $patient_id,
            ]);
        } catch (PDOException $e) {
            error_log("fillMissingBookingDetails error: " . $e->getMessage());
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

    
    
    // Create a basic patient record from a users account
    public function createPatientFromUser($user_id, $email) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO patients (user_id, firstname, lastname, email)
                VALUES (:user_id, 'Patient', '', :email)
            ");
            return $stmt->execute([
                ':user_id'   => $user_id,
                ':email'     => $email,
            ]);
        } catch (PDOException $e) {
            error_log("createPatientFromUser error: " . $e->getMessage());
            return false;
        }
    }

    public function linkUser($patient_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE patients
                SET user_id = :user_id
                WHERE patient_id = :patient_id
            ");
            return $stmt->execute([
                ':user_id' => $user_id,
                ':patient_id' => $patient_id
            ]);
        } catch (PDOException $e) {
            error_log("linkUser error: " . $e->getMessage());
            return false;
        }
    }

    public function isLinked($email) {
        $stmt = $this->conn->prepare("
            SELECT user_id
            FROM patients
            WHERE email = :email
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePersonal($patient_id, $data) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE patients SET
                    firstname      = :firstname,
                    middlename     = :middlename,
                    lastname       = :lastname,
                    birthdate      = :birthdate,
                    age            = :age,
                    gender         = :gender,
                    civil_status   = :civil_status,
                    phone_number   = :phone_number,
                    email          = :email,
                    home_address   = :home_address,
                    work_address   = :work_address,
                    occupation     = :occupation,
                    office_contact = :office_contact,
                    fb_account     = :fb_account
                WHERE patient_id = :patient_id
            ");
            return $stmt->execute([
                ':firstname'      => $data['firstname'],
                ':middlename'     => $data['middlename'] ?: null,
                ':lastname'       => $data['lastname'],
                ':birthdate'      => $data['birthdate'] ?: null,
                ':age'            => $data['age'] ?: null,
                ':gender'         => $data['gender'] ?: null,
                ':civil_status'   => $data['civil_status'] ?: null,
                ':phone_number'   => $data['phone_number'] ?: null,
                ':email'          => $data['email'],
                ':home_address'   => $data['home_address'] ?: null,
                ':work_address'   => $data['work_address'] ?: null,
                ':occupation'     => $data['occupation'] ?: null,
                ':office_contact' => $data['office_contact'] ?: null,
                ':fb_account'     => $data['fb_account'] ?: null,
                ':patient_id'     => $patient_id,
            ]);
        } catch (PDOException $e) {
            error_log("updatePersonal error: " . $e->getMessage());
            return false;
        }
    }
 
    public function updateMinors($patient_id, $data) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE patients SET
                    guardian_name     = :guardian_name,
                    guardian_contact  = :guardian_contact,
                    physician_name    = :physician_name,
                    physician_contact = :physician_contact,
                    physician_address = :physician_address
                WHERE patient_id = :patient_id
            ");
            return $stmt->execute([
                ':guardian_name'     => $data['guardian_name']     ?: null,
                ':guardian_contact'  => $data['guardian_contact']  ?: null,
                ':physician_name'    => $data['physician_name']    ?: null,
                ':physician_contact' => $data['physician_contact'] ?: null,
                ':physician_address' => $data['physician_address'] ?: null,
                ':patient_id'        => $patient_id,
            ]);
        } catch (PDOException $e) {
            error_log("updateMinors error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateDentalHistory($patient_id, $data) {
        try {
            // Check if record exists
            $stmt = $this->conn->prepare("SELECT dental_history_id FROM patient_dental_history WHERE patient_id = :pid");
            $stmt->execute([':pid' => $patient_id]);
            $exists = $stmt->fetch();
    
            if ($exists) {
                $stmt = $this->conn->prepare("
                    UPDATE patient_dental_history SET
                        previous_dentist  = :previous_dentist,
                        last_dental_visit = :last_dental_visit,
                        treatment_done    = :treatment_done,
                        reason_for_visit  = :reason_for_visit,
                        referred_by       = :referred_by,
                        last_updated_by   = :last_updated_by,
                        last_updated_at   = NOW()
                    WHERE patient_id = :patient_id
                ");
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO patient_dental_history
                        (patient_id, previous_dentist, last_dental_visit, treatment_done, reason_for_visit, referred_by, last_updated_by, last_updated_at)
                    VALUES
                        (:patient_id, :previous_dentist, :last_dental_visit, :treatment_done, :reason_for_visit, :referred_by, :last_updated_by, NOW())
                ");
            }
    
            return $stmt->execute([
                ':previous_dentist'  => $data['previous_dentist']  ?: null,
                ':last_dental_visit' => $data['last_dental_visit'] ?: null,
                ':treatment_done'    => $data['treatment_done']    ?: null,
                ':reason_for_visit'  => $data['reason_for_visit']  ?: null,
                ':referred_by'       => $data['referred_by']       ?: null,
                ':last_updated_by'   => $data['last_updated_by'],
                ':patient_id'        => $patient_id,
            ]);
        } catch (PDOException $e) {
            error_log("updateDentalHistory error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateMedicalHistory($patient_id, $data) {
        try {
            $stmt = $this->conn->prepare("SELECT medical_history_id FROM patient_medical_history WHERE patient_id = :pid");
            $stmt->execute([':pid' => $patient_id]);
            $exists = $stmt->fetch();
    
            $fields = [
                'good_health','medical_condition','medical_condition_detail',
                'serious_illness','serious_illness_detail','hospitalized','hospitalized_detail',
                'medication','medication_detail','smoke','alcohol','drugs',
                'allergy','allergy_detail','pregnant','nursing','birth_control',
                'blood_type','blood_pressure','last_updated_by',
            ];
    
            if ($exists) {
                $sets = implode(', ', array_map(fn($f) => "$f = :$f", $fields));
                $sql  = "UPDATE patient_medical_history SET $sets, last_updated_at = NOW() WHERE patient_id = :patient_id";
            } else {
                $cols = implode(', ', $fields) . ', patient_id';
                $vals = implode(', ', array_map(fn($f) => ":$f", $fields)) . ', :patient_id';
                $sql  = "INSERT INTO patient_medical_history ($cols) VALUES ($vals)";
            }
    
            $params = [':patient_id' => $patient_id];
            foreach ($fields as $f) {
                $val = $data[$f] ?? null;
                $params[":$f"] = ($val === '' || $val === null) ? null : $val;
            }
    
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
    
        } catch (PDOException $e) {
            error_log("updateMedicalHistory error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateConditions($patient_id, $conditions, $cond_others = '') {
        try {
            $this->conn->beginTransaction();
    
            // Delete existing conditions
            $stmt = $this->conn->prepare("DELETE FROM patient_conditions WHERE patient_id = :pid");
            $stmt->execute([':pid' => $patient_id]);
    
            // Insert new ones
            if (!empty($conditions)) {
                $stmt = $this->conn->prepare("
                    INSERT INTO patient_conditions (patient_id, `condition`)
                    VALUES (:patient_id, :condition)
                ");
                foreach ($conditions as $cond) {
                    $stmt->execute([':patient_id' => $patient_id, ':condition' => $cond]);
                }
            }
    
            // Save cond_others to medical history
            $stmt = $this->conn->prepare("
                UPDATE patient_medical_history SET cond_others = :cond_others WHERE patient_id = :pid
            ");
            $stmt->execute([':cond_others' => $cond_others ?: null, ':pid' => $patient_id]);
    
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("updateConditions error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateConsent($patient_id, $data) {
        try {
            $stmt = $this->conn->prepare("SELECT consent_id FROM patient_consent WHERE patient_id = :pid");
            $stmt->execute([':pid' => $patient_id]);
            $exists = $stmt->fetch();
    
            if ($exists) {
                $stmt = $this->conn->prepare("
                    UPDATE patient_consent SET
                        consent_name = :consent_name,
                        consent_for  = :consent_for,
                        consent_date = :consent_date
                    WHERE patient_id = :patient_id
                ");
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO patient_consent (patient_id, consent_name, consent_for, consent_date)
                    VALUES (:patient_id, :consent_name, :consent_for, :consent_date)
                ");
            }
    
            return $stmt->execute([
                ':consent_name' => $data['consent_name'] ?: null,
                ':consent_for'  => $data['consent_for']  ?: null,
                ':consent_date' => $data['consent_date'] ?: null,
                ':patient_id'   => $patient_id,
            ]);
        } catch (PDOException $e) {
            error_log("updateConsent error: " . $e->getMessage());
            return false;
        }
    }

    public function completeProfileByStaff($patientId, array $data, $userId, bool $markComplete = true): array {
        try {
            $this->conn->beginTransaction();

            $personal = $this->conn->prepare("
                UPDATE patients SET
                    firstname = :firstname, lastname = :lastname, middlename = :middlename,
                    birthdate = :birthdate, age = :age, gender = :gender,
                    civil_status = :civil_status, phone_number = :phone_number, email = :email,
                    home_address = :home_address, work_address = :work_address,
                    occupation = :occupation, office_contact = :office_contact, fb_account = :fb_account,
                    guardian_name = :guardian_name, guardian_contact = :guardian_contact,
                    physician_name = :physician_name, physician_contact = :physician_contact,
                    physician_address = :physician_address
                WHERE patient_id = :patient_id
            ");
            $personal->execute([
                ':firstname' => $data['firstname'], ':lastname' => $data['lastname'],
                ':middlename' => $data['middlename'] ?: null, ':birthdate' => $data['birthdate'],
                ':age' => $data['age'], ':gender' => $data['gender'],
                ':civil_status' => $data['civil_status'] ?: null, ':phone_number' => $data['phone_number'],
                ':email' => $data['email'] ?: null, ':home_address' => $data['home_address'] ?: null,
                ':work_address' => $data['work_address'] ?: null, ':occupation' => $data['occupation'] ?: null,
                ':office_contact' => $data['office_contact'] ?: null, ':fb_account' => $data['fb_account'] ?: null,
                ':guardian_name' => $data['guardian_name'] ?: null, ':guardian_contact' => $data['guardian_contact'] ?: null,
                ':physician_name' => $data['physician_name'] ?: null, ':physician_contact' => $data['physician_contact'] ?: null,
                ':physician_address' => $data['physician_address'] ?: null, ':patient_id' => $patientId,
            ]);
            if ($personal->rowCount() === 0 && !$this->getPatient($patientId)) {
                throw new RuntimeException('Patient not found.');
            }

            $dental = $this->conn->prepare("
                INSERT INTO patient_dental_history
                    (patient_id, previous_dentist, last_dental_visit, treatment_done, reason_for_visit, referred_by, last_updated_by, last_updated_at)
                VALUES
                    (:patient_id, :previous_dentist, :last_dental_visit, :treatment_done, :reason_for_visit, :referred_by, :updated_by, NOW())
                ON DUPLICATE KEY UPDATE
                    previous_dentist = VALUES(previous_dentist), last_dental_visit = VALUES(last_dental_visit),
                    treatment_done = VALUES(treatment_done), reason_for_visit = VALUES(reason_for_visit),
                    referred_by = VALUES(referred_by), last_updated_by = VALUES(last_updated_by), last_updated_at = NOW()
            ");
            $dental->execute([
                ':patient_id' => $patientId, ':previous_dentist' => $data['previous_dentist'] ?: null,
                ':last_dental_visit' => $data['last_dental_visit'] ?: null, ':treatment_done' => $data['treatment_done'] ?: null,
                ':reason_for_visit' => $data['reason_for_visit'], ':referred_by' => $data['referred_by'] ?: null,
                ':updated_by' => 'staff:' . $userId,
            ]);

            $medicalFields = [
                'good_health','medical_condition','medical_condition_detail','serious_illness','serious_illness_detail',
                'hospitalized','hospitalized_detail','medication','medication_detail','smoke','alcohol','drugs',
                'allergy','allergy_detail','pregnant','nursing','birth_control','blood_type','blood_pressure','cond_others'
            ];
            $columns = implode(', ', $medicalFields);
            $values = implode(', ', array_map(static fn($field) => ':' . $field, $medicalFields));
            $updates = implode(', ', array_map(static fn($field) => "$field = VALUES($field)", $medicalFields));
            $medical = $this->conn->prepare("
                INSERT INTO patient_medical_history (patient_id, {$columns}, last_updated_by, last_updated_at)
                VALUES (:patient_id, {$values}, :updated_by, NOW())
                ON DUPLICATE KEY UPDATE {$updates}, last_updated_by = VALUES(last_updated_by), last_updated_at = NOW()
            ");
            $medicalParams = [':patient_id' => $patientId, ':updated_by' => 'staff:' . $userId];
            foreach ($medicalFields as $field) {
                $value = $data[$field] ?? null;
                $medicalParams[':' . $field] = $value === '' ? null : $value;
            }
            $medical->execute($medicalParams);

            $this->conn->prepare("DELETE FROM patient_conditions WHERE patient_id = :patient_id")
                ->execute([':patient_id' => $patientId]);
            if (!empty($data['conditions'])) {
                $conditionStmt = $this->conn->prepare("INSERT INTO patient_conditions (patient_id, `condition`) VALUES (:patient_id, :condition)");
                foreach (array_unique($data['conditions']) as $condition) {
                    $condition = trim($condition);
                    if ($condition !== '') $conditionStmt->execute([':patient_id' => $patientId, ':condition' => $condition]);
                }
            }

            $consent = $this->conn->prepare("
                INSERT INTO patient_consent (patient_id, consent_name, consent_for, consent_date)
                VALUES (:patient_id, :consent_name, :consent_for, :consent_date)
                ON DUPLICATE KEY UPDATE consent_name = VALUES(consent_name), consent_for = VALUES(consent_for), consent_date = VALUES(consent_date)
            ");
            $consent->execute([
                ':patient_id' => $patientId, ':consent_name' => $data['consent_name'],
                ':consent_for' => $data['consent_for'], ':consent_date' => date('Y-m-d'),
            ]);

            if ($markComplete) {
                $this->conn->prepare("UPDATE patients SET profile_completed_at=NOW(),profile_completed_by_user_id=:user_id,profile_status='Complete' WHERE patient_id=:patient_id")
                    ->execute([':user_id'=>$userId,':patient_id'=>$patientId]);
                $this->conn->prepare("UPDATE appointment_checkins ci JOIN appointments a ON a.appointment_id=ci.appointment_id SET ci.checkin_status='Ready',ci.ready_at=NOW() WHERE a.patient_id=:patient_id AND a.date=CURDATE() AND ci.checkin_status='Profile Required'")
                    ->execute([':patient_id'=>$patientId]);
            } else {
                $this->conn->prepare("UPDATE patients SET profile_status='Draft' WHERE patient_id=:patient_id AND profile_completed_at IS NULL")
                    ->execute([':patient_id'=>$patientId]);
            }

            $audit = new AuditLog($this->conn);
            $actor = $audit->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Staff account not found.');
            $audit->record(
                'patient', $patientId, $markComplete ? 'profile_completed' : 'profile_draft_saved',
                ($markComplete ? 'Completed' : 'Saved a draft of') . " the patient form for patient #{$patientId} at the front desk.",
                null, ['profile_status' => $markComplete ? 'Complete' : 'Draft'], $actor
            );

            $this->conn->commit();
            return ['success' => true, 'message' => $markComplete ? 'Patient form completed. The patient is ready.' : 'Patient form draft saved. Treatment remains unavailable until the form is completed.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('completeProfileByStaff error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to complete the patient form.'];
        }
    }
}
