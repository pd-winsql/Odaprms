<?php

class User {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function findByEmail($email) {
        try {
            $stmt = $this->conn->prepare("
                SELECT u.*,
                    COALESCE(
                        NULLIF(TRIM(CONCAT_WS(' ', s.firstname, s.middlename, s.lastname)), ''),
                        NULLIF(TRIM(CONCAT_WS(' ', p.firstname, p.middlename, p.lastname)), ''),
                        u.email
                    ) AS display_name
                FROM users u
                LEFT JOIN staffs s ON s.user_id = u.id
                LEFT JOIN patients p ON p.user_id = u.id
                WHERE LOWER(u.email) = LOWER(:email)
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("findByEmail error: " . $e->getMessage());
            return null;
        }
    }

    public function getLastInsertedId() {
        try {
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("getLastInsertedId error: " . $e->getMessage());
            return null;
        }
    }

    // Register new user
    public function register($email, $hashedPassword, $role = 'Patient') {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO users (email, password, email_verified_at, user_role)
                VALUES (:email, :password, NOW(), :role)
            ");
            return $stmt->execute([
                ':email'    => $email,
                ':password' => $hashedPassword,
                ':role'     => $role,
            ]);
        } catch (PDOException $e) {
            error_log("register error: " . $e->getMessage());
            return false;
        }
    }

    // Check if email exists
    public function emailExists($email) {
        try {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("emailExists error: " . $e->getMessage());
            return false;
        }
    }

    // Change password
    public function changePassword($user_id, $newHashedPassword) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE users SET password = :password WHERE id = :id
            ");
            return $stmt->execute([
                ':password' => $newHashedPassword,
                ':id'       => $user_id,
            ]);
        } catch (PDOException $e) {
            error_log("changePassword error: " . $e->getMessage());
            return false;
        }
    }

    // Get user by ID
    public function getUserById($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute([':id' => $user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getUserById error: " . $e->getMessage());
            return null;
        }
    }
}

