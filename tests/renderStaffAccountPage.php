<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
session_start();
require_once __DIR__ . '/../config/conn.php';
$db=(new Database())->connect();
$role=($argv[1]??'Admin')==='Dental Assistant'?'Dental Assistant':'Admin';
$id=0;
try {
    $db->prepare('INSERT INTO users(email,password,email_verified_at,user_role) VALUES(?,?,NOW(),?)')->execute(['render-'.bin2hex(random_bytes(6)).'@example.invalid',password_hash('TestPassword123',PASSWORD_DEFAULT),$role]);
    $id=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO staffs(user_id,firstname,lastname,phone_number,email) SELECT id,'Account','Preview','09123456789',email FROM users WHERE id=?")->execute([$id]);
    $_SESSION=['user_id'=>$id,'user_role'=>$role,'csrf_token'=>bin2hex(random_bytes(32))];
    if (($argv[2]??'')==='legacy') require __DIR__ . '/../apps/views/admin/partials/change-password-content.php';
    else require __DIR__ . '/../apps/views/shared/my-account-content.php';
} finally {
    if ($id) {
        $db->prepare('DELETE FROM staffs WHERE user_id=?')->execute([$id]);
        $db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    }
    session_destroy();
}
