<?php
require_once '../models/appointmentModel.php';
require_once '../../config/conn.php';

session_start();

class AppointmentController {
    private $appointments;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->appointments = new Appointment($conn);
    }

    //Patient: upcoming appointments
    public function upcomingAppointments() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../../../index.php?openModal=true');
            exit;
        }

        if ($_SESSION['user_role'] !== 'Patient') {
            header('Location: ../admin/dashboard.php');
            exit;
        }

        $email = $_SESSION['email'];
        $data = $this->appointments->getPatientUpcomingAppointments($email);
        require_once '../views/patient-upcoming-appointments.php';
    }

    //Patient: past appointments
    public function pastAppointments() {
            if (!isset($_SESSION['user_id'])) {
                header('Location: ../../../index.php?openModal=true');
                exit;
            }

        if ($_SESSION['user_role'] !== 'Patient') {
            header('Location: ../admin/dashboard.php');
            exit;
        }

        $email = $_SESSION['email'];
        $data = $this->appointments->getPatientPastAppointments($email);
        require_once '../views/patient-past-appointments.php';
    }

    //Admin: all upcoming appointments
    public function adminUpcoming() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../../../index.php?openModal=true');
            exit;
        }

        if (!in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
            header('Location: ../patient/dashboard.php');
            exit;
        }

        $data = $this->appointments->getAllUpcomingWithStatus();
        require_once '../views/admin-upcoming-appointments.php';
    }

    //Admin: all past appointments
    public function adminPast() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../../../index.php?openModal=true');
            exit;
        }

        if (!in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
            header('Location: ../patient/dashboard.php');
            exit;
        }
        $data = $this->appointments->getAdminPastAppointments();
        require_once '../views/admin-past-appointments.php';
    }

    //Update appointment status
    public function updateStatus() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

        if (!in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $appointment_id = $_POST['appointment_id'] ?? '';
            $status         = $_POST['status'] ?? '';

            if (!$appointment_id || !$status) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
                exit;
            }

            $result = $this->appointments->updateAppointmentStatus($appointment_id, $status);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
            }
            exit;
        }
    }

    //Patient: book appointment
    public function bookAppointment() {

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once '../models/clinicModel.php';
            require_once '../models/scheduleModel.php';
            require_once '../models/patientModel.php';

            $db = new Database();
            $conn = $db->connect();

            $patientModel = new Patient($conn);
            $clinicModel = new Clinic($conn);
            $scheduleModel = new Schedule($conn);

            // GET SELECTED CLINIC + SCHEDULE
            $clinic_id = $_POST['clinic_id'] ?? '';
            $schedule_id = $_POST['schedule_id'] ?? '';

            if (!$clinic_id || !$schedule_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please select a clinic and schedule.'
                ]);
                exit;
            }

            $clinic = $clinicModel->getClinicById($clinic_id);
            $schedule = $scheduleModel->getScheduleById($schedule_id);
            if (!$clinic || !$schedule) {
                echo json_encode([
                    'success'=>false,
                    'message'=>'Invalid clinic or schedule.'
                ]);
                exit;
            }

            // 1. CREATE PATIENT FIRST
            $patient_id = $patientModel->createPatient(
                $_POST['firstname'],
                $_POST['lastname'],
                $_POST['middlename'],
                $_POST['age'],
                $_POST['gender'],
                $_POST['phone_number'],
                $_POST['email']
            );

            if (!$patient_id) {
                echo json_encode([
                    'success'=>false,
                    'message'=>'Failed creating patient record.'
                ]);
                exit;
            }

            // 2. CREATE APPOINTMENT USING patient_id
            $result = $this->appointments->bookAppointment(
                $patient_id,
                $clinic_id,
                $_POST['service'],
                $schedule['sched_date'],
                $schedule_id
            );



            if ($result) {
                $id = $this->appointments->getLastInsertedId();
                echo json_encode([
                    'success'=>true,
                    'appointment_id'=>$id
                ]);
            } else {
                echo json_encode([
                    'success'=>false,
                    'message'=>'Booking failed. Please try again.'
                ]);
            }
            exit;
        }
    }

}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $controller = new AppointmentController();

    if ($action === 'book') {
        $controller->bookAppointment();
    } elseif ($action === 'updateStatus') {
        $controller->updateStatus();
    }
}