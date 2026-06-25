<?php
require_once '../../config/conn.php';
require_once '../models/patientModel.php';

session_start();

class PatientController {
    private $patients;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->patients = new Patient($conn);
    }

    //Admin: all patients
    public function adminAllPatients() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../../../index.php?openModal=true');
            exit;
        }

        if (!in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
            header('Location: ../admin/dashboard.php');
            exit;
        }

        $data = $this->patients->getAllPatients();
        require_once '../views/admin-all-patients.php';
    }
}    