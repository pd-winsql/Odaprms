<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/conn.php';
require_once '../models/billingModel.php';
require_once '../helpers/csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo json_encode(['success' => false, 'message' => 'Forbidden.']); exit;
}
if (!validate_csrf()) {
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']); exit;
}
if (($_POST['action'] ?? '') !== 'settleAndComplete') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit;
}
$serviceIds = array_values(array_unique(array_filter(array_map(
    'intval',
    (array) ($_POST['service_ids'] ?? [])
))));
if (!$serviceIds) {
    echo json_encode(['success' => false, 'message' => 'Select at least one service performed.']); exit;
}
$model = new BillingModel((new Database())->connect());
echo json_encode($model->settleAndCompleteVisit(
    (int) ($_POST['appointment_id'] ?? 0),
    (float) ($_POST['service_amount'] ?? -1),
    (float) ($_POST['cash_received'] ?? -1),
    (int) $_SESSION['user_id'],
    trim($_POST['notes'] ?? ''),
    $serviceIds,
    trim($_POST['service_change_reason'] ?? '')
));
