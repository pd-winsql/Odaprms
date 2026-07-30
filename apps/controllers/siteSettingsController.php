<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/siteSettingsModel.php';

class SiteSettingsController {
    private $settings;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->settings = new SiteSettingsModel($conn);
    }

    private function requireAdmin() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }
    }

    // Updates one section (brand / hero / about / contact) at a time —
    // matches the dashboard's one-card-one-save-button layout.
    public function updateGroup() {
        $this->requireAdmin();

        $group = $_POST['group'] ?? '';

        if (!in_array($group, ['brand', 'hero', 'about', 'contact'])) {
            echo json_encode(['success' => false, 'message' => 'Unknown section.']);
            exit;
        }

        $fields = SiteSettingsModel::FIELD_GROUPS[$group];
        $data   = [];
        foreach ($fields as $field) {
            $data[$field] = trim($_POST[$field] ?? '');
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
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
}