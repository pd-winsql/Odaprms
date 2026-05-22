<?php

class User {
    private $conn;
    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    public function checkUser($email, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = :email OR username = :email");
        $stmt->execute(['email' => $email, 'username' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function addUser($email, $password, $username) {
        // Check if email or username already exists
        $checkStmt = $this->conn->prepare("SELECT id FROM users WHERE email = :email OR username = :username");
        $checkStmt->execute([':email' => $email, ':username' => $username]);
        
        if ($checkStmt->rowCount() > 0) {
            throw new Exception('Email or username already exists.');
        }
        
        $stmt = $this->conn->prepare("INSERT INTO users (email, password, username, user_role) VALUES (:email, :password, :username, :user_role)");
        
        $result = $stmt->execute([
            ':email' => $email,
            ':password' => $password,
            ':username' => $username,
            ':user_role' => 'Patient'  // Default role for new registrations
        ]);
        
        return $result;
    }
}

