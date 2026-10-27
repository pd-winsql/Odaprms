<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/conn.php';
require_once '../models/billingModel.php';
require_once '../helpers/csrf.php';
require_once '../helpers/authorization.php';
header('Content-Type: application/json');

if (!vdCanPerformBilling()) {
    http_response_code(403);
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
$postedQuantities = (array) ($_POST['service_quantities'] ?? []);
$postedUnitPrices = (array) ($_POST['service_unit_prices'] ?? []);
$serviceLineItems = [];
foreach ($serviceIds as $serviceId) {
    if (!array_key_exists($serviceId, $postedQuantities) || !array_key_exists($serviceId, $postedUnitPrices)) {
        echo json_encode(['success' => false, 'message' => 'Enter a rate and quantity for every selected service.']); exit;
    }
    $serviceLineItems[$serviceId] = [
        'quantity' => $postedQuantities[$serviceId],
        'unit_price' => $postedUnitPrices[$serviceId],
    ];
}
$model = new BillingModel((new Database())->connect());
$result = $model->settleAndCompleteVisit(
    (int) ($_POST['appointment_id'] ?? 0),
    (float) ($_POST['service_amount'] ?? -1),
    (float) ($_POST['cash_received'] ?? -1),
    (int) $_SESSION['user_id'],
    trim($_POST['notes'] ?? ''),
    $serviceIds,
    trim($_POST['service_change_reason'] ?? ''),
    $serviceLineItems
);
if (($result['success'] ?? false) && !empty($result['receipt_notification_id'])) {
    $_SESSION['final_billing_receipt_notice'] = 'Payment settled. The patient’s receipt email is queued for delivery.';
}
echo json_encode($result);
