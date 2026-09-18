<?php
// CLI-only HTTP regression checks; never expose a test authentication route.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/conn.php';
ob_start(); // Keep session headers available while collecting test output.
$baseUrl = rtrim($argv[1] ?? 'http://localhost/Capstone%20System', '/');
function requestStatus(string $url, string $cookie = ''): int {
    $request = curl_init($url);
    curl_setopt_array($request, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIE => $cookie,
    ]);
    curl_exec($request); // Do not print secret files or receipt contents.
    $error = curl_error($request);
    $status = (int) curl_getinfo($request, CURLINFO_HTTP_CODE);
    curl_close($request);
    if ($error !== '') throw new RuntimeException($error);
    return $status;
}
function expectStatus(string $url, int $expected, string $cookie = ''): void {
    $actual = requestStatus($url, $cookie);
    if ($actual !== $expected) throw new RuntimeException("Expected {$expected}, got {$actual}: {$url}");
    echo "PASS: {$expected} {$url}\n";
}

foreach ([
    '/.env', '/.env.local', '/db-oaprms-system.sql', '/.git/config',
    '/tests/browserSession.php', '/database/', '/storage/payment_receipts/',
    '/backups/export.zip', '/backup.zip', '/index.php.bak', '/public/css/',
] as $path) {
    expectStatus($baseUrl . $path, 403);
}
expectStatus($baseUrl . '/index.php', 200);
expectStatus($baseUrl . '/public/css/styles.css', 200);
expectStatus($baseUrl . '/apps/controllers/depositController.php?action=receipt&deposit_id=1', 403);

$connection = (new Database())->connect();
if (!$connection) throw new RuntimeException('Database is unavailable for receipt regression checks.');
$receipt = $connection->query("SELECT deposit_id, receipt_path FROM appointment_deposits WHERE receipt_path IS NOT NULL AND receipt_path <> '' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$receipt) {
    echo "SKIP: No existing receipt available for authorized retrieval.\n";
    exit;
}
expectStatus($baseUrl . '/' . str_replace(' ', '%20', $receipt['receipt_path']), 403);

// Use the normal PHP session mechanism, not a web-accessible login bypass.
session_id(bin2hex(random_bytes(24)));
if (!session_start()) {
    throw new RuntimeException('Unable to create the temporary receipt-test session.');
}
$_SESSION['user_id'] = 7;
$_SESSION['user_role'] = 'Dental Assistant';
$cookie = session_name() . '=' . session_id();
session_write_close();
try {
    expectStatus($baseUrl . '/apps/controllers/depositController.php?action=receipt&deposit_id=' . (int) $receipt['deposit_id'], 200, $cookie);
} finally {
    if (session_start()) {
        $_SESSION = [];
        session_destroy();
    }
}
