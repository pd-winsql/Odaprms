<?php
// Loopback-only router for the isolated GCash security audit.
$database = (string) getenv('VD_GCASH_TEST_DATABASE');
if (PHP_SAPI !== 'cli-server'
    || !preg_match('/^vd_gcash_test_[a-f0-9]{12}$/', $database)
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(404);
    exit;
}

$_ENV['DATABASE_NAME'] = $database;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$allowed = ['/apps/controllers/depositController.php'];
if (!in_array($path, $allowed, true)) {
    http_response_code(404);
    exit;
}

$file = dirname(__DIR__) . $path;
chdir(dirname($file));
require $file;

