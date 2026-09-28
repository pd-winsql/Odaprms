<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../../config/conn.php';

$backupRoot = realpath(__DIR__ . '/../backups');
$backupDir = realpath($argv[1] ?? '');
if (!$backupRoot || !$backupDir || !str_starts_with($backupDir, $backupRoot . DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "Pass a verified backup directory under database/backups.\n");
    exit(1);
}

$manifestPath = $backupDir . '/manifest.json';
$dumpPath = $backupDir . '/database.sql';
if (!is_file($manifestPath) || !is_file($dumpPath)) {
    fwrite(STDERR, "Backup manifest or SQL export is missing.\n");
    exit(1);
}

try {
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['source_database'] ?? '') !== 'db-oaprms-system'
        || !hash_equals(strtolower((string) ($manifest['sql_sha256'] ?? '')), strtolower((string) hash_file('sha256', $dumpPath)))
        || (int) ($manifest['verified_restore_tables'] ?? 0) !== 31
        || (int) ($manifest['verified_restore_views'] ?? 0) !== 5
        || (int) ($manifest['verified_table_count_mismatches'] ?? -1) !== 0
        || (int) ($manifest['verified_receipt_hash_mismatches'] ?? -1) !== 0) {
        throw new RuntimeException('Backup verification failed.');
    }

    $receiptRoot = realpath(__DIR__ . '/../../storage/payment_receipts');
    if (!$receiptRoot) {
        throw new RuntimeException('Receipt storage is unavailable.');
    }
    $manifestReceiptNames = [];
    foreach ($manifest['receipts'] ?? [] as $receipt) {
        $name = (string) ($receipt['file'] ?? '');
        $hash = strtolower((string) ($receipt['sha256'] ?? ''));
        if ($name === '' || basename($name) !== $name || isset($manifestReceiptNames[$name])) {
            throw new RuntimeException('Backup receipt manifest is invalid.');
        }
        $liveFile = $receiptRoot . DIRECTORY_SEPARATOR . $name;
        $backupFile = $backupDir . '/payment_receipts/' . $name;
        if (!is_file($liveFile) || !is_file($backupFile)
            || !hash_equals($hash, strtolower((string) hash_file('sha256', $liveFile)))
            || !hash_equals($hash, strtolower((string) hash_file('sha256', $backupFile)))) {
            throw new RuntimeException('A receipt differs from the verified backup.');
        }
        $manifestReceiptNames[$name] = true;
    }

    $conn = (new Database())->connect();
    if (!$conn || $conn->query('SELECT DATABASE()')->fetchColumn() !== 'db-oaprms-system') {
        throw new RuntimeException('The active application database is unavailable or unexpected.');
    }

    $clearTables = [
        'clinic_messages', 'clinic_conversations',
        'appointment_email_notifications',
        'appointment_billing_items', 'appointment_billings',
        'appointment_checkins', 'appointment_reviews',
        'appointment_reschedule_requests', 'appointment_services',
        'appointment_deposits',
        'patient_odontogram_snapshots', 'patient_odontogram_teeth',
        'patient_odontograms', 'patient_duplicate_reviews',
        'patient_account_link_authorizations', 'patient_conditions',
        'patient_consent', 'patient_dental_history', 'patient_medical_history',
        'audit_logs', 'appointments', 'schedules', 'patients',
        'email_verifications', 'password_resets',
    ];
    $keepTables = ['clinics', 'service_categories', 'services', 'site_settings', 'staffs', 'users'];
    $actualTables = $conn->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
    $expectedTables = array_merge($clearTables, $keepTables);
    sort($actualTables);
    sort($expectedTables);
    if ($actualTables !== $expectedTables) {
        throw new RuntimeException('The live table list differs from the reviewed inventory.');
    }

    $liveReceiptNames = [];
    foreach ($conn->query('SELECT receipt_path FROM appointment_deposits WHERE receipt_path IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $path) {
        $path = str_replace('\\', '/', (string) $path);
        if (!preg_match('~^storage/payment_receipts/([^/]+)$~', $path, $matches)) {
            throw new RuntimeException('A receipt path is outside the expected storage folder.');
        }
        $liveReceiptNames[$matches[1]] = true;
    }
    $diskReceiptNames = [];
    foreach (new DirectoryIterator($receiptRoot) as $file) {
        if ($file->isFile() && $file->getFilename() !== '.gitignore') {
            $diskReceiptNames[$file->getFilename()] = true;
        }
    }
    ksort($manifestReceiptNames);
    ksort($liveReceiptNames);
    ksort($diskReceiptNames);
    if ($manifestReceiptNames !== $liveReceiptNames || $manifestReceiptNames !== $diskReceiptNames) {
        throw new RuntimeException('Current receipt records or files differ from the verified backup.');
    }

    $preserveQueries = [
        'clinics' => 'SELECT * FROM clinics ORDER BY clinic_id',
        'service_categories' => 'SELECT * FROM service_categories ORDER BY category_id',
        'services' => 'SELECT * FROM services ORDER BY service_id',
        'site_settings' => 'SELECT * FROM site_settings ORDER BY id',
        'staffs' => 'SELECT * FROM staffs ORDER BY staff_id',
        'staff_users' => "SELECT * FROM users WHERE user_role IN ('Admin', 'Dental Assistant') ORDER BY id",
    ];
    $snapshot = static function () use ($conn, $preserveQueries): array {
        $rows = [];
        foreach ($preserveQueries as $name => $query) {
            $rows[$name] = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
        }
        return $rows;
    };
    $before = $snapshot();
    if (count($before['staff_users']) < 1 || count($before['site_settings']) !== 1) {
        throw new RuntimeException('Essential staff access or site settings are missing.');
    }
    if (($argv[2] ?? '') === '--check') {
        echo json_encode([
            'preflight' => 'passed',
            'tables_to_clear' => count($clearTables),
            'patient_users' => (int) $conn->query("SELECT COUNT(*) FROM users WHERE user_role = 'Patient'")->fetchColumn(),
            'receipt_files_backed_up' => count($manifestReceiptNames),
            'staff_users_to_preserve' => count($before['staff_users']),
        ], JSON_PRETTY_PRINT), PHP_EOL;
        exit(0);
    }

    $removed = [];
    $conn->beginTransaction();
    foreach ($clearTables as $table) {
        $removed[$table] = $conn->exec('DELETE FROM `' . $table . '`');
    }
    $removed['patient_users'] = $conn->exec("DELETE FROM users WHERE user_role = 'Patient'");

    foreach ($clearTables as $table) {
        if ((int) $conn->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() !== 0) {
            throw new RuntimeException('A test-data table was not emptied: ' . $table);
        }
    }
    if ((int) $conn->query("SELECT COUNT(*) FROM users WHERE user_role = 'Patient'")->fetchColumn() !== 0
        || $snapshot() !== $before) {
        throw new RuntimeException('Preserved records changed during the reset.');
    }
    $conn->commit();
    echo json_encode(['removed' => $removed, 'preserved_staff_users' => count($before['staff_users'])], JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
