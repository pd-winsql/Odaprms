<?php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/conn.php';
require_once '../models/logbookModel.php';
require_once '../models/appointmentModel.php';
require_once '../helpers/csrf.php';

header('Content-Type: application/json');

// Allows only authorized staff to use logbook actions.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

// Rejects requests with an invalid or expired CSRF token.
if (!validate_csrf()) {
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
    exit;
}

$db = new Database();
$model = new LogbookModel($db->connect());
$action = $_POST['action'] ?? '';

// Handles patient check-in requests.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'checkIn') {
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $lookupMethod = trim($_POST['lookup_method'] ?? 'Code');
    echo json_encode($model->checkIn($appointmentId, (int) $_SESSION['user_id'], $lookupMethod));
    exit;
}

// Looks up a confirmed appointment scheduled for today.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'lookup') {
    echo json_encode(['success' => true, 'matches' => $model->lookupToday(trim($_POST['term'] ?? ''))]);
    exit;
}

// Handles changes to a patient's position in the queue.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['placeOnHold', 'returnToQueue', 'serveNext'], true)) {
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    if ($appointmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid appointment.']);
        exit;
    }

    $userId = (int) $_SESSION['user_id'];
    $reason = trim($_POST['reason'] ?? '');
    if ($action === 'placeOnHold') {
        echo json_encode($model->placeOnHold($appointmentId, $userId, $reason));
    } elseif ($action === 'returnToQueue') {
        echo json_encode($model->returnToQueue($appointmentId, $userId));
    } else {
        echo json_encode($model->serveNext($appointmentId, $userId, $reason));
    }
    exit;
}

// Updates a patient's current visit status.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'updateVisitStatus') {
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($appointmentId <= 0 || !in_array($status, ['In Progress', 'Completed'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid visit status request.']);
        exit;
    }

    $appointmentModel = new Appointment($db->connect());
    echo json_encode($appointmentModel->updateAppointmentStatus(
        $appointmentId,
        $status,
        (int) $_SESSION['user_id']
    ));
    exit;
}

// Returns an error when no supported action matches the request.
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
