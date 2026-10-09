<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../helpers/authorization.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../models/appointmentModel.php';
header('Content-Type: application/json');
vdRequireAdminJson();
$conn = (new Database())->connect();
$model = new Appointment($conn);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        echo json_encode(['success'=>true,'schedules'=>$model->getPostponementSchedules((int) ($_GET['appointment_id'] ?? 0))]);
    } catch (Throwable $e) {
        error_log('Postponement schedules: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Unable to load schedules. Please try again.']);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Refresh the page and try again.']);
    exit;
}
$nextStep = (string) ($_POST['next_step'] ?? 'book_later');
$scheduleId = (int) ($_POST['schedule_id'] ?? 0);
if (!in_array($nextStep,['book_later','reschedule'],true) || ($nextStep === 'reschedule' && $scheduleId <= 0)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Choose a schedule or Book later.']);
    exit;
}
echo json_encode($model->postponeTreatment((int) ($_POST['appointment_id'] ?? 0), (int) $_SESSION['user_id'], (string) ($_POST['reason'] ?? ''), $nextStep === 'reschedule' ? $scheduleId : 0));
