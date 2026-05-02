<?php

session_start();
require_once '../../models/users.php';
require_once '../../../config/conn.php';

$db = new Database();
$conn=$db->connect();
$userModel = new User($conn);

if(isset($_POST['email']) && isset($_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $user = $userModel->checkUser($email, $password);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'] ?? $user['email'];
        header("Location: ../../views/dashboard.html");
        echo "Login successful. Welcome, " . $_SESSION['username'] . "!";
        exit();
    } else {
        echo "Invalid email or password.";
    }
}