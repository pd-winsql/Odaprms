<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../helpers/csrf.php';
require_once '../helpers/paymentSettings.php';
require_once '../../config/mailer.php';

header('Content-Type: application/json');

function notificationJson(array $payload): void {
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'deliverPending') {
    http_response_code(405);
    notificationJson(['success' => false, 'message' => 'Invalid request.']);
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    http_response_code(403);
    notificationJson(['success' => false, 'message' => 'Forbidden.']);
}

if (!validate_csrf()) {
    http_response_code(419);
    notificationJson(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
}

// SMTP may take several seconds. Releasing the session file lock here lets the
// staff member continue using other authenticated dashboard actions meanwhile.
session_write_close();

$db = new Database();
$conn = $db->connect();
if (!$conn) {
    http_response_code(500);
    notificationJson(['success' => false, 'message' => 'Database connection failed.']);
}

$notificationId = max(0, (int) ($_POST['notification_id'] ?? 0));
$lockName = 'av_clinica_browser_email_delivery';
$lockStmt = $conn->prepare('SELECT GET_LOCK(:lock_name, 0)');
$lockStmt->execute([':lock_name' => $lockName]);

// A second browser request can arrive while one email is already being sent.
// Returning immediately avoids duplicate delivery and keeps both requests fast.
if ((int) $lockStmt->fetchColumn() !== 1) {
    notificationJson(['success' => true, 'busy' => true, 'processed' => 0]);
}

$sent = 0;
$retried = 0;
$failed = 0;

try {
    $whereId = $notificationId > 0 ? ' AND notification_id = :notification_id' : '';
    $queue = $conn->prepare("
        SELECT notification_id, appointment_id, recipient_email, payload, attempts
        FROM appointment_email_notifications
        WHERE delivery_status = 'Pending'
          AND attempts < 3
          AND scheduled_at <= NOW()
          {$whereId}
        ORDER BY scheduled_at ASC, notification_id ASC
        LIMIT 5
    ");
    if ($notificationId > 0) {
        $queue->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
    }
    $queue->execute();

    $notifications = $queue->fetchAll(PDO::FETCH_ASSOC);
    foreach ($notifications as $index => $notification) {
        // Mailtrap sandbox accounts may accept only one message per second.
        // This runs after session_write_close(), so the short pacing delay does
        // not block the staff member's other dashboard requests.
        if ($index > 0) usleep(1100000);

        $id = (int) $notification['notification_id'];
        $attempt = (int) $notification['attempts'] + 1;

        // Save the attempt before SMTP. If the browser closes mid-request, the
        // next dashboard visit can safely retry the still-pending notification.
        $recordAttempt = $conn->prepare("
            UPDATE appointment_email_notifications
            SET attempts = :attempts, last_error = NULL
            WHERE notification_id = :notification_id
              AND delivery_status = 'Pending'
        ");
        $recordAttempt->execute([':attempts' => $attempt, ':notification_id' => $id]);

        try {
            $payload = json_decode((string) $notification['payload'], true, 512, JSON_THROW_ON_ERROR);
            $templateVariables = is_array($payload['template_variables'] ?? null) ? $payload['template_variables'] : [];
            if (!$templateVariables) {
                $paymentStmt = $conn->prepare("
                    SELECT COALESCE(d.amount, ss.deposit_amount, 400) AS deposit_amount,
                           COALESCE(ss.payment_deadline_minutes, 480) AS payment_deadline_minutes
                    FROM site_settings ss
                    LEFT JOIN appointment_deposits d ON d.appointment_id = :appointment_id
                    WHERE ss.id = 1
                    LIMIT 1
                ");
                $paymentStmt->execute([':appointment_id' => (int) $notification['appointment_id']]);
                $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $templateVariables = [
                    '{deposit_amount}' => vdFormatPesoAmount((float) ($payment['deposit_amount'] ?? 400)),
                    '{payment_deadline}' => vdFormatDurationMinutes((int) ($payment['payment_deadline_minutes'] ?? 480)),
                ];
            }
            $result = sendTemplateEmail(
                (string) $notification['recipient_email'],
                (string) ($payload['to_name'] ?? 'Patient'),
                (string) ($payload['template_key'] ?? ''),
                (string) ($payload['value'] ?? ''),
                $templateVariables
            );
        } catch (Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        if ($result['success'] ?? false) {
            $markSent = $conn->prepare("
                UPDATE appointment_email_notifications
                SET delivery_status = 'Sent', sent_at = NOW(), last_error = NULL
                WHERE notification_id = :notification_id
            ");
            $markSent->execute([':notification_id' => $id]);
            $sent++;
            continue;
        }

        $error = trim((string) ($result['message'] ?? 'Unknown email delivery error.'));
        $error = function_exists('mb_substr') ? mb_substr($error, 0, 500) : substr($error, 0, 500);
        $isFinalAttempt = $attempt >= 3;
        $retryDelayMinutes = $attempt === 1 ? 1 : 5;

        // The first two failures remain Pending. A later dashboard visit will
        // retry them after the short delay; the third failure is kept for review.
        $markFailure = $conn->prepare("
            UPDATE appointment_email_notifications
            SET delivery_status = :delivery_status,
                last_error = :last_error,
                scheduled_at = :scheduled_at
            WHERE notification_id = :notification_id
        ");
        $markFailure->execute([
            ':delivery_status' => $isFinalAttempt ? 'Failed' : 'Pending',
            ':last_error' => $error,
            ':scheduled_at' => date('Y-m-d H:i:s', time() + ($retryDelayMinutes * 60)),
            ':notification_id' => $id,
        ]);
        $isFinalAttempt ? $failed++ : $retried++;
    }
} finally {
    $releaseStmt = $conn->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $releaseStmt->execute([':lock_name' => $lockName]);
}

notificationJson([
    'success' => true,
    'processed' => $sent + $retried + $failed,
    'sent' => $sent,
    'retried' => $retried,
    'failed' => $failed,
]);
