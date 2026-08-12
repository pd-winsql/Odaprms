<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/conn.php';
require_once '../models/billingModel.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo json_encode(['success' => false, 'message' => 'Forbidden.']); exit;
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']); exit;
}
if (($_POST['action'] ?? '') !== 'settleAndComplete') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit;
}
$model = new BillingModel((new Database())->connect());
echo json_encode($model->settleAndCompleteVisit(
    (int) ($_POST['appointment_id'] ?? 0),
    (float) ($_POST['service_amount'] ?? -1),
    (float) ($_POST['cash_received'] ?? -1),
    (int) $_SESSION['user_id'],
    trim($_POST['notes'] ?? '')
));
