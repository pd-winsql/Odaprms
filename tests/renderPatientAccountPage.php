<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
session_start();
require_once __DIR__ . '/../config/conn.php';
$db = (new Database())->connect();
$id = 0;
try {
    $email = 'patient-account-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $db->prepare("INSERT INTO users(email,password,email_verified_at,user_role) VALUES(?,?,NOW(),'Patient')")
        ->execute([$email, password_hash('TestPassword123', PASSWORD_DEFAULT)]);
    $id = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO patients(user_id,firstname,middlename,lastname,birthdate,age,gender,phone_number,profile_status) VALUES(?,'Patient','Mobile','Preview','2000-01-01',26,'Prefer not to say','09123456789','Complete')")
        ->execute([$id]);
    $_SESSION = ['user_id'=>$id, 'user_role'=>'Patient', 'email'=>$email, 'csrf_token'=>bin2hex(random_bytes(32))];
    require __DIR__ . '/../apps/views/patient/partials/my-account-content.php';
} finally {
    if ($id) {
        $db->prepare('DELETE FROM account_email_changes WHERE user_id=?')->execute([$id]);
        $db->prepare('DELETE FROM audit_logs WHERE performed_by_user_id=?')->execute([$id]);
        $db->prepare('DELETE FROM patients WHERE user_id=?')->execute([$id]);
        $db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    }
    session_destroy();
}

