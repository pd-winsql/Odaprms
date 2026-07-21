<?php

class User {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Find user by email OR username for login
    public function findByEmailOrUsername($identity) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM users
                WHERE email = :identity OR username = :identity
                LIMIT 1
            ");
            $stmt->execute([':identity' => $identity]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("findByEmailOrUsername error: " . $e->getMessage());
            return null;
        }
    }

    // Register new user
    public function register($email, $username, $hashedPassword, $role = 'Patient') {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO users (email, username, password, user_role)
                VALUES (:email, :username, :password, :role)
            ");
            return $stmt->execute([
                ':email'    => $email,
                ':username' => $username,
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

    // Check if username exists
    public function usernameExists($username) {
        try {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("usernameExists error: " . $e->getMessage());
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