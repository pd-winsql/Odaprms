<?php
declare(strict_types=1);

// Repair timestamps written while the workstation clock was set to October 7.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../../config/conn.php';
$db = (new Database())->connect();
if (!$db) {
    fwrite(STDERR, "Application database unavailable.\n");
    exit(1);
}

try {
    if (date('Y-m-d') !== '2026-09-30'
        || $db->query('SELECT DATABASE()')->fetchColumn() !== 'db-oaprms-system') {
        throw new RuntimeException('Unexpected date or database.');
    }
    $profiles = $db->query('SELECT patient_id, created_at, profile_completed_at
        FROM patients WHERE patient_id IN (513,519,520) ORDER BY patient_id')->fetchAll(PDO::FETCH_ASSOC);
    if (array_map(static fn(array $r): int => (int) $r['patient_id'], $profiles) !== [513,519,520]
        || count(array_filter($profiles, static fn(array $r): bool => $r['profile_completed_at'] <= '2026-09-30 23:59:59')) !== 0
        || (int) $db->query('SELECT COUNT(*) FROM audit_logs
            WHERE audit_log_id IN (1951,1952,1953,1958,1978,1979,1980) AND performed_at > NOW()')->fetchColumn() !== 7
        || (int) $db->query('SELECT COUNT(*) FROM clinic_messages
            WHERE message_id = 8 AND conversation_id = 4 AND created_at > NOW()')->fetchColumn() !== 1
        || (int) $db->query('SELECT COUNT(*) FROM clinic_messages
            WHERE message_id IN (5,6,7,8) AND read_at > NOW()')->fetchColumn() !== 4) {
        throw new RuntimeException('Clock-test rows changed since inspection.');
    }
    if (($argv[1] ?? '') === '--check') {
        echo json_encode(['preflight' => 'passed', 'patient_profiles' => 3,
            'future_audit_events' => 7, 'future_chat_created' => 1,
            'future_chat_reads' => 4], JSON_PRETTY_PRINT), PHP_EOL;
        exit(0);
    }

    $db->beginTransaction();
    // These fictional profiles were already complete before the clock test.
    $db->exec('UPDATE patients p JOIN users u ON u.id = p.user_id
        SET p.profile_completed_at = DATE_ADD(p.created_at, INTERVAL 2 HOUR),
            p.profile_completed_by_user_id = u.id
        WHERE p.patient_id IN (513,519,520)');
    // The automated no-show events were undone; the profile events duplicated
    // the original completed profiles. Keep the service edit, at the real time.
    $db->exec('DELETE FROM audit_logs
        WHERE audit_log_id IN (1951,1952,1953,1978,1979,1980)');
    $db->exec('UPDATE audit_logs SET performed_at = NOW() WHERE audit_log_id = 1958');
    // Preserve chat contents and read state, with current timestamps.
    $db->exec('UPDATE clinic_messages SET created_at = NOW()
        WHERE message_id = 8 AND conversation_id = 4');
    $db->exec('UPDATE clinic_messages SET read_at = NOW()
        WHERE message_id IN (5,6,7,8)');
    $db->exec('UPDATE clinic_conversations
        SET updated_at = (SELECT MAX(created_at) FROM clinic_messages WHERE conversation_id = 4)
        WHERE conversation_id = 4');
    $db->commit();
    echo json_encode(['normalized' => true, 'patient_profiles' => [513,519,520],
        'chat_messages_kept' => 4], JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
