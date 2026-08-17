<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/clinicModel.php';
require_once '../helpers/csrf.php';

class clinicController {
    private $clinics;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->clinics = new Clinic($conn);
    }

    private function json(array $payload): void {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private function requireAdminPost(): void {
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
            $this->json(['success' => false, 'message' => 'Forbidden.']);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method.']);
        }
        if (!validate_csrf()) {
            $this->json(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
        }
    }

    private function storeClinicImage(string $prefix): ?string {
        if (empty($_FILES['image']['name'])) return null;
        if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($_FILES['image']['size'] ?? 0) > 5 * 1024 * 1024) {
            $this->json(['success' => false, 'message' => 'Upload a clinic image no larger than 5 MB.']);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            $this->json(['success' => false, 'message' => 'Image must be JPG, PNG, or WEBP.']);
        }
        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $directory = dirname(__DIR__, 2) . '/public/assets/clinic-images';
        if (!is_dir($directory) || !move_uploaded_file($_FILES['image']['tmp_name'], $directory . '/' . $filename)) {
            $this->json(['success' => false, 'message' => 'Failed to upload image.']);
        }
        return $filename;
    }

    public function addClinic(): void {
        $this->requireAdminPost();
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '' || $address === '' || $phone === '') {
            $this->json(['success' => false, 'message' => 'Please fill in the clinic name, contact number, and address.']);
        }
        if (mb_strlen($name) > 100 || mb_strlen($address) > 100 || mb_strlen($phone) > 15) {
            $this->json(['success' => false, 'message' => 'One or more clinic fields exceed the allowed length.']);
        }
        if ($this->clinics->clinicNameExists($name)) {
            $this->json(['success' => false, 'message' => 'A clinic with this name already exists.']);
        }

        $image = $this->storeClinicImage('clinic_new');
        $clinicId = $this->clinics->createClinic($name, $address, $phone, $image);
        if (!$clinicId) {
            if ($image) @unlink(dirname(__DIR__, 2) . '/public/assets/clinic-images/' . $image);
            $this->json(['success' => false, 'message' => 'Failed to add clinic.']);
        }
        $this->json(['success' => true, 'message' => 'Clinic added successfully.', 'clinic_id' => $clinicId, 'image' => $image]);
    }

    // Inline update from the admin dashboard (AJAX, returns JSON)
    public function updateClinicInline() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        $id      = $_POST['clinic_id'] ?? '';
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');

        if (!$id || !$name || !$address || !$phone) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
            exit;
        }

        $existingClinic = $this->clinics->getClinicById($id);
        if (!$existingClinic) {
            echo json_encode(['success' => false, 'message' => 'Clinic not found.']);
            exit;
        }

        $image = $existingClinic['clinic_image'];

        // Only touch the image if a new one was actually uploaded
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Image must be JPG, PNG, or WEBP.']);
                exit;
            }

            $newImageName = 'clinic_' . $id . '_' . time() . '.' . $ext;
            $target_dir   = "../../public/assets/clinic-images/";
            $target_file  = $target_dir . $newImageName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = $newImageName;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
                exit;
            }
        }

        $result = $this->clinics->updateClinic($id, $name, $address, $phone, $image);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Clinic updated successfully.',
                'image'   => $image,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update clinic.']);
        }
        exit;
    }

}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'updateInline') {
    $controller = new clinicController();
    $controller->updateClinicInline();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $controller = new clinicController();
    $controller->addClinic();
}
