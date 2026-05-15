<?php
session_start();
require_once '../../models/users.php';
require_once '../../../config/conn.php';

$db = new Database();
$conn = $db->connect();
$userModel = new User($conn);

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    registerUser($userModel);
} elseif ($action === 'login') {
    loginUser($userModel);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}

function registerUser($userModel) {
    $email           = $_POST['email'] ?? '';
    $username        = $_POST['username'] ?? '';
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm-password'] ?? '';

    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        return;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $userModel->addUser($email, $hashedPassword, $username);
    echo json_encode(['success' => true, 'redirect' => '../../views/ventura_dental_form.php']);
}

function loginUser($userModel) {
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = $userModel->checkUser($email, $password);
    if ($user) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['email']    = $user['email'];
        $_SESSION['username'] = $user['username'];
        echo json_encode(['success' => true, 'redirect' => '../../views/dashboard.html']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    }
}