<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/depositModel.php';
require_once '../models/patientModel.php';
require_once '../helpers/csrf.php';
require_once '../support/GcashReceiptOcr.php';

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

    private function receiptImageUpload(): array {
        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'Please upload a valid payment receipt.']);
        }
        if ($_FILES['receipt']['size'] <= 0 || $_FILES['receipt']['size'] > 5 * 1024 * 1024) {
            $this->json(['success' => false, 'message' => 'Receipt must not exceed 5 MB.']);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['receipt']['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($extensions[$mime])) {
            $this->json(['success' => false, 'message' => 'Receipt must be a JPG or PNG image.']);
        }

        return [
            'tmp_name' => $_FILES['receipt']['tmp_name'],
            'mime' => $mime,
            'extension' => $extensions[$mime],
        ];
    }

    public function extract(): void {
        $this->requireCsrf();
        $this->deposits->expireUnpaidAppointments();
        $context = $this->paymentContextFromRequest();
        if (!$context) {
            $this->json(['success' => false, 'message' => 'Payment request not found or access denied.']);
        }

        $upload = $this->receiptImageUpload();
        try {
            $result = (new GcashReceiptOcr())->extract($upload['tmp_name']);
        } catch (Throwable $exception) {
            error_log('GCash receipt OCR error: ' . $exception->getMessage());
            $this->json(['success' => false, 'message' => $exception->getMessage()]);
        }

        if (!$result['recognized_receipt']) {
            $this->json([
                'success' => false,
                'message' => 'This does not appear to be a standard GCash receipt. You can still enter the details manually.',
            ]);
        }

        $labels = [
            'amount' => 'amount',
            'reference_number' => 'reference number',
            'transaction_at' => 'transaction date',
        ];
        $missing = array_map(static fn($key) => $labels[$key] ?? $key, $result['missing']);
        $message = $missing
            ? 'Receipt scanned. Please enter the missing ' . implode(', ', $missing) . '.'
            : 'Receipt scanned. Review the details before submitting.';

        $this->json([
            'success' => true,
            'fields' => $result['fields'],
            'missing' => $result['missing'],
            'message' => $message,
        ]);
    }

    public function submit(): void {
        $this->requireCsrf();
        $this->deposits->expireUnpaidAppointments();
        $context = $this->paymentContextFromRequest();
        if (!$context) {
            $this->json(['success' => false, 'message' => 'Payment request not found or access denied.']);
        }

        $reference = preg_replace('/\D+/', '', trim($_POST['gcash_reference'] ?? ''));
        if (!preg_match('/^\d{10,20}$/', $reference)) {
            $this->json(['success' => false, 'message' => 'Enter the 10–20 digit GCash reference number shown on the receipt.']);
        }

        $receiptAmountInput = str_replace([',', '₱', 'PHP', ' '], '', strtoupper(trim($_POST['receipt_amount'] ?? '')));
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $receiptAmountInput)) {
            $this->json(['success' => false, 'message' => 'Enter the amount shown on the GCash receipt.']);
        }
        $receiptAmount = number_format((float) $receiptAmountInput, 2, '.', '');
        if (abs((float) $receiptAmount - (float) $context['amount']) > 0.009) {
            $this->json([
                'success' => false,
                'message' => 'The receipt amount must match the required deposit of ₱' . number_format((float) $context['amount'], 2) . '.',
            ]);
        }

        $transactionInput = trim($_POST['gcash_transaction_at'] ?? '');
        $transactionAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $transactionInput);
        if (!$transactionAt || $transactionAt->format('Y-m-d\TH:i') !== $transactionInput) {
            $this->json(['success' => false, 'message' => 'Enter the transaction date and time shown on the receipt.']);
        }
        if ($transactionAt > new DateTimeImmutable('+5 minutes')) {
            $this->json(['success' => false, 'message' => 'The GCash transaction date cannot be in the future.']);
        }

        $upload = $this->receiptImageUpload();
        $filename = bin2hex(random_bytes(20)) . '.' . $upload['extension'];
        $relativePath = 'storage/payment_receipts/' . $filename;
        $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
        if (!move_uploaded_file($upload['tmp_name'], $absolutePath)) {
            $this->json(['success' => false, 'message' => 'Unable to store the uploaded receipt.']);
        }

        $result = $this->deposits->submitReceipt(
            (int) $context['appointment_id'],
            $reference,
            $relativePath,
            $upload['mime'],
            $receiptAmount,
            $transactionAt->format('Y-m-d H:i:s')
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'extract') {
    $controller->extract();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
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
