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

    public function index() {
        $data = $this->clinics->getAllClinics();
        require_once '../views/clinic-index.php';
    }

    public function addClinic() {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'];
            $address = $_POST['address'];
            $phone = $_POST['phone'];
            $image = $_FILES['image']['name'];

            // Handle file upload
            $target_dir = "../../public/assets/clinic-images/";
            $target_file = $target_dir . basename($image);
            move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);

            $result = $this->clinics->addClinic($name, $address, $phone, $image);

            if ($result) {
                header("Location: ../views/admin/clinics.php?added=1");
            } else {
                header("Location: ../views/admin/clinics.php?error=1");
            }
            exit;
        }
    }

    public function updateClinic() {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $name = $_POST['name'];
            $address = $_POST['address'];
            $phone = $_POST['phone'];
            $image = $_FILES['image']['name'];

            // Handle file upload
            if ($image) {
                $target_dir = "../../public/assets/clinic-images/";
                $target_file = $target_dir . basename($image);
                move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);
            } else {
                // If no new image is uploaded, keep the existing one
                $existingClinic = $this->clinics->getClinicById($id);
                $image = $existingClinic['clinic_image'];
            }

            $result = $this->clinics->updateClinic($id, $name, $address, $phone, $image);

            if ($result) {
                header("Location: ../views/admin/clinics.php?updated=1");
            } else {
                header("Location: ../views/admin/clinics.php?error=1");
            }
            exit;
        }
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

    public function getClinicById($id) {
        return $this->clinics->getClinicById($id);
    }

    public function deleteClinic($id) {
        $result = $this->clinics->deleteClinic($id);
        if ($result) {
            header("Location: ../views/admin/clinics.php?deleted=1");
        } else {
            header("Location: ../views/admin/clinics.php?error=1");
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $controller = new clinicController();

    if ($action === 'add') {
        $controller->addClinic();
    } elseif ($action === 'update') {
        $controller->updateClinic();
    } elseif ($action === 'updateInline') {
        $controller->updateClinicInline();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $controller = new clinicController();

    if ($action === 'delete') {
        $id = $_GET['id'] ?? null;
        if ($id) $controller->deleteClinic($id);
    }
}