<?php
declare(strict_types=1);

// One-time cleanup of seeded audit display metadata. This keeps IDs, actors,
// status values, and timestamps intact; it does not create clinical actions.
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
    $db->beginTransaction();
    $adminName = $db->query("SELECT CONCAT_WS(' ', s.firstname, NULLIF(s.middlename, ''), s.lastname)
        FROM users u JOIN staffs s ON s.user_id = u.id
        WHERE u.id = 7 AND u.user_role = 'Admin'")->fetchColumn();
    if ($adminName !== 'Aprille Ventura') {
        throw new RuntimeException('The expected administrator account has changed.');
    }

    $rows = $db->query("SELECT audit_log_id, entity_type, entity_id, action, description,
            new_values, performed_by_user_id, performed_by_name
        FROM audit_logs
        WHERE description REGEXP 'fictional|demo|defense|clock-forward|test|repair'
        ORDER BY audit_log_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 59) {
        throw new RuntimeException('Seeded audit row count changed; inspect before normalizing.');
    }

    $statusCount = 0;
    $correctionCount = 0;
    $actorNameCount = 0;
    $updates = [];
    foreach ($rows as $row) {
        $id = (int) $row['audit_log_id'];
        if ($row['entity_type'] !== 'appointment') {
            throw new RuntimeException("Unexpected record type in audit entry $id.");
        }

        $description = $row['description'];
        $action = $row['action'];
        if ($action === 'status_changed') {
            $status = json_decode((string) $row['new_values'], true, 512, JSON_THROW_ON_ERROR)['status'] ?? null;
            if (!is_string($status) || $status === '') {
                throw new RuntimeException("Status is missing from audit entry $id.");
            }
            if ($id >= 1998 && $id <= 2002) {
                $description = "Initial appointment status recorded as $status.";
            } else {
                $description = "Appointment status changed to $status.";
            }
            $statusCount++;
        } elseif ($action === 'demo_attribution_corrected' && $id >= 2005 && $id <= 2012) {
            $target = json_decode((string) $row['new_values'], true, 512, JSON_THROW_ON_ERROR)['audit_log_id'] ?? null;
            if (!is_int($target) || $target < 1995 || $target > 2002) {
                throw new RuntimeException("Correction target is invalid in audit entry $id.");
            }
            $action = 'audit_actor_updated';
            $description = "Updated the recorded actor for appointment status entry $target.";
            $correctionCount++;
        } else {
            throw new RuntimeException("Unexpected seeded audit action in entry $id.");
        }

        $name = $row['performed_by_name'];
        if ($name === 'Defense preparation') {
            if ((int) $row['performed_by_user_id'] !== 7) {
                throw new RuntimeException("Audit actor ID does not match entry $id.");
            }
            $name = $adminName;
            $actorNameCount++;
        }
        $updates[] = [$action, $description, $name, $id, $row['action'], $row['description'], $row['performed_by_name']];
    }
    if ($statusCount !== 51 || $correctionCount !== 8 || $actorNameCount !== 32) {
        throw new RuntimeException('Seeded audit categories changed; inspect before normalizing.');
    }

    if (($argv[1] ?? '') === '--check') {
        $db->rollBack();
        echo "Preflight passed: 51 status descriptions, 8 maintenance actions, 32 account names.\n";
        exit(0);
    }

    $update = $db->prepare('UPDATE audit_logs
        SET action = ?, description = ?, performed_by_name = ?
        WHERE audit_log_id = ? AND action = ? AND description = ? AND performed_by_name = ?');
    foreach ($updates as $values) {
        $update->execute($values);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException("Could not normalize audit entry {$values[3]}.");
        }
    }
    $db->commit();
    echo "Normalized 59 audit entries without changing status data or timestamps.\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
