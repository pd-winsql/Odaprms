<?php
declare(strict_types=1);

// One-time removal of eight assistant-generated attribution maintenance entries
// from the clinic activity feed. The original rows remain recoverable in an
// internal archive table; appointment status history is not touched.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../../config/conn.php';

$db = (new Database())->connect();
if (!$db || $db->query('SELECT DATABASE()')->fetchColumn() !== 'db-oaprms-system') {
    fwrite(STDERR, "Expected application database is unavailable.\n");
    exit(1);
}

try {
    $expectedAppointments = [981, 982, 983, 992, 993, 994, 995, 996];
    $stmt = $db->query('SELECT audit_log_id, entity_type, entity_id, action,
            performed_by_user_id, performed_by_name, performed_by_role, source
        FROM audit_logs WHERE audit_log_id BETWEEN 2005 AND 2012 ORDER BY audit_log_id');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($expectedAppointments)) {
        throw new RuntimeException('The maintenance audit set has changed.');
    }
    foreach ($rows as $index => $row) {
        if ((int) $row['audit_log_id'] !== 2005 + $index
            || $row['entity_type'] !== 'appointment'
            || (int) $row['entity_id'] !== $expectedAppointments[$index]
            || $row['action'] !== 'audit_actor_updated'
            || $row['performed_by_user_id'] !== null
            || $row['performed_by_name'] !== 'System'
            || $row['performed_by_role'] !== 'System'
            || $row['source'] !== 'System') {
            throw new RuntimeException('An audit entry does not match the inspected maintenance record.');
        }
    }

    $archiveExists = (int) $db->query("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'audit_logs_maintenance_archive'")->fetchColumn();
    if ($archiveExists && (int) $db->query('SELECT COUNT(*) FROM audit_logs_maintenance_archive
        WHERE audit_log_id BETWEEN 2005 AND 2012')->fetchColumn() !== 0) {
        throw new RuntimeException('The maintenance entries are already archived.');
    }

    if (($argv[1] ?? '') === '--check') {
        echo "Preflight passed: eight maintenance entries; appointment status entries remain untouched.\n";
        exit(0);
    }

    if (!$archiveExists) {
        $db->exec('CREATE TABLE audit_logs_maintenance_archive LIKE audit_logs');
    }

    $db->beginTransaction();
    $lockedRows = $db->query('SELECT audit_log_id FROM audit_logs
        WHERE audit_log_id BETWEEN 2005 AND 2012 AND action = \'audit_actor_updated\'
        FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
    if (count($lockedRows) !== 8) {
        throw new RuntimeException('The maintenance entries changed before archiving.');
    }

    $copied = $db->exec('INSERT INTO audit_logs_maintenance_archive
        SELECT * FROM audit_logs WHERE audit_log_id BETWEEN 2005 AND 2012
            AND action = \'audit_actor_updated\'');
    if ($copied !== 8) {
        throw new RuntimeException('Not all maintenance entries were archived.');
    }

    $deleted = $db->exec('DELETE FROM audit_logs WHERE audit_log_id BETWEEN 2005 AND 2012
        AND action = \'audit_actor_updated\' AND entity_type = \'appointment\'
        AND performed_by_user_id IS NULL AND performed_by_name = \'System\'
        AND performed_by_role = \'System\' AND source = \'System\'');
    if ($deleted !== 8) {
        throw new RuntimeException('Not all maintenance entries were removed from activity.');
    }
    $db->commit();
    echo "Archived and removed eight maintenance entries from clinic activity.\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
