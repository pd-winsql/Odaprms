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

    public function getStaffById(int $staffId) {
        $stmt = $this->conn->prepare("
            SELECT s.*, u.email AS user_email
            FROM staffs s
            JOIN users u ON u.id = s.user_id
            WHERE s.staff_id = :staff_id AND u.user_role = 'Dental Assistant'
            LIMIT 1
        ");
        $stmt->execute([':staff_id' => $staffId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getMyAccount(int $userId): ?array {
        $stmt = $this->conn->prepare("
            SELECT u.id AS user_id, u.email AS login_email, u.password, u.user_role,
                   s.staff_id, s.firstname, s.middlename, s.lastname, s.phone_number
            FROM users u
            LEFT JOIN staffs s ON s.user_id = u.id
            WHERE u.id = :user_id AND u.user_role IN ('Admin', 'Dental Assistant')
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateMyAccount(int $userId, array $fields): array {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("SELECT u.email, u.user_role, s.staff_id FROM users u LEFT JOIN staffs s ON s.user_id = u.id WHERE u.id = :user_id AND u.user_role IN ('Admin', 'Dental Assistant') FOR UPDATE");
            $stmt->execute([':user_id' => $userId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$account) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Account not found.'];
            }

            $stmt = $this->conn->prepare('UPDATE users SET email = :email WHERE id = :user_id');
            $stmt->execute([':email' => $fields['email'], ':user_id' => $userId]);
            if ($account['staff_id'] !== null) {
                $stmt = $this->conn->prepare('UPDATE staffs SET firstname = :firstname, middlename = :middlename, lastname = :lastname, phone_number = :phone, email = :email WHERE staff_id = :staff_id');
                $stmt->execute([
                    ':firstname' => $fields['firstname'], ':middlename' => $fields['middlename'] ?: null,
                    ':lastname' => $fields['lastname'], ':phone' => $fields['phone_number'],
                    ':email' => $fields['email'], ':staff_id' => $account['staff_id'],
                ]);
                $staffId = (int) $account['staff_id'];
            } else {
                $stmt = $this->conn->prepare('INSERT INTO staffs (user_id, firstname, middlename, lastname, phone_number, email) VALUES (:user_id, :firstname, :middlename, :lastname, :phone, :email)');
                $stmt->execute([
                    ':user_id' => $userId, ':firstname' => $fields['firstname'],
                    ':middlename' => $fields['middlename'] ?: null, ':lastname' => $fields['lastname'],
                    ':phone' => $fields['phone_number'], ':email' => $fields['email'],
                ]);
                $staffId = (int) $this->conn->lastInsertId();
            }
            $this->conn->commit();
            return ['success' => true, 'staff_id' => $staffId];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('updateMyAccount error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getCode() === '23000' ? 'This email is already in use.' : 'Unable to save your account. Please try again.'];
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
            $staffId = (int) $this->conn->lastInsertId();

            $this->conn->commit();
            return ['success' => true, 'email' => $email, 'staff_id' => $staffId];

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
            return $stmt->rowCount() === 1;
        } catch (PDOException $e) {
            error_log("toggleStatus error: " . $e->getMessage());
            return false;
        }
    }

}
