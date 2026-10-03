<?php
declare(strict_types=1);

// One-time attribution update for the eight fictional defense appointment scenarios.
// The correction is itself retained in audit_logs so the original attribution remains traceable.
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

// Existing confirmations (#981-983) retain their original dental-assistant actors.
$actors = [
    1995 => ['appointment' => 981, 'user' => 18],
    1996 => ['appointment' => 982, 'user' => 16],
    1997 => ['appointment' => 983, 'user' => 65],
    1998 => ['appointment' => 992, 'user' => 65],
    1999 => ['appointment' => 993, 'user' => 18],
    2000 => ['appointment' => 994, 'user' => 16],
    2001 => ['appointment' => 995, 'user' => 65],
    2002 => ['appointment' => 996, 'user' => 18],
];

try {
    $db->beginTransaction();
    $staffRows = $db->query("SELECT u.id, s.firstname, s.middlename, s.lastname
        FROM users u JOIN staffs s ON s.user_id = u.id
        WHERE u.id IN (16,18,65) AND u.user_role = 'Dental Assistant'
            AND s.employment_status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
    $staff = [];
    foreach ($staffRows as $row) {
        $staff[(int) $row['id']] = trim(implode(' ', array_filter([
            $row['firstname'], $row['middlename'], $row['lastname'],
        ], static fn($part) => $part !== null && $part !== '')));
    }
    if (count($staff) !== 3) {
        throw new RuntimeException('Expected active dental-assistant accounts were not found.');
    }

    $logs = $db->query('SELECT audit_log_id, entity_id, action, description,
            performed_by_user_id, performed_by_name, performed_by_role, source
        FROM audit_logs WHERE audit_log_id BETWEEN 1995 AND 2002
        ORDER BY audit_log_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    if (count($logs) !== count($actors)) {
        throw new RuntimeException('The scenario audit set has changed.');
    }
    foreach ($logs as $log) {
        $id = (int) $log['audit_log_id'];
        if (!isset($actors[$id])
            || (int) $log['entity_id'] !== $actors[$id]['appointment']
            || $log['action'] !== 'status_changed'
            || (int) $log['performed_by_user_id'] !== 7
            || $log['performed_by_name'] !== 'Defense preparation'
            || $log['performed_by_role'] !== 'Admin'
            || $log['source'] !== 'User'
            || !str_contains($log['description'], 'fictional')) {
            throw new RuntimeException("Scenario audit entry $id no longer matches the inspected record.");
        }
    }

    foreach ([992, 994, 995, 996] as $appointmentId) {
        $stmt = $db->prepare('SELECT reviewed_by_user_id FROM appointments WHERE appointment_id = ? FOR UPDATE');
        $stmt->execute([$appointmentId]);
        if ((int) $stmt->fetchColumn() !== 7) {
            throw new RuntimeException("Appointment $appointmentId review actor changed.");
        }
    }
    foreach ([992, 996] as $appointmentId) {
        $stmt = $db->prepare('SELECT verified_by_user_id FROM appointment_deposits WHERE appointment_id = ? FOR UPDATE');
        $stmt->execute([$appointmentId]);
        if ((int) $stmt->fetchColumn() !== 7) {
            throw new RuntimeException("Appointment $appointmentId payment actor changed.");
        }
    }

    if (($argv[1] ?? '') === '--check') {
        $db->rollBack();
        echo "Preflight passed: eight fictional status entries and three active dental assistants.\n";
        exit(0);
    }

    $correction = $db->prepare("INSERT INTO audit_logs
        (entity_type, entity_id, action, description, old_values, new_values,
         performed_by_user_id, performed_by_name, performed_by_role, source)
        VALUES ('appointment', ?, 'audit_actor_updated', ?, ?, ?, NULL, 'System', 'System', 'System')");
    $updateLog = $db->prepare("UPDATE audit_logs
        SET performed_by_user_id = ?, performed_by_name = ?, performed_by_role = 'Dental Assistant'
        WHERE audit_log_id = ? AND performed_by_user_id = 7
            AND performed_by_name = 'Defense preparation' AND performed_by_role = 'Admin'");
    foreach ($logs as $log) {
        $auditId = (int) $log['audit_log_id'];
        $appointmentId = $actors[$auditId]['appointment'];
        $userId = $actors[$auditId]['user'];
        $correction->execute([
            $appointmentId,
            "Updated the recorded actor for appointment status entry $auditId.",
            json_encode(['audit_log_id' => $auditId, 'user_id' => 7,
                'name' => $log['performed_by_name'], 'role' => $log['performed_by_role']], JSON_THROW_ON_ERROR),
            json_encode(['audit_log_id' => $auditId, 'user_id' => $userId,
                'name' => $staff[$userId], 'role' => 'Dental Assistant'], JSON_THROW_ON_ERROR),
        ]);
        $updateLog->execute([$userId, $staff[$userId], $auditId]);
        if ($updateLog->rowCount() !== 1) {
            throw new RuntimeException("Could not update scenario audit entry $auditId.");
        }
    }

    $updateReview = $db->prepare('UPDATE appointments SET reviewed_by_user_id = ?
        WHERE appointment_id = ? AND reviewed_by_user_id = 7');
    foreach ([992 => 65, 994 => 16, 995 => 65, 996 => 18] as $appointmentId => $userId) {
        $updateReview->execute([$userId, $appointmentId]);
        if ($updateReview->rowCount() !== 1) {
            throw new RuntimeException("Could not update appointment $appointmentId review actor.");
        }
    }
    $updatePayment = $db->prepare('UPDATE appointment_deposits SET verified_by_user_id = ?
        WHERE appointment_id = ? AND verified_by_user_id = 7');
    foreach ([992 => 65, 996 => 18] as $appointmentId => $userId) {
        $updatePayment->execute([$userId, $appointmentId]);
        if ($updatePayment->rowCount() !== 1) {
            throw new RuntimeException("Could not update appointment $appointmentId payment actor.");
        }
    }
    $db->prepare('UPDATE appointment_deposits SET deadline_extended_by_user_id = 16
        WHERE appointment_id = 994 AND deadline_extended_by_user_id = 7')->execute();

    $db->commit();
    echo "Updated eight scenario activity entries and their related staff references.\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
