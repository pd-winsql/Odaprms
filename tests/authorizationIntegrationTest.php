<?php
// Run: C:\xampp\php\php.exe tests/authorizationIntegrationTest.php
// Copies schema only. Never changes the live .env, data, or Apache configuration.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/conn.php';
ob_start();
$live = (new Database())->connect();
if (!$live) throw new RuntimeException('Schema source unavailable.');
$sourceName = (string) $live->query('SELECT DATABASE()')->fetchColumn();
$database = 'vd_auth_test_' . bin2hex(random_bytes(6));
$live->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$db = new PDO("mysql:host=localhost;dbname={$database}", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($db->query('SELECT DATABASE()')->fetchColumn() === $sourceName) throw new RuntimeException('Isolation failed.');
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
echo "Isolated database: {$database} (schema only; synthetic records)\n";

// Fill required fixture columns from metadata without importing application data.
function fixture(PDO $db, string $table, array $values): int {
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
    $users[$key] = fixture($db, 'users', ['email' => "{$key}@example.invalid", 'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'user_role' => $role, 'email_verified_at' => date('Y-m-d H:i:s')]);
    if ($role === 'Patient') $patients[$key] = fixture($db, 'patients', ['user_id' => $users[$key], 'firstname' => 'Synthetic' . strtoupper($key), 'lastname' => 'Patient', 'email' => "{$key}@example.invalid", 'birthdate' => '2000-01-01', 'gender' => 'Male', 'age' => 26, 'phone_number' => $key === 'a' ? '09111111111' : '09222222222']);
    else fixture($db, 'staffs', ['user_id' => $users[$key], 'firstname' => ucfirst($key), 'lastname' => 'Synthetic']);
}
fixture($db, 'site_settings', ['id' => 1, 'minimum_booking_lead_days' => 7, 'minimum_reschedule_lead_days' => 3]);
$clinic = fixture($db, 'clinics', ['clinic_name' => 'Synthetic Clinic']);
$category = fixture($db, 'service_categories', ['category_name' => 'Synthetic Care']);
$service = fixture($db, 'services', ['category_id' => $category, 'service_name' => 'Synthetic Service', 'service_description' => 'Test only', 'is_active' => 1]);
$schedules = [];
foreach ([30, 32, 34] as $days) $schedules[] = fixture($db, 'schedules', ['clinic_id' => $clinic, 'sched_date' => date('Y-m-d', strtotime("+{$days} days")), 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'max_appointments' => 20]);
$appointments = [];
foreach (['a', 'b'] as $key) {
    $appointments[$key] = fixture($db, 'appointments', ['patient_id' => $patients[$key], 'schedule_id' => $schedules[0], 'clinic_id' => $clinic, 'date' => date('Y-m-d', strtotime('+30 days')), 'status' => 'Confirmed', 'appointment_code' => 'AUTH-' . strtoupper($key)]);
    fixture($db, 'appointment_services', ['appointment_id' => $appointments[$key], 'service_id' => $service]);
    fixture($db, 'appointment_deposits', ['appointment_id' => $appointments[$key], 'amount' => 400, 'status' => 'Verified']);
}
$payment = fixture($db, 'appointments', ['patient_id' => $patients['b'], 'schedule_id' => $schedules[2], 'clinic_id' => $clinic, 'date' => date('Y-m-d', strtotime('+34 days')), 'status' => 'Awaiting Deposit', 'payment_deadline_at' => date('Y-m-d H:i:s', strtotime('+1 day')), 'appointment_code' => 'AUTH-B-PAY']);
fixture($db, 'appointment_services', ['appointment_id' => $payment, 'service_id' => $service]);
$paymentDeposit = fixture($db, 'appointment_deposits', ['appointment_id' => $payment, 'amount' => 400, 'status' => 'Awaiting Submission']);
$visit = fixture($db, 'appointments', ['patient_id' => $patients['b'], 'schedule_id' => $schedules[0], 'clinic_id' => $clinic, 'date' => date('Y-m-d'), 'status' => 'In Progress', 'appointment_code' => 'AUTH-B-VISIT']);
fixture($db, 'appointment_services', ['appointment_id' => $visit, 'service_id' => $service]);
$conversations = [];
foreach (['a', 'b'] as $key) {
    $conversations[$key] = fixture($db, 'clinic_conversations', ['patient_user_id' => $users[$key]]);
    fixture($db, 'clinic_messages', ['conversation_id' => $conversations[$key], 'sender_id' => $users[$key], 'sender_role' => 'Patient', 'body' => 'PRIVATE-' . strtoupper($key), 'request_key' => bin2hex(random_bytes(16))]);
}

$sessionDir = sys_get_temp_dir() . '/vd_auth_sessions_' . bin2hex(random_bytes(6));
if (!mkdir($sessionDir, 0700)) throw new RuntimeException('Cannot create isolated session directory.');
session_save_path($sessionDir);
$sessions = [];
foreach ($users as $key => $id) {
    session_id(bin2hex(random_bytes(24)));
    if (!session_start()) throw new RuntimeException('Cannot create test session.');
    $token = bin2hex(random_bytes(32));
    $_SESSION = ['user_id' => $id, 'user_role' => $key === 'assistant' ? 'Dental Assistant' : ($key === 'admin' ? 'Admin' : 'Patient'), 'csrf_token' => $token];
    $sessions[$key] = ['cookie' => session_name() . '=' . session_id(), 'token' => $token, 'file' => $sessionDir . '/sess_' . session_id()];
    session_write_close();
}
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$socket) throw new RuntimeException($error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$log = $sessionDir . '/server.log';
$environment = getenv();
$environment['VD_AUTH_TEST_DATABASE'] = $database;
$server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $sessionDir, '-S', $address, __DIR__ . '/authorizationTestRouter.php'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, dirname(__DIR__), $environment);
if (!is_resource($server)) throw new RuntimeException('Test server failed to start.');
$base = 'http://' . $address;
$results = [];
function check(bool $ok, string $label): void {
    global $results;
    $results[] = ($ok ? 'PASS' : 'FAIL') . ': ' . $label;
    echo end($results) . "\n";
}
function httpTest(string $key, string $path, ?array $post = null): array {
    global $base, $sessions;
    $curl = curl_init($base . $path);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_COOKIE => $sessions[$key]['cookie']];
    if ($post !== null) $options += [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post + ['csrf_token' => $sessions[$key]['token']])];
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
}
function snapshot(PDO $db): string {
    $state = [];
    foreach (['patients', 'patient_medical_history', 'patient_dental_history', 'patient_conditions', 'patient_consent', 'appointments', 'appointment_deposits', 'appointment_services', 'appointment_billings', 'appointment_billing_items', 'appointment_reschedule_requests', 'clinic_messages', 'clinic_conversations', 'audit_logs', 'appointment_email_notifications'] as $table) {
        $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        $encoded = array_map('json_encode', $rows);
        sort($encoded);
        $state[$table] = $encoded;
    }
    return hash('sha256', json_encode($state));
}
function deniedUnchanged(PDO $db, string $label, string $key, string $path, array $post, string $message): void {
    $before = snapshot($db);
    $response = httpTest($key, $path, $post);
    check(($response['json']['success'] ?? null) === false && str_contains($response['json']['message'] ?? '', $message)
        && snapshot($db) === $before, $label . ' (denied; database unchanged)');
}
try {
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    $tamper = '?patient_id=' . $patients['b'] . '&user_id=' . $users['b'] . '&appointment_id=' . $appointments['b'];
    $profile = httpTest('a', '/apps/views/patient/partials/profile-content.php' . $tamper);
    check($profile['status'] === 200 && str_contains($profile['body'], 'SyntheticA') && !str_contains($profile['body'], 'SyntheticB'), 'Tampered profile IDs expose only patient A');
    $ownB = httpTest('b', '/apps/views/patient/partials/profile-content.php');
    check($ownB['status'] === 200 && str_contains($ownB['body'], 'SyntheticB'), 'Patient B can read their own profile');
    $deposit = httpTest('a', '/apps/views/patient/partials/billing-content.php' . $tamper);
    $ownDeposit = httpTest('b', '/apps/views/patient/partials/billing-content.php');
    check($deposit['status'] === 200 && $ownDeposit['status'] === 200 && !str_contains($deposit['body'], 'AUTH-B-PAY') && !str_contains($deposit['body'], 'data-deposit-card="' . $paymentDeposit . '"') && str_contains($ownDeposit['body'], 'data-deposit-card="' . $paymentDeposit . '"'), 'Deposits remain owner-scoped with tampered IDs; B positive control');
    $feed = httpTest('a', '/apps/controllers/appointmentController.php?action=patientNotificationSnapshot&user_id=' . $users['b']);
    $feedIds = array_map('intval', array_column($feed['json']['appointments'] ?? [], 'appointment_id'));
    $feedB = httpTest('b', '/apps/controllers/appointmentController.php?action=patientNotificationSnapshot');
    $feedBIds = array_map('intval', array_column($feedB['json']['appointments'] ?? [], 'appointment_id'));
    check(($feed['json']['success'] ?? false) && $feedIds === [$appointments['a']]
        && in_array($appointments['b'], $feedBIds, true) && !in_array($appointments['a'], $feedBIds, true), 'Appointment feed ignores tampered user ID; each owner sees their records');
    $receipt = httpTest('a', '/apps/controllers/depositController.php?action=receipt&deposit_id=' . $paymentDeposit);
    check($receipt['status'] === 403 && ($receipt['json']['success'] ?? null) === false, 'Patient cannot access staff receipt endpoint with another deposit ID');
    deniedUnchanged($db, 'Patient cannot submit another patient deposit', 'a', '/apps/controllers/depositController.php', ['action' => 'submit', 'appointment_id' => $payment], 'access denied');
    $ownPayment = httpTest('b', '/apps/controllers/depositController.php', ['action' => 'submit', 'appointment_id' => $payment]);
    check(str_contains($ownPayment['json']['message'] ?? '', 'reference number'), 'Owner deposit request passes ownership check to receipt validation');
    deniedUnchanged($db, 'Patient cannot reschedule another appointment', 'a', '/apps/controllers/rescheduleController.php', ['action' => 'submit', 'appointment_id' => $appointments['b'], 'target_schedule_id' => $schedules[1], 'reason' => 'Synthetic reschedule reason'], 'Only your confirmed');
    $request = httpTest('b', '/apps/controllers/rescheduleController.php', ['action' => 'submit', 'appointment_id' => $appointments['b'], 'target_schedule_id' => $schedules[1], 'reason' => 'Synthetic reschedule reason']);
    check(($request['json']['success'] ?? false), 'Owner can request rescheduling');
    $requestId = (int) ($request['json']['request_id'] ?? 0);
    if ($requestId <= 0) throw new RuntimeException('Reschedule positive control failed: ' . ($request['json']['message'] ?? 'Invalid response'));
    deniedUnchanged($db, 'Patient cannot withdraw another reschedule', 'a', '/apps/controllers/rescheduleController.php', ['action' => 'withdraw', 'request_id' => $requestId], '');
    $messages = httpTest('a', '/apps/controllers/chatController.php?action=messages&conversation_id=' . $conversations['b']);
    check($messages['status'] === 403 && ($messages['json']['success'] ?? null) === false && !str_contains($messages['body'], 'PRIVATE-B'), 'Other patient conversation read returns 403');
    $ownMessages = httpTest('a', '/apps/controllers/chatController.php?action=messages&conversation_id=' . $conversations['a']);
    check(($ownMessages['json']['success'] ?? false) && str_contains($ownMessages['body'], 'PRIVATE-A'), 'Patient can read their own conversation');
    deniedUnchanged($db, 'Patient cannot send into another conversation', 'a', '/apps/controllers/chatController.php', ['action' => 'send', 'conversation_id' => $conversations['b'], 'body' => 'Unauthorized test', 'request_key' => bin2hex(random_bytes(16))], 'Conversation not available');
    deniedUnchanged($db, 'Patient cannot mark another conversation read', 'a', '/apps/controllers/chatController.php', ['action' => 'read', 'conversation_id' => $conversations['b'], 'through' => 999], 'Conversation not available');
    deniedUnchanged($db, 'Patient cannot use staff profile editing', 'a', '/apps/controllers/patientController.php', ['action' => 'completeProfileByStaff', 'patient_id' => $patients['b']], 'Forbidden');
    $beforeB = $db->query('SELECT * FROM patients WHERE patient_id=' . $patients['b'])->fetch(PDO::FETCH_ASSOC);
    $save = httpTest('a', '/apps/controllers/patientController.php', ['action' => 'saveOwnProfile', 'patient_id' => $patients['b'], 'user_id' => $users['b'], 'firstname' => 'SyntheticAUpdated', 'lastname' => 'Patient', 'birthdate' => '2000-01-01', 'gender' => 'Male']);
    check(($save['json']['success'] ?? false) && $beforeB === $db->query('SELECT * FROM patients WHERE patient_id=' . $patients['b'])->fetch(PDO::FETCH_ASSOC)
        && $db->query('SELECT firstname FROM patients WHERE patient_id=' . $patients['a'])->fetchColumn() === 'SyntheticAUpdated', 'Tampered profile save updates only session owner; B unchanged');
    $settlement = ['action' => 'settleAndComplete', 'appointment_id' => $visit, 'service_amount' => 1000, 'cash_received' => 1000, 'service_ids' => [$service]];
    deniedUnchanged($db, 'Assistant cannot settle an in-progress visit', 'assistant', '/apps/controllers/billingController.php', $settlement, 'Forbidden');
    $assistant = httpTest('assistant', '/apps/controllers/billingController.php', $settlement);
    check($assistant['status'] === 403, 'Assistant settlement explicitly returns HTTP 403');
    deniedUnchanged($db, 'Patient cannot settle a visit', 'a', '/apps/controllers/billingController.php', $settlement, 'Forbidden');
    $admin = httpTest('admin', '/apps/controllers/billingController.php', $settlement);
    check(($admin['json']['success'] ?? false) && $db->query('SELECT status FROM appointments WHERE appointment_id=' . $visit)->fetchColumn() === 'Completed'
        && (int) $db->query('SELECT recorded_by_user_id FROM appointment_billings WHERE appointment_id=' . $visit)->fetchColumn() === $users['admin'], 'Admin positive control settles and completes visit');
    $historyA = httpTest('a', '/apps/views/patient/partials/history-content.php' . $tamper);
    $historyB = httpTest('b', '/apps/views/patient/partials/history-content.php');
    check($historyA['status'] === 200 && $historyB['status'] === 200 && !str_contains($historyA['body'], 'AUTH-B-VISIT') && str_contains($historyB['body'], 'AUTH-B-VISIT')
        && str_contains($historyB['body'], '&quot;actualCharge&quot;:1000'), 'Completed visit and final billing appear only in owner history');
} finally {
    proc_terminate($server);
    fclose($pipes[0]);
    proc_close($server);
    foreach ($sessions as $session) if (is_file($session['file'])) unlink($session['file']);
    echo "Test database retained: {$database}\nServer stopped; temporary sessions removed. Server log: {$log}\n";
}
$failures = count(array_filter($results, static fn(string $result): bool => str_starts_with($result, 'FAIL')));
echo count($results) . " checks; {$failures} failures.\n";
ob_end_flush();
exit($failures ? 1 : 0);
