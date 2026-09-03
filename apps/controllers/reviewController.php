<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/reviewModel.php';
require_once '../helpers/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Patient') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only signed-in patients can submit visit feedback.']);
    exit;
}

if (!validate_csrf()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
    exit;
}

if (($_POST['action'] ?? '') !== 'submit') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

$db = new Database();
$conn = $db->connect();
if (!$conn) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'The feedback service is temporarily unavailable.']);
    exit;
}

$model = new ReviewModel($conn);
$result = $model->submitForPatientUser(
    (int) ($_POST['appointment_id'] ?? 0),
    (int) $_SESSION['user_id'],
    (int) ($_POST['rating'] ?? 0),
    (string) ($_POST['feedback'] ?? '')
);

if (!$result['success']) http_response_code(422);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
