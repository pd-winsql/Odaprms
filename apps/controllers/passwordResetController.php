<?php
require_once '../../config/conn.php';
require_once '../models/userModel.php';
require_once '../../config/mailer.php';

header('Content-Type: application/json');
session_start();

error_log(print_r($_POST, true));
$action = $_POST['action'] ?? '';
error_log("Action received: " . $action);

$db   = new Database();
$conn = $db->connect();
$userModel = new User($conn);

$action = $_POST['action'] ?? '';

// ── 1. Send OTP ──────────────────────────────────────────────
if ($action === 'sendOTP') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    // Check if email exists in users table
    $user = $userModel->findByEmail($email);
    if (!$user) {
        // Don't reveal if email exists or not for security
        echo json_encode(['success' => true, 'message' => 'If that email is registered, a reset code has been sent.']);
        exit;
    }

    // Generate 6-digit OTP
    $otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Delete any existing OTPs for this email
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = :email");
    $stmt->execute([':email' => $email]);

    // Insert new OTP
    $stmt = $conn->prepare("
        INSERT INTO password_resets (email, otp, expires_at)
        VALUES (:email, :otp, NOW() + INTERVAL 10 MINUTE)
    ");
    $stmt->execute([
        ':email'      => $email,
        ':otp'        => $otp
    ]);

    // Send email
    $name   = $user['username'] ?? $email;
    $result = sendOTPEmail($email, $name, $otp);

    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Reset code sent! Check your email.']);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    exit;
}

// ── 2. Verify OTP ────────────────────────────────────────────
if ($action === 'verifyOTP') {
    $email = trim($_POST['email'] ?? '');
    $otp   = trim($_POST['otp']   ?? '');

    if (!$email || !$otp) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT * FROM password_resets
        WHERE email = :email
        AND otp = :otp
        AND used = 0
        AND expires_at >= NOW() 
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':email' => $email, ':otp' => $otp]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Please try again.']);
        exit;
    }

    // Generate a secure reset token
    $token = bin2hex(random_bytes(32));

    $tokenHash = hash('sha256', $token);

    $stmt = $conn->prepare("
        UPDATE password_resets
        SET
            token_hash = :token_hash,
            used = 1
        WHERE id = :id
    ");

    $stmt->execute([
        ':token_hash' => $tokenHash,
        ':id' => $record['id']
    ]);


    // Still keep email in session
    $_SESSION['reset_email'] = $email;

    // Send ONLY the raw token to the browser
    echo json_encode([
        'success' => true,
        'token' => $token
    ]);
    exit;
}

// ── 3. Reset Password ─────────────────────────────────────────
if ($action === 'resetPassword') {
    $token       = trim($_POST['token']        ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');

    // Make sure we still know which email is resetting
    if (!isset($_SESSION['reset_email'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Reset session expired. Please start again.'
        ]);
        exit;
    }

    $email = $_SESSION['reset_email'];

    // Hash the token received from the browser
    $tokenHash = hash('sha256', $token);

    // Look for a matching reset request
    $stmt = $conn->prepare("
        SELECT *
        FROM password_resets
        WHERE
            email = :email
            AND token_hash = :token_hash
            AND expires_at >= NOW()
        LIMIT 1
    ");

    $stmt->execute([
        ':email'      => $email,
        ':token_hash' => $tokenHash
    ]);

    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired reset token.'
        ]);
        exit;
    }

    if (strlen($newPassword) <= 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }

    $email          = $_SESSION['reset_email'];
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password in users table
    $stmt = $conn->prepare("UPDATE users SET password = :password WHERE email = :email");
    $result = $stmt->execute([
        ':password' => $hashedPassword,
        ':email'    => $email,
    ]);

    //Removes the reset record so the token cannot be reused.
    $stmt = $conn->prepare("
        DELETE FROM password_resets
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $reset['id']
    ]);

    if ($result) {
        // Clear reset session data
        unset($_SESSION['reset_token'], $_SESSION['reset_email'], $_SESSION['reset_expires']);
        echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);