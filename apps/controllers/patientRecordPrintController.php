<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../helpers/authorization.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../models/auditLogModel.php';

header('Content-Type: application/json');
vdRequireRoleJson(['Admin', 'Dental Assistant']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
    exit;
}

$patientId = (int) ($_POST['patient_id'] ?? 0);
if ($patientId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a valid patient record.']);
    exit;
}

try {
    $conn = (new Database())->connect();
    if (!$conn) throw new RuntimeException('Database connection unavailable.');

    $patientStmt = $conn->prepare('SELECT patient_id FROM patients WHERE patient_id = :patient_id');
    $patientStmt->execute([':patient_id' => $patientId]);
    if (!$patientStmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Patient record not found.']);
        exit;
    }

    $audit = new AuditLog($conn);
    $actor = $audit->getUserActor((int) $_SESSION['user_id']);
    if (!$actor) throw new RuntimeException('Unable to identify the current user.');

    $audit->record(
        'patient',
        $patientId,
        'patient_record_printed',
        "Prepared the printable dental record for patient #{$patientId}.",
        null,
        ['format' => 'Letter duplex', 'pages' => 2],
        $actor
    );

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('patient record print audit error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to record this print action.']);
}
