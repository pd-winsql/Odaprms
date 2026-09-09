<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/clinicModel.php';
require_once '../models/auditLogModel.php';
require_once '../helpers/csrf.php';
require_once '../helpers/authorization.php';

class clinicController {
    private $clinics;
    private $auditLog;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->clinics = new Clinic($conn);
        $this->auditLog = new AuditLog($conn);
    }

    private function json(array $payload): void {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private function requireStaffPost(): void {
        vdRequireDentalAssistantJson();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method.']);
        }
        if (!validate_csrf()) {
            $this->json(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
        }
    }

    private function normalizeEmbedUrl(string $rawEmbed): ?string {
        $rawEmbed = trim($rawEmbed);
        if ($rawEmbed === '') {
            return null;
        }

        if (stripos($rawEmbed, '<iframe') !== false) {
            if (!preg_match('/src=["\']([^"\']+)["\']/i', $rawEmbed, $matches)) {
                $this->json(['success' => false, 'message' => 'Invalid map embed iframe.']);
            }
            $rawEmbed = trim($matches[1]);
        }

        if (!filter_var($rawEmbed, FILTER_VALIDATE_URL)) {
            $this->json(['success' => false, 'message' => 'Map embed URL must be a valid URL.']);
        }

        $parsedUrl = parse_url($rawEmbed);
        $host = strtolower($parsedUrl['host'] ?? '');
        $path = strtolower($parsedUrl['path'] ?? '');
        $isGoogleMapsHost = in_array($host, ['www.google.com', 'google.com', 'maps.google.com'], true);

        if (!$isGoogleMapsHost || !str_starts_with($path, '/maps/embed')) {
            $this->json(['success' => false, 'message' => 'Use a valid Google Maps embed URL.']);
        }

        return $rawEmbed;
    }

    public function addClinic(): void {
        $this->requireStaffPost();
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $embedUrl = $this->normalizeEmbedUrl($_POST['embed_url'] ?? '');

        if ($name === '' || $address === '') {
            $this->json(['success' => false, 'message' => 'Please fill in the clinic name and address.']);
        }
        if (mb_strlen($name) > 100 || mb_strlen($address) > 100) {
            $this->json(['success' => false, 'message' => 'One or more clinic fields exceed the allowed length.']);
        }
        if ($this->clinics->clinicNameExists($name)) {
            $this->json(['success' => false, 'message' => 'A clinic with this name already exists.']);
        }

        $clinicId = $this->clinics->createClinic($name, $address, $embedUrl);
        if (!$clinicId) {
            $this->json(['success' => false, 'message' => 'Failed to add clinic.']);
        }
        $this->auditLog->recordForUser('clinic', $clinicId, 'clinic_created', "Created clinic {$name}.", null, [
            'name' => $name, 'address' => $address, 'embed_url' => $embedUrl,
        ], (int) $_SESSION['user_id']);
        $this->json(['success' => true, 'message' => 'Clinic added successfully.', 'clinic_id' => $clinicId]);
    }

    // Modal-based dashboard update (AJAX, returns JSON)
    public function updateClinicInline() {
        $this->requireStaffPost();

        $id      = $_POST['clinic_id'] ?? '';
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $embedUrl = $this->normalizeEmbedUrl($_POST['embed_url'] ?? '');

        if (!$id || !$name || !$address) {
            $this->json(['success' => false, 'message' => 'Please fill in all fields.']);
        }

        $existingClinic = $this->clinics->getClinicById($id);
        if (!$existingClinic) {
            $this->json(['success' => false, 'message' => 'Clinic not found.']);
        }

        $result = $this->clinics->updateClinic($id, $name, $address, $embedUrl);

        if ($result) {
            $this->auditLog->recordForUser('clinic', (int) $id, 'clinic_updated', "Updated clinic {$name}.", [
                'name' => $existingClinic['clinic_name'],
                'address' => $existingClinic['clinic_address'],
                'embed_url' => $existingClinic['embed_url'] ?? null,
            ], ['name' => $name, 'address' => $address, 'embed_url' => $embedUrl], (int) $_SESSION['user_id']);
            $this->json([
                'success' => true,
                'message' => 'Clinic updated successfully.',
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update clinic.']);
        }
    }

    public function deleteClinic(): void {
        $this->requireStaffPost();

        $id = filter_var($_POST['clinic_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            $this->json(['success' => false, 'message' => 'Invalid clinic.']);
        }

        $clinic = $this->clinics->getClinicById($id);
        if (!$clinic) {
            $this->json(['success' => false, 'message' => 'Clinic not found.']);
        }

        if ($this->clinics->countClinics() <= 1) {
            $this->json(['success' => false, 'message' => 'At least one clinic must remain available.']);
        }

        $usage = $this->clinics->getClinicUsageCounts($id);
        if (!empty($usage['error'])) {
            $this->json(['success' => false, 'message' => 'Unable to verify whether this clinic is in use.']);
        }
        if ($usage['schedules'] > 0 || $usage['appointments'] > 0) {
            $this->json([
                'success' => false,
                'message' => 'This clinic has schedules or appointments and cannot be deleted.',
            ]);
        }

        if (!$this->clinics->deleteClinic($id)) {
            $this->json(['success' => false, 'message' => 'Failed to delete clinic.']);
        }

        $name = $clinic['clinic_name'] ?? 'Clinic';
        $this->auditLog->recordForUser('clinic', (int) $id, 'clinic_deleted', "Deleted clinic {$name}.", [
            'name' => $name,
            'address' => $clinic['clinic_address'] ?? '',
            'embed_url' => $clinic['embed_url'] ?? null,
        ], null, (int) $_SESSION['user_id']);

        $this->json(['success' => true, 'message' => 'Clinic deleted successfully.']);
    }

}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'updateInline') {
    $controller = new clinicController();
    $controller->updateClinicInline();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $controller = new clinicController();
    $controller->addClinic();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $controller = new clinicController();
    $controller->deleteClinic();
}
