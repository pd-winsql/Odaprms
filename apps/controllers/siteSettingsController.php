<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/siteSettingsModel.php';
require_once '../models/clinicModel.php';
require_once '../models/scheduleModel.php';
require_once '../helpers/csrf.php';

class SiteSettingsController {
    private $settings;
    private $clinics;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->settings = new SiteSettingsModel($conn);
        $this->clinics = new Clinic($conn);
    }

    private function requireScheduleSettingsAccess(): void {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }
        if (!validate_csrf()) {
            echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
            exit;
        }
    }

    public function updateClinicHours(): void {
        $this->requireScheduleSettingsAccess();
        $clinicId = (int) ($_POST['clinic_id'] ?? 0);
        $startTime = Schedule::normalizeTime((string) ($_POST['default_start_time'] ?? ''));
        $endTime = Schedule::normalizeTime((string) ($_POST['default_end_time'] ?? ''));
        if (!$clinicId || !$this->clinics->getClinicById($clinicId)) {
            echo json_encode(['success' => false, 'message' => 'Select a valid clinic.']);
            exit;
        }
        if (!$startTime || !$endTime || $startTime >= $endTime) {
            echo json_encode(['success' => false, 'message' => 'Default closing time must be later than opening time.']);
            exit;
        }
        if (!Schedule::usesFiveMinuteIncrement($startTime) || !Schedule::usesFiveMinuteIncrement($endTime)) {
            echo json_encode(['success' => false, 'message' => 'Default clinic hours must use five-minute increments.']);
            exit;
        }
        $saved = $this->clinics->updateDefaultHours($clinicId, $startTime, $endTime);
        echo json_encode($saved
            ? ['success' => true, 'message' => 'Default clinic hours saved.']
            : ['success' => false, 'message' => 'Unable to save the default clinic hours.']);
        exit;
    }

    private function requireAdmin() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }
        if (!validate_csrf()) {
            echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
            exit;
        }
    }

    // Updates one section (brand / hero / about / contact) at a time —
    // matches the dashboard's one-card-one-save-button layout.
    public function updateGroup() {
        $this->requireAdmin();

        $group = $_POST['group'] ?? '';

        if (!in_array($group, ['brand', 'hero', 'about', 'contact', 'payment'], true)) {
            echo json_encode(['success' => false, 'message' => 'Unknown section.']);
            exit;
        }

        $fields = SiteSettingsModel::FIELD_GROUPS[$group];
        $data   = [];
        foreach ($fields as $field) {
            $data[$field] = trim($_POST[$field] ?? '');
        }

        if ($group === 'payment') {
            $validation = SiteSettingsModel::validatePaymentSettings($data);
            if (!$validation['success']) {
                echo json_encode($validation);
                exit;
            }
            $data = $validation['data'];
        }

        $result = $this->settings->updateGroup($group, $data, 'Admin');

        echo json_encode($result
            ? ['success' => true, 'message' => 'Changes saved.']
            : ['success' => false, 'message' => 'Failed to save changes.']);
        exit;
    }

    public function updateLogo() {
        $this->requireAdmin();

        if (!isset($_FILES['logo'])) {
            echo json_encode(['success' => false, 'message' => 'No file selected.']);
            exit;
        }

        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Upload failed. Please try again.']);
            exit;
        }

        if (empty($_FILES['logo']['name']) || !is_uploaded_file($_FILES['logo']['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => 'No valid upload found.']);
            exit;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Logo must be JPG, PNG, WEBP, or SVG.']);
            exit;
        }

        $newFilename = 'site_logo_' . time() . '.' . $ext;
        $targetDir   = __DIR__ . '/../../public/assets/';
        $targetFile  = $targetDir . $newFilename;

        if (!is_dir($targetDir)) {
            echo json_encode(['success' => false, 'message' => 'Logo directory does not exist.']);
            exit;
        }

        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload logo.']);
            exit;
        }

        // Ensure settings row exists before updating logo field.
        $this->settings->getSettings();
        $result = $this->settings->updateLogo($newFilename, 'Admin');

        if (!$result) {
            if (file_exists($targetFile)) {
                unlink($targetFile);
            }
            echo json_encode(['success' => false, 'message' => 'Failed to save logo.']);
            exit;
        }

        echo json_encode($result
            ? ['success' => true, 'message' => 'Logo updated.', 'logo' => $newFilename]
            : ['success' => false, 'message' => 'Failed to save logo.']);
        exit;
    }

    public function removeLogo()
    {
        $this->requireAdmin();
        $settings = $this->settings->getSettings();
        $filename = $settings['site_logo'] ?? '';
        if (empty($filename)) {
            echo json_encode(['success' => false, 'message' => 'No logo to remove.']);
            exit;
        }

        $targetDir = __DIR__ . '/../../public/assets/';
        $file = $targetDir . $filename;
        if (is_file($file)) {
            @unlink($file);
        }

        $result = $this->settings->updateLogo('', 'Admin');

        echo json_encode($result
            ? ['success' => true, 'message' => 'Logo removed.']
            : ['success' => false, 'message' => 'Failed to remove logo.']);
        exit;
    }

    public function updateGcashQr() {
        $this->requireAdmin();
        if (!isset($_FILES['gcash_qr']) || $_FILES['gcash_qr']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['gcash_qr']['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => 'Select a valid QR image.']);
            exit;
        }
        if ($_FILES['gcash_qr']['size'] <= 0 || $_FILES['gcash_qr']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'QR image must not exceed 5 MB.']);
            exit;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['gcash_qr']['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($extensions[$mime])) {
            echo json_encode(['success' => false, 'message' => 'QR code must be a JPG or PNG image.']);
            exit;
        }

        $filename = 'gcash_qr_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $targetDir = __DIR__ . '/../../public/assets/';
        $targetFile = $targetDir . $filename;
        if (!move_uploaded_file($_FILES['gcash_qr']['tmp_name'], $targetFile)) {
            echo json_encode(['success' => false, 'message' => 'Unable to upload the QR image.']);
            exit;
        }

        $oldSettings = $this->settings->getSettings();
        if (!$this->settings->updateGcashQr($filename, 'Admin')) {
            @unlink($targetFile);
            echo json_encode(['success' => false, 'message' => 'Unable to save the QR image.']);
            exit;
        }
        $oldFile = basename($oldSettings['gcash_qr_path'] ?? '');
        if ($oldFile && str_starts_with($oldFile, 'gcash_qr_') && is_file($targetDir . $oldFile)) {
            @unlink($targetDir . $oldFile);
        }
        echo json_encode(['success' => true, 'message' => 'GCash QR code updated.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $controller = new SiteSettingsController();

    if ($action === 'updateGroup') {
        $controller->updateGroup();
    } elseif ($action === 'updateLogo') {
        $controller->updateLogo();
    } elseif ($action === 'removeLogo') {
        $controller->removeLogo();
    } elseif ($action === 'updateGcashQr') {
        $controller->updateGcashQr();
    } elseif ($action === 'updateClinicHours') {
        $controller->updateClinicHours();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
}
