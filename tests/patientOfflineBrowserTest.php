<?php
// Run: C:\xampp\php\php.exe tests/patientOfflineBrowserTest.php
// Creates a disposable schema-only database and serves the app on loopback.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/conn.php';

$bootstrapCss = file_get_contents(__DIR__ . '/../public/css/bootstrap.min.css');
$bootstrapJs = file_get_contents(__DIR__ . '/../public/js/bootstrap.bundle.min.js');
preg_match('/Bootstrap\s+v([0-9.]+)/', $bootstrapCss ?: '', $cssVersion);
preg_match('/Bootstrap\s+v([0-9.]+)/', $bootstrapJs ?: '', $jsVersion);
if (($cssVersion[1] ?? '') === '' || ($cssVersion[1] ?? '') !== ($jsVersion[1] ?? '')) {
    throw new RuntimeException('Local Bootstrap CSS/JS versions do not match.');
}
echo 'Local Bootstrap CSS/JS version: ' . $cssVersion[1] . "\n";

$live = (new Database())->connect();
if (!$live) throw new RuntimeException('Schema source unavailable.');
$sourceName = (string) $live->query('SELECT DATABASE()')->fetchColumn();
$database = 'vd_patient_offline_' . bin2hex(random_bytes(6));
$live->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$db = new PDO("mysql:host=localhost;dbname={$database}", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function offlineFixture(PDO $db, string $table, array $values): int
{
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

$server = null;
$pipes = [];
$sessionDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vd_patient_offline_sessions_' . bin2hex(random_bytes(6));
$log = $sessionDir . DIRECTORY_SEPARATOR . 'server.log';
$exitCode = 1;

try {
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

    $password = 'OfflineTest123';
    $email = 'patient.offline@example.test';
    $userId = offlineFixture($db, 'users', [
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'email_verified_at' => date('Y-m-d H:i:s'),
        'user_role' => 'Patient',
    ]);
    $patientId = offlineFixture($db, 'patients', [
        'user_id' => $userId,
        'firstname' => 'Offline',
        'middlename' => 'Browser',
        'lastname' => 'Patient',
        'age' => 26,
        'gender' => 'Female',
        'phone_number' => '09170000001',
        'email' => $email,
        'birthdate' => '2000-01-01',
        'civil_status' => 'Single',
        'home_address' => 'Isolated Test Address',
        'profile_status' => 'Complete',
        'profile_completed_at' => date('Y-m-d H:i:s'),
    ]);
    offlineFixture($db, 'site_settings', [
        'id' => 1,
        'deposit_amount' => 400,
        'payment_deadline_minutes' => 480,
        'minimum_patient_age_years' => 1,
        'minimum_booking_lead_days' => 7,
        'minimum_reschedule_lead_days' => 3,
        'gcash_account_name' => 'Offline Test Clinic',
        'gcash_account_number' => '09170000002',
        'gcash_qr_path' => 'public/assets/gcash_qr_c3cf08d76a38b646.jpg',
    ]);
    $clinicA = offlineFixture($db, 'clinics', [
        'clinic_name' => 'Offline Alcala Branch',
        'clinic_address' => 'Alcala, Cagayan',
        'clinic_contact' => '09170000003',
    ]);
    $clinicB = offlineFixture($db, 'clinics', [
        'clinic_name' => 'Offline Tuguegarao Branch',
        'clinic_address' => 'Tuguegarao, Cagayan',
        'clinic_contact' => '09170000004',
    ]);
    $category = offlineFixture($db, 'service_categories', ['category_name' => 'Offline Preventive Care']);
    $service = offlineFixture($db, 'services', [
        'category_id' => $category,
        'service_name' => 'Offline Dental Cleaning',
        'service_description' => 'Synthetic service for the external-network regression.',
        'default_price' => 1000,
        'is_active' => 1,
    ]);

    $schedule = static function (PDO $db, int $clinic, int $days): int {
        return offlineFixture($db, 'schedules', [
            'clinic_id' => $clinic,
            'sched_date' => date('Y-m-d', strtotime("+{$days} days")),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'max_appointments' => 15,
        ]);
    };
    $scheduleA = $schedule($db, $clinicA, 10);
    $scheduleB = $schedule($db, $clinicA, 11);
    $rescheduleTarget = $schedule($db, $clinicB, 12);
    $depositSchedule = $schedule($db, $clinicA, 14);

    $appointment = static function (PDO $db, int $patient, int $scheduleId, int $clinic, int $days, string $status, string $code): int {
        return offlineFixture($db, 'appointments', [
            'patient_id' => $patient,
            'schedule_id' => $scheduleId,
            'clinic_id' => $clinic,
            'date' => date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . "{$days} days")),
            'status' => $status,
            'appointment_code' => $code,
            'confirmed_at' => $status === 'Confirmed' ? date('Y-m-d H:i:s') : null,
        ]);
    };
    $confirmedA = $appointment($db, $patientId, $scheduleA, $clinicA, 10, 'Confirmed', 'OFFLINE-A');
    $confirmedB = $appointment($db, $patientId, $scheduleB, $clinicA, 11, 'Confirmed', 'OFFLINE-B');
    $depositAppointment = $appointment($db, $patientId, $depositSchedule, $clinicA, 14, 'Awaiting Deposit', 'OFFLINE-PAY');
    $db->prepare('UPDATE appointments SET payment_deadline_at = DATE_ADD(NOW(), INTERVAL 8 HOUR) WHERE appointment_id = ?')->execute([$depositAppointment]);
    foreach ([$confirmedA, $confirmedB, $depositAppointment] as $id) {
        offlineFixture($db, 'appointment_services', ['appointment_id' => $id, 'service_id' => $service]);
    }
    offlineFixture($db, 'appointment_deposits', [
        'appointment_id' => $confirmedA,
        'amount' => 400,
        'status' => 'Verified',
        'verified_at' => date('Y-m-d H:i:s'),
    ]);
    offlineFixture($db, 'appointment_deposits', [
        'appointment_id' => $confirmedB,
        'amount' => 400,
        'status' => 'Verified',
        'verified_at' => date('Y-m-d H:i:s'),
    ]);
    offlineFixture($db, 'appointment_deposits', [
        'appointment_id' => $depositAppointment,
        'amount' => 400,
        'status' => 'Awaiting Submission',
    ]);
    offlineFixture($db, 'appointment_reschedule_requests', [
        'appointment_id' => $confirmedB,
        'requested_by_user_id' => $userId,
        'original_schedule_id' => $scheduleB,
        'original_clinic_id' => $clinicA,
        'original_date' => date('Y-m-d', strtotime('+11 days')),
        'target_schedule_id' => $rescheduleTarget,
        'target_clinic_id' => $clinicB,
        'target_date' => date('Y-m-d', strtotime('+12 days')),
        'reason' => 'Offline browser regression request',
        'status' => 'Pending',
        'lead_days_snapshot' => 3,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
    ]);

    $pastSchedule = offlineFixture($db, 'schedules', [
        'clinic_id' => $clinicA,
        'sched_date' => date('Y-m-d', strtotime('-1 day')),
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'max_appointments' => 15,
    ]);
    $past = $appointment($db, $patientId, $pastSchedule, $clinicA, -1, 'Completed', 'OFFLINE-HISTORY');
    $db->prepare('UPDATE appointments SET completed_at = NOW() WHERE appointment_id = ?')->execute([$past]);
    offlineFixture($db, 'appointment_services', ['appointment_id' => $past, 'service_id' => $service]);

    $conversation = offlineFixture($db, 'clinic_conversations', ['patient_user_id' => $userId]);
    offlineFixture($db, 'clinic_messages', [
        'conversation_id' => $conversation,
        'sender_id' => $userId,
        'sender_role' => 'Patient',
        'body' => 'Existing offline test conversation.',
        'request_key' => bin2hex(random_bytes(16)),
    ]);

    if (!mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) throw new RuntimeException('Cannot create test session directory.');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException($error);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);

    $environment = getenv();
    $environment['VD_PATIENT_OFFLINE_TEST_DATABASE'] = $database;
    $server = proc_open(
        [PHP_BINARY, '-d', 'session.save_path=' . $sessionDir, '-S', $address, __DIR__ . '/patientOfflineTestRouter.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
        $pipes,
        dirname(__DIR__),
        $environment
    );
    if (!is_resource($server)) throw new RuntimeException('Test server failed to start.');

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }

    echo "Isolated patient browser database: {$database}\n";
    echo "Loopback application: http://{$address}\n";
    $testEnvironment = $environment;
    $testEnvironment['PATIENT_OFFLINE_BASE_URL'] = 'http://' . $address;
    $testEnvironment['PATIENT_OFFLINE_EMAIL'] = $email;
    $testEnvironment['PATIENT_OFFLINE_PASSWORD'] = $password;
    $testEnvironment['PATIENT_OFFLINE_UPLOAD'] = realpath(__DIR__ . '/../public/assets/site_logo_1785381335.png');

    $test = proc_open(
        ['node', __DIR__ . '/patientOfflineBrowserTest.js'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $testPipes,
        dirname(__DIR__),
        $testEnvironment
    );
    if (!is_resource($test)) throw new RuntimeException('Unable to launch the browser regression. Run npm install first.');
    fclose($testPipes[0]);
    $stdout = stream_get_contents($testPipes[1]);
    $stderr = stream_get_contents($testPipes[2]);
    fclose($testPipes[1]);
    fclose($testPipes[2]);
    $exitCode = proc_close($test);
    echo $stdout;
    if ($stderr !== '') fwrite(STDERR, $stderr);
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
        proc_close($server);
    }
    if (isset($db)) $db = null;
    if (isset($live) && $live instanceof PDO) {
        $live->exec("DROP DATABASE IF EXISTS `{$database}`");
        echo "Removed isolated database: {$database}\n";
    }
    if (is_dir($sessionDir)) {
        foreach (glob($sessionDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) @unlink($file);
        @rmdir($sessionDir);
    }
}

exit($exitCode);
