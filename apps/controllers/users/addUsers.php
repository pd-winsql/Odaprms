<?php

session_start();
require_once '../../models/users.php';
require_once '../../../config/conn.php';

$db = new Database();
$conn=$db->connect();
$userModel = new User($conn);

if(isset($_POST['email']) && isset($_POST['password']) && isset($_POST['confirm-password']) && isset($_POST['username'])) {
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm-password'];

    if ($password === $confirmPassword) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $userModel->addUser($email, $hashedPassword, $username);
        header("Location: ../../views/ventura_dental_form.php");
        exit();
    } else {
        echo "Passwords do not match.";
    }
}