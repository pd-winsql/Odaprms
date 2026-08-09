<?php

class AuditLog {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getUserActor($userId) {
        $stmt = $this->conn->prepare("
            SELECT
                u.id,
                u.email,
                u.user_role,
                s.firstname,
                s.middlename,
                s.lastname
            FROM users u
            LEFT JOIN staffs s ON s.user_id = u.id
            WHERE u.id = :user_id
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $nameParts = array_filter([
            trim((string) ($user['firstname'] ?? '')),
            trim((string) ($user['middlename'] ?? '')),
            trim((string) ($user['lastname'] ?? '')),
        ]);

        return [
            'user_id' => (int) $user['id'],
            'name' => $nameParts ? implode(' ', $nameParts) : $user['email'],
            'role' => $user['user_role'],
        ];
    }

    public function record(
        $entityType,
        $entityId,
        $action,
        $description,
        $oldValues,
        $newValues,
        $actor
    ) {
        $stmt = $this->conn->prepare("
            INSERT INTO audit_logs (
                entity_type,
                entity_id,
                action,
                description,
                old_values,
                new_values,
                performed_by_user_id,
                performed_by_name,
                performed_by_role,
                source
            ) VALUES (
                :entity_type,
                :entity_id,
                :action,
                :description,
                :old_values,
                :new_values,
                :performed_by_user_id,
                :performed_by_name,
                :performed_by_role,
                :source
            )
        ");

        $stmt->execute([
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':action' => $action,
            ':description' => $description,
            ':old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':new_values' => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':performed_by_user_id' => $actor['user_id'] ?? null,
            ':performed_by_name' => $actor['name'],
            ':performed_by_role' => $actor['role'],
            ':source' => $actor['source'] ?? 'User',
        ]);

        $auditLogId = (int) $this->conn->lastInsertId();
        $timestampStmt = $this->conn->prepare("
            SELECT performed_at
            FROM audit_logs
            WHERE audit_log_id = :audit_log_id
        ");
        $timestampStmt->execute([':audit_log_id' => $auditLogId]);

        return [
            'audit_log_id' => $auditLogId,
            'performed_at' => $timestampStmt->fetchColumn(),
        ];
    }

}
