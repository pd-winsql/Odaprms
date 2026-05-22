<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
session_start();
require_once '../models/userController.php';
require_once '../../config/conn.php';

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$userModel = new User($conn);

$action = $_POST['action'] ?? '';

try {
    if ($action === 'register') {
        registerUser($userModel);
    } elseif ($action === 'login') {
        loginUser($userModel);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    error_log("Controller Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

function registerUser($userModel) {
    $email           = $_POST['email'] ?? '';
    $username        = $_POST['username'] ?? '';
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm-password'] ?? '';

    // Validate input
    if (empty($email) || empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        return;
    }

    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        return;
    }

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        return;
    }

    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $result = $userModel->addUser($email, $hashedPassword, $username);
        
        if ($result) {
            echo json_encode(['success' => true, 'redirect' => '../../index.php?openModal=true']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function loginUser($userModel) {
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = $userModel->checkUser($email, $password);
    if ($user) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['email']    = $user['email'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['user_role'];

        if ($user['user_role'] === 'Patient') {
            echo json_encode(['success' => true, 'redirect' => '../../views/patient/dashboard.php']);
        } else if ($user['user_role'] === 'Admin') {
            echo json_encode(['success' => true, 'redirect' => 'apps/views/admin/dashboard.php']);
        } else {
            echo json_encode(['success' => true, 'redirect' => '../../views/dental_asst/dashboard.php']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    }
}