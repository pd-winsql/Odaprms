<?php
// Loopback-only router for the isolated patient browser regression.
$database = (string) getenv('VD_PATIENT_OFFLINE_TEST_DATABASE');
if (PHP_SAPI !== 'cli-server'
    || !preg_match('/^vd_patient_offline_[a-f0-9]{12}$/', $database)
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(404);
    exit;
}

$_ENV['DATABASE_NAME'] = $database;
$root = realpath(dirname(__DIR__));
$requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$candidate = realpath($root . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $requestPath), DIRECTORY_SEPARATOR));

if ($candidate === false || ($candidate !== $root && !str_starts_with($candidate, $root . DIRECTORY_SEPARATOR))) {
    http_response_code(404);
    exit;
}

if (is_dir($candidate)) $candidate = realpath($candidate . DIRECTORY_SEPARATOR . 'index.php') ?: '';
if ($candidate === '' || !is_file($candidate)) {
    http_response_code(404);
    exit;
}

if (strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== 'php') return false;

chdir(dirname($candidate));
require $candidate;
return true;
