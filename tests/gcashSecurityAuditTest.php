<?php
// Run: php tests/gcashSecurityAuditTest.php
// Creates and removes a schema-only isolated database and synthetic receipt files.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/conn.php';

$live = (new Database())->connect();
if (!$live) throw new RuntimeException('Schema source unavailable.');
$sourceName = (string) $live->query('SELECT DATABASE()')->fetchColumn();
$database = 'vd_gcash_test_' . bin2hex(random_bytes(6));
$live->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$db = new PDO("mysql:host=localhost;dbname={$database}", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ((string) $db->query('SELECT DATABASE()')->fetchColumn() === $sourceName) {
    throw new RuntimeException('Isolation failed.');
}

$db->exec('SET FOREIGN_KEY_CHECKS=0');
$views = [];
foreach ($live->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$table, $type]) {
    if ($type === 'VIEW') { $views[] = $table; continue; }
    $ddl = $live->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
    $ddl = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $ddl);
    $db->exec($ddl);
}
foreach ($views as $view) {
    $ddl = $live->query("SHOW CREATE VIEW `{$view}`")->fetch(PDO::FETCH_NUM)[1];
    $ddl = str_replace('`' . $sourceName . '`.', '`' . $database . '`.', $ddl);
    $db->exec($ddl);
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');
$live = null;

function gcashFixture(PDO $db, string $table, array $values): int {
    foreach ($db->query("SHOW COLUMNS FROM `{$table}`") as $column) {
        $name = $column['Field'];
        if (array_key_exists($name, $values) || $column['Null'] === 'YES'
            || $column['Default'] !== null || str_contains($column['Extra'], 'auto_increment')) continue;
        $type = $column['Type'];
        $values[$name] = preg_match('/^(?:int|tinyint|smallint|bigint|decimal|float|double)/', $type) ? 1 : 'Synthetic';
        if (preg_match("/^enum\('([^']+)'/", $type, $match)) $values[$name] = $match[1];
        if ($type === 'date') $values[$name] = date('Y-m-d');
        if (in_array($type, ['datetime', 'timestamp'], true)) $values[$name] = date('Y-m-d H:i:s');
    }
    $columns = '`' . implode('`,`', array_keys($values)) . '`';
    $stmt = $db->prepare("INSERT INTO `{$table}` ({$columns}) VALUES (" . implode(',', array_fill(0, count($values), '?')) . ')');
    $stmt->execute(array_values($values));
    return (int) $db->lastInsertId();
}

$users = [];
$patients = [];
foreach (['a' => 'Patient', 'b' => 'Patient', 'assistant' => 'Dental Assistant', 'admin' => 'Admin'] as $key => $role) {
    $users[$key] = gcashFixture($db, 'users', [
        'email' => "gcash-{$key}@example.invalid",
        'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        'user_role' => $role,
        'email_verified_at' => date('Y-m-d H:i:s'),
    ]);
    if ($role === 'Patient') {
        $patients[$key] = gcashFixture($db, 'patients', [
            'user_id' => $users[$key], 'firstname' => 'Synthetic' . strtoupper($key),
            'lastname' => 'Receipt', 'email' => "gcash-{$key}@example.invalid",
            'birthdate' => '2000-01-01', 'gender' => 'Male', 'age' => 26,
            'phone_number' => $key === 'a' ? '09111111111' : '09222222222',
        ]);
    } else {
        gcashFixture($db, 'staffs', [
            'user_id' => $users[$key], 'firstname' => ucfirst($key),
            'lastname' => 'Synthetic', 'email' => "gcash-{$key}@example.invalid",
            'employment_status' => 'Active',
        ]);
    }
}

gcashFixture($db, 'site_settings', ['id' => 1, 'deposit_amount' => 200, 'payment_deadline_minutes' => 480]);
$clinic = gcashFixture($db, 'clinics', ['clinic_name' => 'Synthetic Receipt Clinic']);
$category = gcashFixture($db, 'service_categories', ['category_name' => 'Synthetic Care']);
$service = gcashFixture($db, 'services', [
    'category_id' => $category, 'service_name' => 'Synthetic Service',
    'service_description' => 'Test only', 'is_active' => 1,
]);

$appointments = [];
$deposits = [];
foreach ([30, 31, 32] as $index => $days) {
    $schedule = gcashFixture($db, 'schedules', [
        'clinic_id' => $clinic, 'sched_date' => date('Y-m-d', strtotime("+{$days} days")),
        'start_time' => '09:00:00', 'end_time' => '17:00:00', 'max_appointments' => 10,
    ]);
    $appointment = gcashFixture($db, 'appointments', [
        'patient_id' => $patients['b'], 'schedule_id' => $schedule, 'clinic_id' => $clinic,
        'date' => date('Y-m-d', strtotime("+{$days} days")), 'status' => 'Awaiting Deposit',
        'payment_deadline_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        'appointment_code' => 'GCASH-' . ($index + 1),
    ]);
    gcashFixture($db, 'appointment_services', ['appointment_id' => $appointment, 'service_id' => $service]);
    $appointments[] = $appointment;
    $deposits[] = gcashFixture($db, 'appointment_deposits', [
        'appointment_id' => $appointment, 'amount' => 200, 'status' => 'Awaiting Submission',
    ]);
}

$sessionDir = sys_get_temp_dir() . '/vd_gcash_sessions_' . bin2hex(random_bytes(6));
$fileDir = sys_get_temp_dir() . '/vd_gcash_files_' . bin2hex(random_bytes(6));
if (!mkdir($sessionDir, 0700) || !mkdir($fileDir, 0700)) throw new RuntimeException('Cannot create isolated temporary directories.');
session_save_path($sessionDir);
$sessions = [];
foreach ($users as $key => $id) {
    session_id(bin2hex(random_bytes(24)));
    if (!session_start()) throw new RuntimeException('Cannot create test session.');
    $token = bin2hex(random_bytes(32));
    $_SESSION = [
        'user_id' => $id,
        'user_role' => $key === 'assistant' ? 'Dental Assistant' : ($key === 'admin' ? 'Admin' : 'Patient'),
        'csrf_token' => $token,
    ];
    $sessions[$key] = ['cookie' => session_name() . '=' . session_id(), 'token' => $token, 'file' => $sessionDir . '/sess_' . session_id()];
    session_write_close();
}

$validPng = $fileDir . '/synthetic-valid.png';
$fakePng = $fileDir . '/synthetic-fake.png';
$svgPng = $fileDir . '/synthetic-svg.png';
$largePng = $fileDir . '/synthetic-large.png';
$corruptPng = $fileDir . '/synthetic-corrupt.png';
file_put_contents($validPng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
file_put_contents($fakePng, "not an image\n<script>alert(1)</script>");
file_put_contents($svgPng, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
file_put_contents($largePng, "\x89PNG\r\n\x1a\n" . str_repeat('A', 5 * 1024 * 1024 + 1));
file_put_contents($corruptPng, substr(file_get_contents($validPng), 0, 33));

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$socket) throw new RuntimeException($error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$log = $sessionDir . '/server.log';
$environment = getenv();
$environment['VD_GCASH_TEST_DATABASE'] = $database;
$server = proc_open(
    [PHP_BINARY, '-d', 'session.save_path=' . $sessionDir, '-S', $address, __DIR__ . '/gcashSecurityTestRouter.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
    $pipes,
    dirname(__DIR__),
    $environment
);
if (!is_resource($server)) throw new RuntimeException('Test server failed to start.');
$base = 'http://' . $address;
$results = [];
$createdReceiptPaths = [];

function gcashCheck(bool $ok, string $label): void {
    global $results;
    $results[] = ($ok ? 'PASS' : 'FAIL') . ': ' . $label;
    echo end($results) . PHP_EOL;
}

function gcashHttp(?string $key, string $path, array $post = [], ?string $file = null, bool $addCsrf = true): array {
    global $base, $sessions;
    $curl = curl_init($base . $path);
    $headers = [];
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HEADERFUNCTION => static function ($curl, $line) use (&$headers) {
        $length = strlen($line);
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
        return $length;
    }];
    if ($key !== null) $options[CURLOPT_COOKIE] = $sessions[$key]['cookie'];
    if ($post || $file !== null) {
        $fields = $post;
        if ($addCsrf && $key !== null) $fields['csrf_token'] = $sessions[$key]['token'];
        if ($file !== null) $fields['receipt'] = new CURLFile($file, mime_content_type($file), basename($file));
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $fields;
    }
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);
    $jsonBody = $body;
    $jsonStart = strrpos($body, '{"success"');
    if ($jsonStart !== false) $jsonBody = substr($body, $jsonStart);
    return ['status' => $status, 'body' => $body, 'json' => json_decode($jsonBody, true), 'headers' => $headers, 'content_type' => $contentType];
}

function submitFields(int $appointmentId, string $reference, string $amount = '200.00', ?string $date = null): array {
    return [
        'action' => 'submit', 'appointment_id' => $appointmentId,
        'gcash_reference' => $reference, 'receipt_amount' => $amount,
        'gcash_transaction_at' => $date ?? date('Y-m-d\TH:i', strtotime('-5 minutes')),
    ];
}

function depositSnapshot(PDO $db): string {
    $state = [];
    foreach (['appointments', 'appointment_deposits', 'audit_logs', 'appointment_email_notifications'] as $table) {
        $rows = $db->query("SELECT * FROM `{$table}` ORDER BY 1")->fetchAll(PDO::FETCH_ASSOC);
        $state[$table] = $rows;
    }
    return hash('sha256', json_encode($state));
}

try {
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }

    $before = depositSnapshot($db);
    $fake = gcashHttp('b', '/apps/controllers/depositController.php', submitFields($appointments[0], '100000000001'), $fakePng);
    gcashCheck(($fake['json']['success'] ?? null) === false
        && str_contains($fake['json']['message'] ?? '', 'JPG or PNG')
        && depositSnapshot($db) === $before, 'Text/HTML renamed as PNG is rejected with database unchanged');

    $svg = gcashHttp('b', '/apps/controllers/depositController.php', submitFields($appointments[0], '100000000002'), $svgPng);
    gcashCheck(($svg['json']['success'] ?? null) === false
        && depositSnapshot($db) === $before, 'Active SVG renamed as PNG is rejected with database unchanged');

    $large = gcashHttp('b', '/apps/controllers/depositController.php', submitFields($appointments[0], '100000000003'), $largePng);
    gcashCheck(($large['json']['success'] ?? null) === false
        && str_contains($large['json']['message'] ?? '', '5 MB')
        && depositSnapshot($db) === $before, 'Oversized receipt is rejected with database unchanged');

    $idor = gcashHttp('a', '/apps/controllers/depositController.php', submitFields($appointments[0], '100000000004'), $validPng);
    gcashCheck(($idor['json']['success'] ?? null) === false
        && str_contains($idor['json']['message'] ?? '', 'access denied')
        && depositSnapshot($db) === $before, 'Patient cannot upload a receipt to another patient appointment');

    $invalidReference = gcashHttp('b', '/apps/controllers/depositController.php', submitFields($appointments[0], '123'), $validPng);
    $wrongAmount = gcashHttp('b', '/apps/controllers/depositController.php', submitFields($appointments[0], '100000000005', '199.99'), $validPng);
    $futureDate = gcashHttp('b', '/apps/controllers/depositController.php', submitFields($appointments[0], '100000000006', '200.00', date('Y-m-d\TH:i', strtotime('+1 hour'))), $validPng);
    gcashCheck(($invalidReference['json']['success'] ?? null) === false
        && ($wrongAmount['json']['success'] ?? null) === false
        && ($futureDate['json']['success'] ?? null) === false
        && depositSnapshot($db) === $before, 'Reference length, amount mismatch, and future timestamp are rejected before storage');

    $corrupt = gcashHttp('b', '/apps/controllers/depositController.php', submitFields($appointments[2], '100000000007'), $corruptPng);
    $corruptRow = $db->query('SELECT receipt_path,status FROM appointment_deposits WHERE deposit_id=' . $deposits[2])->fetch(PDO::FETCH_ASSOC);
    if (!empty($corruptRow['receipt_path'])) $createdReceiptPaths[] = dirname(__DIR__) . '/' . $corruptRow['receipt_path'];
    gcashCheck(($corrupt['json']['success'] ?? null) === false
        && $corruptRow['status'] === 'Awaiting Submission', 'Truncated/corrupted PNG is rejected instead of entering manual review');

    $reference = '100000000010';
    $oldDate = '2000-01-01T12:00';
    $forged = gcashHttp('b', '/apps/controllers/depositController.php', submitFields($appointments[0], $reference, '200.00', $oldDate), $validPng);
    $row = $db->query('SELECT * FROM appointment_deposits WHERE deposit_id=' . $deposits[0])->fetch(PDO::FETCH_ASSOC);
    $savedPath = (string) ($row['receipt_path'] ?? '');
    if ($savedPath !== '') $createdReceiptPaths[] = dirname(__DIR__) . '/' . $savedPath;
    gcashCheck(($forged['json']['success'] ?? false)
        && $row['status'] === 'Under Review'
        && $row['gcash_reference'] === $reference
        && str_starts_with($savedPath, 'storage/payment_receipts/'), 'A structurally valid but unauthenticated receipt is accepted only into manual review');
    gcashCheck($row['gcash_transaction_at'] === '2000-01-01 12:00:00', 'No lower-bound plausibility check exists for receipt transaction time');

    $directCode = trim((string) shell_exec('curl.exe -sS -o NUL -w "%{http_code}" "http://localhost/Capstone%20System/' . str_replace(' ', '%20', $savedPath) . '"'));
    gcashCheck($directCode === '403', 'Direct HTTP access to the stored receipt is blocked by Apache');

    foreach ([null, 'a', 'b', 'admin'] as $key) {
        $receipt = gcashHttp($key, '/apps/controllers/depositController.php?action=receipt&deposit_id=' . $deposits[0]);
        $label = $key === null ? 'Anonymous' : ucfirst($key);
        gcashCheck($receipt['status'] === 403, "{$label} cannot retrieve a receipt through the staff endpoint");
    }
    $assistantReceipt = gcashHttp('assistant', '/apps/controllers/depositController.php?action=receipt&deposit_id=' . $deposits[0]);
    gcashCheck($assistantReceipt['status'] === 200
        && str_starts_with($assistantReceipt['content_type'], 'image/png')
        && strtolower($assistantReceipt['headers']['x-content-type-options'] ?? '') === 'nosniff'
        && str_contains(strtolower($assistantReceipt['headers']['cache-control'] ?? ''), 'no-store'), 'Authorized assistant receives image with nosniff and no-store caching');

    $underReviewSnapshot = depositSnapshot($db);
    foreach (['a', 'b', 'admin'] as $key) {
        $approval = gcashHttp($key, '/apps/controllers/depositController.php', ['action' => 'verify', 'deposit_id' => $deposits[0]]);
        gcashCheck($approval['status'] === 403 && depositSnapshot($db) === $underReviewSnapshot, ucfirst($key) . ' cannot approve a receipt');
    }
    $anonymousApproval = gcashHttp(null, '/apps/controllers/depositController.php', ['action' => 'verify', 'deposit_id' => $deposits[0]], null, false);
    gcashCheck(($anonymousApproval['json']['success'] ?? null) === false
        && depositSnapshot($db) === $underReviewSnapshot, 'Anonymous approval is rejected with database unchanged');
    $missingCsrf = gcashHttp('assistant', '/apps/controllers/depositController.php', ['action' => 'verify', 'deposit_id' => $deposits[0]], null, false);
    gcashCheck(($missingCsrf['json']['success'] ?? null) === false
        && depositSnapshot($db) === $underReviewSnapshot, 'Assistant approval without CSRF is rejected with database unchanged');

    $approved = gcashHttp('assistant', '/apps/controllers/depositController.php', ['action' => 'verify', 'deposit_id' => $deposits[0]]);
    gcashCheck(($approved['json']['success'] ?? false)
        && $db->query('SELECT status FROM appointment_deposits WHERE deposit_id=' . $deposits[0])->fetchColumn() === 'Verified'
        && $db->query('SELECT status FROM appointments WHERE appointment_id=' . $appointments[0])->fetchColumn() === 'Confirmed', 'Authorized assistant approval verifies the deposit and confirms the appointment');
    $approvedSnapshot = depositSnapshot($db);
    $repeat = gcashHttp('assistant', '/apps/controllers/depositController.php', ['action' => 'verify', 'deposit_id' => $deposits[0]]);
    gcashCheck(($repeat['json']['success'] ?? null) === false && depositSnapshot($db) === $approvedSnapshot, 'Repeated approval is idempotently rejected');

    $filesBeforeDuplicate = glob(dirname(__DIR__) . '/storage/payment_receipts/*') ?: [];
    $duplicate = gcashHttp('b', '/apps/controllers/depositController.php', submitFields($appointments[1], $reference), $validPng);
    $filesAfterDuplicate = glob(dirname(__DIR__) . '/storage/payment_receipts/*') ?: [];
    gcashCheck(($duplicate['json']['success'] ?? null) === false
        && str_contains($duplicate['json']['message'] ?? '', 'already been submitted')
        && $db->query('SELECT status FROM appointment_deposits WHERE deposit_id=' . $deposits[1])->fetchColumn() === 'Awaiting Submission'
        && count($filesBeforeDuplicate) === count($filesAfterDuplicate), 'Duplicate GCash reference is rejected and the temporary stored upload is removed');

    $db->prepare('UPDATE appointment_deposits SET receipt_path = :path WHERE deposit_id = :id')
        ->execute([':path' => '../../.env', ':id' => $deposits[0]]);
    $traversal = gcashHttp('assistant', '/apps/controllers/depositController.php?action=receipt&deposit_id=' . $deposits[0]);
    gcashCheck($traversal['status'] === 404 && !str_contains($traversal['body'], 'DATABASE_NAME'), 'Receipt path traversal is rejected without secret disclosure');
    $db->prepare('UPDATE appointment_deposits SET receipt_path = :path WHERE deposit_id = :id')
        ->execute([':path' => $savedPath, ':id' => $deposits[0]]);
} finally {
    if (isset($server) && is_resource($server)) {
        proc_terminate($server);
        if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
        proc_close($server);
    }
    foreach ($createdReceiptPaths as $path) if (is_file($path)) unlink($path);
    foreach (glob($sessionDir . '/*') ?: [] as $path) if (is_file($path)) unlink($path);
    foreach (glob($fileDir . '/*') ?: [] as $path) if (is_file($path)) unlink($path);
    if (is_dir($sessionDir)) rmdir($sessionDir);
    if (is_dir($fileDir)) rmdir($fileDir);
    $db = null;
    $cleanup = new PDO('mysql:host=localhost', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if (preg_match('/^vd_gcash_test_[a-f0-9]{12}$/', $database)) $cleanup->exec("DROP DATABASE `{$database}`");
    echo "Cleanup complete: isolated database, sessions, fixtures, and synthetic receipts removed.\n";
}

$failures = count(array_filter($results, static fn(string $result): bool => str_starts_with($result, 'FAIL')));
echo count($results) . " checks; {$failures} failures.\n";
exit($failures ? 1 : 0);
