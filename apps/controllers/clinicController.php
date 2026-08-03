<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/clinicModel.php';

class clinicController {
    private $clinics;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->clinics = new Clinic($conn);
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
}
