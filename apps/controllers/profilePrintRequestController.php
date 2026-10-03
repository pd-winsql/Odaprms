<?php
require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../models/profilePrintRequestModel.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['user_role'] ?? '';
    if (!$userId || !in_array($role, ['Patient', 'Dental Assistant', 'Admin'], true)) { http_response_code(403); throw new InvalidArgumentException('Unauthorized.'); }
    $conn = (new Database())->connect();
    if (!$conn) throw new RuntimeException('Database unavailable.');
    $model = new ProfilePrintRequestModel($conn);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validate_csrf()) { http_response_code(419); throw new InvalidArgumentException('Refresh the page and try again.'); }
        if (($_POST['action'] ?? '') === 'create' && $role === 'Patient') {
            $model->create($userId, (int) ($_POST['clinic_id'] ?? 0), (string) ($_POST['purpose'] ?? ''), (string) ($_POST['reason'] ?? ''), ($_POST['include_billing'] ?? '') === '1');
        } elseif (($_POST['action'] ?? '') === 'status') {
            $model->changeStatus((int) ($_POST['request_id'] ?? 0), $userId, (string) ($_POST['status'] ?? ''));
        } else { throw new InvalidArgumentException('Invalid request.'); }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); throw new InvalidArgumentException('Invalid request.'); }
    echo json_encode(['success'=>true, 'requests'=>$model->listRequests($role === 'Patient' ? $userId : null)]);
} catch (InvalidArgumentException $e) {
    if (http_response_code() === 200) http_response_code(422);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Profile print request: '.$e->getMessage());
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'Unable to process the request. Please try again.']);
}
