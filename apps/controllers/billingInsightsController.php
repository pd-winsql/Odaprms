<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../models/billingModel.php';
require_once __DIR__ . '/../helpers/authorization.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

vdRequireAdminJson();

try {
    $filters = BillingModel::normalizeInsightFilters($_GET);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $model = new BillingModel((new Database())->connect());
    echo json_encode([
        'success' => true,
        'data' => $model->getBillingInsights($filters, $page, 15),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Billing insights error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Billing insights could not be loaded.']);
}
