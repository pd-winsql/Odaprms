<?php
require_once '../../../config/conn.php';
require_once '../models/clinicModel.php';

class clinicController {
    private $clinics;

    public function __construct($conn) {
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
            $target_dir = "../../../public/assets/clinic-images/";
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
                $target_dir = "../../../public/assets/clinic-images/";
                $target_file = $target_dir . basename($image);
                move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);
            } else {
                // If no new image is uploaded, keep the existing one
                $existingClinic = $this->clinics->getClinicById($id);
                $image = $existingClinic['image'];
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

    public function getClinicForBookings() {
        return $this->clinics->getClinicForBookings();
    }
}