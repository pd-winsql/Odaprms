<?php

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || !hash_equals('schedule-picker-aug31-qa', (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit;
}

session_start();
$role = (string) ($_GET['role'] ?? 'Admin');
$patient = $role === 'Patient';
$dentalAssistant = $role === 'Dental Assistant';
$_SESSION['user_id'] = $patient ? 14 : 7;
$_SESSION['user_role'] = $patient ? 'Patient' : ($dentalAssistant ? 'Dental Assistant' : 'Admin');
$_SESSION['display_name'] = $patient ? 'Patient' : ($dentalAssistant ? 'Dental Assistant' : 'Administrator');

$dashboard = $patient ? 'patient' : ($dentalAssistant ? 'dental_asst' : 'admin');
$page = basename((string) ($_GET['page'] ?? 'schedule-content.php'));
header("Location: ../apps/views/{$dashboard}/dashboard.php#{$page}");
exit;
