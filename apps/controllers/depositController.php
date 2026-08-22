<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/depositModel.php';
require_once '../models/patientModel.php';
require_once '../helpers/csrf.php';

class DepositController {
    private $conn;
    private $deposits;
    private $patients;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
        $this->deposits = new DepositModel($this->conn);
        $this->patients = new Patient($this->conn);
    }

    private function json($payload): void {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private function requireStaff(): int {
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
            $this->json(['success' => false, 'message' => 'Forbidden.']);
        }
        return (int) $_SESSION['user_id'];
    }

    private function requireCsrf(): void {
        if (!validate_csrf()) {
            $this->json(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
        }
    }

    private function paymentContextFromRequest() {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        if ($appointmentId <= 0) return false;

        if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'Patient') {
            $patient = $this->patients->getPatientByUserId($_SESSION['user_id']);
            if (!$patient) return false;
            return $this->deposits->getPaymentContext($appointmentId, (int) $patient['patient_id']);
        }

        return $this->deposits->getPaymentContext($appointmentId, null, trim($_POST['payment_token'] ?? ''));
    }

    public function submit(): void {
        $this->requireCsrf();
        $this->deposits->expireUnpaidAppointments();
        $context = $this->paymentContextFromRequest();
        if (!$context) {
            $this->json(['success' => false, 'message' => 'Payment request not found or access denied.']);
        }

        $reference = strtoupper(preg_replace('/\s+/', '', trim($_POST['gcash_reference'] ?? '')));
        if (!preg_match('/^[A-Z0-9-]{6,100}$/', $reference)) {
            $this->json(['success' => false, 'message' => 'Enter a valid GCash reference number.']);
        }

        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'Please upload a valid payment receipt.']);
        }
        if ($_FILES['receipt']['size'] <= 0 || $_FILES['receipt']['size'] > 5 * 1024 * 1024) {
            $this->json(['success' => false, 'message' => 'Receipt must not exceed 5 MB.']);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['receipt']['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
        ];
        if (!isset($extensions[$mime])) {
            $this->json(['success' => false, 'message' => 'Receipt must be a JPG, PNG, or PDF file.']);
        }

        $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
        $relativePath = 'storage/payment_receipts/' . $filename;
        $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $absolutePath)) {
            $this->json(['success' => false, 'message' => 'Unable to store the uploaded receipt.']);
        }

        $result = $this->deposits->submitReceipt(
            (int) $context['appointment_id'],
            $reference,
            $relativePath,
            $mime
        );

        if (!$result['success']) {
            @unlink($absolutePath);
            $this->json($result);
        }

        $oldPath = $result['old_receipt_path'] ?? '';
        if ($oldPath && $oldPath !== $relativePath) {
            $oldAbsolute = realpath(dirname(__DIR__, 2) . '/' . $oldPath);
            $storageRoot = realpath(dirname(__DIR__, 2) . '/storage/payment_receipts');
            if ($oldAbsolute && $storageRoot && str_starts_with($oldAbsolute, $storageRoot . DIRECTORY_SEPARATOR)) {
                @unlink($oldAbsolute);
            }
        }

        $this->json($result);
    }

    public function verify(): void {
        $this->requireCsrf();
        $userId = $this->requireStaff();
        $depositId = (int) ($_POST['deposit_id'] ?? 0);
        $result = $this->deposits->verify($depositId, $userId);
        $this->json($result);
    }

    public function reject(): void {
        $this->requireCsrf();
        $userId = $this->requireStaff();
        $depositId = (int) ($_POST['deposit_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if (strlen($reason) < 3 || strlen($reason) > 255) {
            $this->json(['success' => false, 'message' => 'Enter a short rejection reason.']);
        }
        $result = $this->deposits->reject($depositId, $userId, $reason);
        $this->json($result);
    }

    public function extend(): void { $this->requireCsrf(); $user=$this->requireStaff(); $this->json($this->deposits->extendDeadline((int)($_POST['appointment_id']??0),$user,trim($_POST['reason']??''))); }
    public function transfer(): void { $this->requireCsrf(); $user=$this->requireStaff(); $this->json($this->deposits->transferDeposit((int)($_POST['source_appointment_id']??0),(int)($_POST['target_appointment_id']??0),$user,trim($_POST['reason']??''))); }
    public function refund(): void { $this->requireCsrf(); $user=$this->requireStaff(); $this->json($this->deposits->markRefunded((int)($_POST['appointment_id']??0),$user,trim($_POST['notes']??''))); }

    public function receipt(): void {
        $this->requireStaff();
        $depositId = (int) ($_GET['deposit_id'] ?? 0);
        $receipt = $this->deposits->getReceiptForStaff($depositId);
        if (!$receipt) {
            http_response_code(404);
            exit('Receipt not found.');
        }

        $storageRoot = realpath(dirname(__DIR__, 2) . '/storage/payment_receipts');
        $absolutePath = realpath(dirname(__DIR__, 2) . '/' . $receipt['receipt_path']);
        if (!$storageRoot || !$absolutePath || !str_starts_with($absolutePath, $storageRoot . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit('Receipt not found.');
        }

        header('Content-Type: ' . $receipt['receipt_mime']);
        header('Content-Disposition: inline; filename="payment-receipt-' . $depositId . '"');
        header('Content-Length: ' . filesize($absolutePath));
        header('X-Content-Type-Options: nosniff');
        readfile($absolutePath);
        exit;
    }
}

$controller = new DepositController();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
    $controller->submit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'verify') {
    $controller->verify();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reject') {
    $controller->reject();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'extend') {
    $controller->extend();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'transfer') {
    $controller->transfer();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'refund') {
    $controller->refund();
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'receipt') {
    $controller->receipt();
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
