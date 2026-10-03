<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../helpers/authorization.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../models/appointmentModel.php';
header('Content-Type: application/json');
vdRequireAdminJson();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Refresh the page and try again.']);
    exit;
}
$conn = (new Database())->connect();
echo json_encode((new Appointment($conn))->postponeTreatment((int) ($_POST['appointment_id'] ?? 0), (int) $_SESSION['user_id'], (string) ($_POST['reason'] ?? '')));
