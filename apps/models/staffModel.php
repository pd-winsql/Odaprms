<?php

class Staff {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getAllStaff() {
        try {
            $stmt = $this->conn->prepare("
                SELECT s.*, u.email AS user_email
                FROM staffs s
                JOIN users u ON s.user_id = u.id
                WHERE u.user_role = 'Dental Assistant'
                ORDER BY s.created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAllStaff error: " . $e->getMessage());
            return [];
        }
    }

    public function createStaff($firstname, $lastname, $middlename, $gender, $phone, $email, $password) {
        try {
            $this->conn->beginTransaction();

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users
            $stmt = $this->conn->prepare("
                INSERT INTO users (email, password, email_verified_at, user_role)
                VALUES (:email, :password, NOW(), 'Dental Assistant')
            ");
            $stmt->execute([
                ':email'    => $email,
                ':password' => $hashedPassword,
            ]);
            $userId = $this->conn->lastInsertId();

            // Insert into staffs
            $stmt = $this->conn->prepare("
                INSERT INTO staffs (user_id, firstname, lastname, middlename, gender, phone_number, email)
                VALUES (:user_id, :firstname, :lastname, :middlename, :gender, :phone, :email)
            ");
            $stmt->execute([
                ':user_id'    => $userId,
                ':firstname'  => $firstname,
                ':lastname'   => $lastname,
                ':middlename' => $middlename ?: null,
                ':gender'     => $gender,
                ':phone'      => $phone,
                ':email'      => $email,
            ]);

            $this->conn->commit();
            return ['success' => true, 'email' => $email];

        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("createStaff error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateStaff($staff_id, $phone, $email) {
        try {
            $this->conn->beginTransaction();

            // Update staffs table
            $stmt = $this->conn->prepare("
                UPDATE staffs SET phone_number = :phone, email = :email
                WHERE staff_id = :staff_id
            ");
            $stmt->execute([
                ':phone'    => $phone,
                ':email'    => $email,
                ':staff_id' => $staff_id,
            ]);

            // Also update users table email
            $stmt = $this->conn->prepare("
                UPDATE users u
                JOIN staffs s ON u.id = s.user_id
                SET u.email = :email
                WHERE s.staff_id = :staff_id
            ");
            $stmt->execute([
                ':email'    => $email,
                ':staff_id' => $staff_id,
            ]);

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("updateStaff error: " . $e->getMessage());
            return false;
        }
    }

    public function toggleStatus($staff_id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE staffs
                SET employment_status = CASE
                    WHEN employment_status = 'Active' THEN 'Inactive'
                    ELSE 'Active'
                END
                WHERE staff_id = :staff_id
            ");
            $stmt->execute([':staff_id' => $staff_id]);
            return true;
        } catch (PDOException $e) {
            error_log("toggleStatus error: " . $e->getMessage());
            return false;
        }
    }

}
