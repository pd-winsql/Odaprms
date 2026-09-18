<?php
// Only the CLI regression runner may launch this loopback-only router.
$database = (string) getenv('VD_AUTH_TEST_DATABASE');
if (PHP_SAPI !== 'cli-server' || !preg_match('/^vd_auth_test_[a-f0-9]{12}$/', $database)
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(404);
    exit;
}
$_ENV['DATABASE_NAME'] = $database;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$allowed = [
    '/apps/controllers/patientController.php',
    '/apps/controllers/appointmentController.php',
    '/apps/controllers/depositController.php',
    '/apps/controllers/rescheduleController.php',
    '/apps/controllers/chatController.php',
    '/apps/controllers/billingController.php',
    '/apps/views/patient/partials/profile-content.php',
    '/apps/views/patient/partials/history-content.php',
    '/apps/views/patient/partials/billing-content.php',
];
if (!in_array($path, $allowed, true)) {
    http_response_code(404);
    exit;
}
$file = dirname(__DIR__) . $path;
chdir(dirname($file)); // Preserve the controllers' existing relative includes.
require $file;
