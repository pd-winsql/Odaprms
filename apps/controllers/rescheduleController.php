<?php

require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../models/rescheduleModel.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/authorization.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
if (!validate_csrf()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
    exit;
}

$db = new Database();
$model = new RescheduleModel($db->connect());
$action = trim((string) ($_POST['action'] ?? ''));
$userId = (int) $_SESSION['user_id'];

if (in_array($action, ['submit', 'withdraw'], true)) {
    if (($_SESSION['user_role'] ?? '') !== 'Patient') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only patients can manage their reschedule requests.']);
        exit;
    }
    $result = $action === 'submit'
        ? $model->submitRequest(
            (int) ($_POST['appointment_id'] ?? 0),
            (int) ($_POST['target_schedule_id'] ?? 0),
            (string) ($_POST['reason'] ?? ''),
            $userId
        )
        : $model->withdrawRequest((int) ($_POST['request_id'] ?? 0), $userId);
    echo json_encode($result);
    exit;
}

if (in_array($action, ['approve', 'reject'], true)) {
    vdRequireDentalAssistantJson();
    $result = $action === 'approve'
        ? $model->approveRequest((int) ($_POST['request_id'] ?? 0), $userId)
        : $model->rejectRequest((int) ($_POST['request_id'] ?? 0), (string) ($_POST['reason'] ?? ''), $userId);
    echo json_encode($result);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown reschedule action.']);
