<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/siteSettingsModel.php';
require_once '../models/clinicModel.php';
require_once '../models/scheduleModel.php';
require_once '../models/auditLogModel.php';
require_once '../support/SiteLogoUpload.php';
require_once '../helpers/csrf.php';
require_once '../helpers/authorization.php';

class SiteSettingsController {
    private $settings;
    private $clinics;
    private $schedules;
    private $auditLog;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->settings = new SiteSettingsModel($conn);
        $this->clinics = new Clinic($conn);
        $this->schedules = new Schedule($conn);
        $this->auditLog = new AuditLog($conn);
    }

    private function requireScheduleSettingsAccess(): void {
        header('Content-Type: application/json');
        vdRequireDentalAssistantJson();
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
        if (!Schedule::isWithinOperatingHours($startTime, $endTime)) {
            echo json_encode(['success' => false, 'message' => 'Default clinic hours must stay between 8:00 AM and 5:30 PM.']);
            exit;
        }
        $oldClinic = $this->clinics->getClinicById($clinicId);
        $saved = $this->clinics->updateDefaultHours($clinicId, $startTime, $endTime);
        if ($saved) $this->auditLog->recordForUser('clinic', $clinicId, 'schedule_defaults_updated', 'Updated default clinic schedule hours.', [
            'default_start_time' => $oldClinic['default_start_time'] ?? null,
            'default_end_time' => $oldClinic['default_end_time'] ?? null,
        ], ['default_start_time' => $startTime, 'default_end_time' => $endTime], (int) $_SESSION['user_id']);
        echo json_encode($saved
            ? ['success' => true, 'message' => 'Default clinic hours saved.']
            : ['success' => false, 'message' => 'Unable to save the default clinic hours.']);
        exit;
    }

    public function updateClinicTransitionMinutes(): void
    {
        $this->requireScheduleSettingsAccess();
        $rawMinutes = trim((string) ($_POST['clinic_transition_minutes'] ?? ''));
        if (!ctype_digit($rawMinutes)) {
            echo json_encode(['success' => false, 'message' => 'Clinic separation must be a whole number of minutes.']);
            exit;
        }

        $minutes = (int) $rawMinutes;
        if ($minutes < Schedule::MIN_TRANSITION_MINUTES || $minutes > Schedule::MAX_TRANSITION_MINUTES || $minutes % 5 !== 0) {
            echo json_encode(['success' => false, 'message' => 'Clinic separation must be between 0 and 240 minutes in five-minute increments.']);
            exit;
        }

        $oldSettings = $this->settings->getSettings();
        $oldMinutes = (int) ($oldSettings['clinic_transition_minutes'] ?? $this->schedules->getTransitionMinutes());
        $conflict = $minutes === $oldMinutes ? null : $this->schedules->findTransitionPolicyConflict($minutes);
        if ($conflict !== null) {
            if (!empty($conflict['error'])) {
                echo json_encode(['success' => false, 'message' => 'Unable to validate the current clinic schedule. Try again.']);
                exit;
            }
            $first = $conflict['first'];
            $second = $conflict['second'];
            $dateLabel = date('M j, Y', strtotime($first['sched_date']));
            echo json_encode([
                'success' => false,
                'message' => "The {$minutes}-minute separation conflicts with {$first['clinic_name']} and {$second['clinic_name']} on {$dateLabel}. Adjust those schedule windows first.",
            ]);
            exit;
        }

        $saved = $this->settings->updateClinicTransitionMinutes($minutes);
        if ($saved) {
            $this->auditLog->recordForUser(
                'site_settings',
                1,
                'clinic_transition_updated',
                'Updated the required separation between clinic schedule windows.',
                ['clinic_transition_minutes' => $oldMinutes],
                ['clinic_transition_minutes' => $minutes],
                (int) $_SESSION['user_id']
            );
        }
        echo json_encode($saved
            ? ['success' => true, 'message' => 'Clinic separation saved. New and edited schedules now use this interval.']
            : ['success' => false, 'message' => 'Unable to save the clinic separation.']);
        exit;
    }

    public function updateDefaultScheduleCapacity(): void
    {
        $this->requireScheduleSettingsAccess();
        $rawCapacity = trim((string) ($_POST['default_schedule_capacity'] ?? ''));
        if (!ctype_digit($rawCapacity)) {
            echo json_encode(['success' => false, 'message' => 'Default patient slots must be a whole number.']);
            exit;
        }

        $capacity = (int) $rawCapacity;
        if ($capacity < Schedule::MIN_CAPACITY || $capacity > Schedule::MAX_CAPACITY) {
            echo json_encode(['success' => false, 'message' => 'Default patient slots must be between 1 and 50.']);
            exit;
        }

        $oldSettings = $this->settings->getSettings();
        $oldCapacity = (int) ($oldSettings['default_schedule_capacity'] ?? Schedule::DEFAULT_CAPACITY);
        $saved = $this->settings->updateDefaultScheduleCapacity($capacity);
        if ($saved) {
            $this->auditLog->recordForUser(
                'site_settings',
                1,
                'schedule_capacity_default_updated',
                'Updated the default patient slots for new schedules.',
                ['default_schedule_capacity' => $oldCapacity],
                ['default_schedule_capacity' => $capacity],
                (int) $_SESSION['user_id']
            );
        }
        echo json_encode($saved
            ? ['success' => true, 'message' => 'Default patient slots saved. New schedules will use this capacity.']
            : ['success' => false, 'message' => 'Unable to save the default patient slots.']);
        exit;
    }

    private function requireAdmin() {
        header('Content-Type: application/json');
        vdRequireAdminJson();
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

        if (!in_array($group, ['brand', 'hero', 'about', 'contact', 'payment', 'eligibility', 'booking'], true)) {
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
        } elseif ($group === 'eligibility') {
            $validation = SiteSettingsModel::validateEligibilitySettings($data);
            if (!$validation['success']) {
                echo json_encode($validation);
                exit;
            }
            $data = $validation['data'];
        } elseif ($group === 'booking') {
            $validation = SiteSettingsModel::validateBookingSettings($data);
            if (!$validation['success']) {
                echo json_encode($validation);
                exit;
            }
            $data = $validation['data'];
        }

        $oldSettings = $this->settings->getSettings();
        $oldData = array_intersect_key($oldSettings, array_flip(SiteSettingsModel::FIELD_GROUPS[$group]));
        $result = $this->settings->updateGroup($group, $data, 'Admin');
        if ($result) $this->auditLog->recordForUser('site_settings', 1, 'settings_updated', "Updated {$group} settings.", $oldData, $data, (int) $_SESSION['user_id']);

        echo json_encode($result
            ? ['success' => true, 'message' => 'Changes saved.']
            : ['success' => false, 'message' => 'Failed to save changes.']);
        exit;
    }

    public function updateLogo() {
        $this->requireAdmin();
        $oldLogo = $this->settings->getSettings()['site_logo'] ?? '';

        if (!isset($_FILES['logo'])) {
            echo json_encode(['success' => false, 'message' => 'No file selected.']);
            exit;
        }

        $result = SiteLogoUpload::replace(
            $_FILES['logo'],
            __DIR__ . '/../../public/assets/',
            (string) $oldLogo,
            function (string $filename): bool {
                return $this->settings->updateLogo($filename, 'Admin');
            },
            'is_uploaded_file',
            'move_uploaded_file'
        );

        if (!$result['success']) {
            echo json_encode($result);
            exit;
        }

        $newFilename = $result['filename'];
        // Audit only server-generated names. The untrusted client filename is
        // deliberately neither persisted nor logged.
        $safeOldLogo = preg_match('/^site_logo_(?:[a-f0-9]{32}\.(?:jpg|png|webp)|[0-9]{10}\.(?:jpe?g|png|webp|svg))$/D', (string) $oldLogo)
            ? (string) $oldLogo
            : '';
        $this->auditLog->recordForUser('site_settings', 1, 'site_logo_updated', 'Updated the system logo.', ['site_logo' => $safeOldLogo], ['site_logo' => $newFilename], (int) $_SESSION['user_id']);

        echo json_encode(['success' => true, 'message' => 'Logo updated.', 'logo' => $newFilename]);
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
        if ($result) $this->auditLog->recordForUser('site_settings', 1, 'site_logo_removed', 'Removed the system logo.', ['site_logo' => $filename], ['site_logo' => ''], (int) $_SESSION['user_id']);

        echo json_encode($result
            ? ['success' => true, 'message' => 'Logo removed.']
            : ['success' => false, 'message' => 'Failed to remove logo.']);
        exit;
    }

    private function removeManagedHeroImage(string $filename): void
    {
        if (!preg_match('/^hero_image_[a-f0-9]{32}\.(?:jpg|png|webp)$/D', $filename)) {
            return;
        }

        $targetDirectory = realpath(__DIR__ . '/../../public/assets/');
        if ($targetDirectory === false) {
            return;
        }

        $candidate = $targetDirectory . DIRECTORY_SEPARATOR . $filename;
        $resolved = realpath($candidate);
        if ($resolved !== false
            && dirname(str_replace('\\', '/', $resolved)) === rtrim(str_replace('\\', '/', $targetDirectory), '/')
            && is_file($resolved)
            && !is_link($candidate)) {
            @unlink($resolved);
        }
    }

    public function updateHeroImage(): void
    {
        $this->requireAdmin();
        if (!isset($_FILES['hero_image'])) {
            echo json_encode(['success' => false, 'message' => 'Choose a hero image first.']);
            exit;
        }

        $validated = SiteLogoUpload::validate($_FILES['hero_image'], 'is_uploaded_file');
        if (!$validated['success']) {
            echo json_encode(['success' => false, 'message' => str_replace('Logo', 'Hero', $validated['message'])]);
            exit;
        }

        $targetDirectory = realpath(__DIR__ . '/../../public/assets/');
        if ($targetDirectory === false || !is_writable($targetDirectory)) {
            echo json_encode(['success' => false, 'message' => 'Hero image storage is unavailable.']);
            exit;
        }

        $filename = 'hero_image_' . bin2hex(random_bytes(16)) . '.' . $validated['extension'];
        $target = $targetDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($validated['path'], $target)) {
            echo json_encode(['success' => false, 'message' => 'Unable to upload the hero image.']);
            exit;
        }

        $oldSettings = $this->settings->getSettings();
        $oldFilename = basename((string) ($oldSettings['hero_image'] ?? ''));
        if (!$this->settings->updateHeroImage($filename, 'Admin')) {
            @unlink($target);
            echo json_encode(['success' => false, 'message' => 'Unable to save the hero image.']);
            exit;
        }

        $this->removeManagedHeroImage($oldFilename);
        $this->auditLog->recordForUser(
            'site_settings',
            1,
            'hero_image_updated',
            'Updated the landing page hero image.',
            ['hero_image' => $oldFilename],
            ['hero_image' => $filename],
            (int) $_SESSION['user_id']
        );
        echo json_encode(['success' => true, 'message' => 'Hero image updated.', 'hero_image' => $filename]);
        exit;
    }

    public function resetHeroImage(): void
    {
        $this->requireAdmin();
        $oldSettings = $this->settings->getSettings();
        $oldFilename = basename((string) ($oldSettings['hero_image'] ?? ''));
        $defaultFilename = 'landing_hero_default.jpg';
        if (!$this->settings->updateHeroImage($defaultFilename, 'Admin')) {
            echo json_encode(['success' => false, 'message' => 'Unable to restore the default hero image.']);
            exit;
        }

        $this->removeManagedHeroImage($oldFilename);
        $this->auditLog->recordForUser(
            'site_settings',
            1,
            'hero_image_reset',
            'Restored the default landing page hero image.',
            ['hero_image' => $oldFilename],
            ['hero_image' => $defaultFilename],
            (int) $_SESSION['user_id']
        );
        echo json_encode(['success' => true, 'message' => 'Default hero image restored.']);
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
        $this->auditLog->recordForUser('site_settings', 1, 'gcash_qr_updated', 'Updated the GCash QR code.', ['gcash_qr_path' => $oldSettings['gcash_qr_path'] ?? ''], ['gcash_qr_path' => $filename], (int) $_SESSION['user_id']);
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
    } elseif ($action === 'updateHeroImage') {
        $controller->updateHeroImage();
    } elseif ($action === 'resetHeroImage') {
        $controller->resetHeroImage();
    } elseif ($action === 'updateGcashQr') {
        $controller->updateGcashQr();
    } elseif ($action === 'updateClinicHours') {
        $controller->updateClinicHours();
    } elseif ($action === 'updateClinicTransitionMinutes') {
        $controller->updateClinicTransitionMinutes();
    } elseif ($action === 'updateDefaultScheduleCapacity') {
        $controller->updateDefaultScheduleCapacity();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
}
