<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/odontogramModel.php';
require_once '../helpers/csrf.php';
require_once '../helpers/authorization.php';

header('Content-Type: application/json');
vdRequireRoleJson(['Admin', 'Dental Assistant']);

$conn = (new Database())->connect();
if (!$conn) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

$model = new OdontogramModel($conn);
$action = $_REQUEST['action'] ?? 'get';
$patientId = (int) ($_REQUEST['patient_id'] ?? 0);
$appointmentId = (int) ($_REQUEST['appointment_id'] ?? 0);

try {
    if ($action === 'get') {
        if ($patientId < 1) throw new InvalidArgumentException('Choose a patient.');
        echo json_encode(['success' => true, 'data' => $model->getChart($patientId, $appointmentId)]);
        exit;
    }

    if ($action !== 'save' || ($_SESSION['user_role'] ?? '') !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the Admin / Dentist can update dental charts.']);
        exit;
    }
    if (!validate_csrf()) {
        echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
        exit;
    }
    $payload = json_decode((string) ($_POST['chart'] ?? ''), true);
    if (!is_array($payload)) throw new InvalidArgumentException('Invalid dental chart data.');
    echo json_encode($model->saveChart($patientId, $appointmentId, $payload, (int) $_SESSION['user_id']));
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('odontogram controller error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load the dental chart.']);
}
