<?php
require_once '../../../config/conn.php';

$db = new Database();
$conn=$db->connect();

class User {
    private $conn;
    public function __construct($conn) {
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
        $stmt = $this->conn->prepare("INSERT INTO users (email, password, username) VALUES (:email, :password, :username)");
        
        $stmt->execute([
            ':email' => $email,
            ':password' => $password,
            ':username' => $username
        ]);
    }
}

