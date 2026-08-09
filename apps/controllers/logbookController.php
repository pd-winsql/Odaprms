<?php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/conn.php';
require_once '../models/logbookModel.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

$providedToken = (string) ($_POST['csrf_token'] ?? '');
$expectedToken = (string) ($_SESSION['csrf_token'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
    exit;
}

$db = new Database();
$model = new LogbookModel($db->connect());
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'checkIn') {
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $lookupMethod = trim($_POST['lookup_method'] ?? 'Code');
    echo json_encode($model->checkIn($appointmentId, (int) $_SESSION['user_id'], $lookupMethod));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'lookup') {
    echo json_encode(['success' => true, 'matches' => $model->lookupToday(trim($_POST['term'] ?? ''))]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
