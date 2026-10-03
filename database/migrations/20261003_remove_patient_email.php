<?php
declare(strict_types=1);
// CLI-only migration. Keep existing login addresses and historical mail recipients.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../../config/conn.php';
$db = (new Database())->connect();
if (!$db) { fwrite(STDERR, "Application database unavailable.\n"); exit(1); }
try {
    $column = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='patients' AND COLUMN_NAME='email'")->fetchColumn();
    if (!$column) { echo "patients.email is already removed.\n"; exit; }
    $unlinked = (int) $db->query("SELECT COUNT(*) FROM patients p LEFT JOIN users u ON u.id=p.user_id WHERE u.id IS NULL AND NULLIF(TRIM(p.email),'') IS NOT NULL")->fetchColumn();
    if ($unlinked) throw new RuntimeException('Unlinked patient contact emails require a preservation decision before removal.');
    $missing = (int) $db->query("SELECT COUNT(*) FROM patients p JOIN users u ON u.id=p.user_id WHERE NULLIF(TRIM(u.email),'') IS NULL")->fetchColumn();
    if ($missing) throw new RuntimeException('Linked accounts with missing emails must be resolved first.');
    $mismatches = (int) $db->query("SELECT COUNT(*) FROM patients p JOIN users u ON u.id=p.user_id WHERE NULLIF(TRIM(p.email),'') IS NOT NULL AND LOWER(TRIM(p.email))<>LOWER(TRIM(u.email))")->fetchColumn();
    echo "Preflight: {$mismatches} mismatched address(es); existing login emails remain unchanged.\n";
    $schema = $db->query('SELECT DATABASE()')->fetchColumn();
    $views = $db->query("SELECT TABLE_NAME, VIEW_DEFINITION, SECURITY_TYPE FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE()")->fetchAll(PDO::FETCH_ASSOC);
    $replacements = [];
    foreach ($views as $view) {
        $definition = $view['VIEW_DEFINITION'];
        if (!str_contains($definition, '`p`.`email`') || !str_contains($definition, '`patients` `p`')) continue;
        $table = '`' . str_replace('`', '``', $schema) . '`.`patients` `p`';
        if (substr_count($definition, $table) !== 1) throw new RuntimeException('Unexpected patients reference in view ' . $view['TABLE_NAME']);
        $definition = str_replace($table, '(' . $table . ' LEFT JOIN `users` `patient_account` ON (`patient_account`.`id`=`p`.`user_id`))', $definition);
        $definition = str_replace('`p`.`email`', '`patient_account`.`email`', $definition);
        $name = '`' . str_replace('`', '``', $view['TABLE_NAME']) . '`';
        $security = $view['SECURITY_TYPE'] === 'INVOKER' ? 'INVOKER' : 'DEFINER';
        $replacements[$view['TABLE_NAME']] = "CREATE OR REPLACE SQL SECURITY {$security} VIEW {$name} AS {$definition}";
    }
    if (!isset($replacements['vw_patient_information'], $replacements['vw_appointment_overview'])) throw new RuntimeException('Expected dependent views have an unexpected definition.');
    if (($argv[1] ?? '') !== '--apply') {
        echo 'Views to migrate: ' . implode(', ', array_keys($replacements)) . "\nRun with --apply and a full database backup path.\n";
        exit;
    }
    $backup = realpath($argv[2] ?? '');
    $backupRoot = realpath(__DIR__ . '/../backups');
    if (!$backup || !str_starts_with($backup, $backupRoot . DIRECTORY_SEPARATOR) || !is_file($backup) || filesize($backup) < 1000) throw new RuntimeException('A full dump in database/backups is required.');
    $dump = file_get_contents($backup);
    if (!str_contains($dump, 'CREATE TABLE `patients`') || !str_contains($dump, 'CREATE TABLE `users`') || !str_contains($dump, 'vw_patient_information')) throw new RuntimeException('Backup is missing required tables/views.');
    $patientCount = (int) $db->query('SELECT COUNT(*) FROM patients')->fetchColumn();
    $usersBefore = $db->query('SELECT id,email FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($replacements as $sql) $db->exec($sql);
    foreach (array_keys($replacements) as $name) $db->query('SELECT * FROM `' . $name . '` LIMIT 1')->fetch();
    $db->exec('ALTER TABLE patients DROP COLUMN email');
    if ((int) $db->query('SELECT COUNT(*) FROM patients')->fetchColumn() !== $patientCount) throw new RuntimeException('Patient count changed during migration.');
    if ($db->query('SELECT id,email FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) !== $usersBefore) throw new RuntimeException('Account emails changed during migration.');
    foreach (array_keys($replacements) as $name) $db->query('SELECT * FROM `' . $name . '` LIMIT 1')->fetch();
    echo "Removed patients.email; patient records and account emails preserved. Backup: {$backup}\n";
} catch (Throwable $e) {
    // MariaDB DDL commits implicitly; a failed migration is not a rolled-back one.
    fwrite(STDERR, 'Migration stopped: ' . $e->getMessage() . "\nCheck the schema and backup before retrying.\n");
    exit(1);
}
