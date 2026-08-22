<?php

class EmailNotificationModel {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /**
     * Queue one template email for an appointment.
     *
     * This method uses the caller's PDO connection so the queue insert can be
     * committed or rolled back with the appointment/payment status change.
     */
    public function enqueueAppointmentTemplate(
        int $appointmentId,
        string $templateKey,
        string $value,
        string $deduplicationKey
    ): ?array {
        $recipientStmt = $this->conn->prepare("
            SELECT p.user_id, p.email, p.firstname, p.lastname
            FROM appointments a
            JOIN patients p ON p.patient_id = a.patient_id
            WHERE a.appointment_id = :appointment_id
            LIMIT 1
        ");
        $recipientStmt->execute([':appointment_id' => $appointmentId]);
        $recipient = $recipientStmt->fetch(PDO::FETCH_ASSOC);

        // A missing address should not prevent the clinic from updating the
        // appointment. It simply means there is no notification to queue.
        $email = trim((string) ($recipient['email'] ?? ''));
        if (!$recipient || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $templates = require __DIR__ . '/../../config/emailTemplates.php';
        $template = $templates[$templateKey] ?? null;
        if (!$template) {
            throw new InvalidArgumentException("Unknown email template: {$templateKey}");
        }

        $name = trim(($recipient['firstname'] ?? '') . ' ' . ($recipient['lastname'] ?? ''));
        $payload = json_encode([
            'to_name' => $name !== '' ? $name : 'Patient',
            'template_key' => $templateKey,
            'value' => $value,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // The audit-based key makes retries safe: the same business event can
        // never create a second email, while a later legitimate event can.
        $insert = $this->conn->prepare("
            INSERT INTO appointment_email_notifications (
                appointment_id,
                recipient_user_id,
                notification_type,
                recipient_email,
                subject,
                payload,
                deduplication_key,
                delivery_status,
                scheduled_at
            ) VALUES (
                :appointment_id,
                :recipient_user_id,
                :notification_type,
                :recipient_email,
                :subject,
                :payload,
                :deduplication_key,
                'Pending',
                NOW()
            )
            ON DUPLICATE KEY UPDATE notification_id = LAST_INSERT_ID(notification_id)
        ");
        $insert->execute([
            ':appointment_id' => $appointmentId,
            ':recipient_user_id' => $recipient['user_id'] ?: null,
            ':notification_type' => $templateKey,
            ':recipient_email' => $email,
            ':subject' => $template['subject'],
            ':payload' => $payload,
            ':deduplication_key' => $deduplicationKey,
        ]);

        return [
            'id' => (int) $this->conn->lastInsertId(),
            'status' => 'Pending',
        ];
    }
}
