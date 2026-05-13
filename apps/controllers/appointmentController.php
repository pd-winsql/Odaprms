<?php
require_once '../models/appointmentModel.php';
require_once '../../../config/conn.php';

class AppointmentController {
    private $appointments;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->appointments = new Appointment($conn);
    }

    //Patient: upcoming appointments
    public function upcomingAppointments() {
        $email = $_SESSION['email'];
        $data = $this->appointments->getPatientUpcomingAppointments($email);
        require_once '../views/patient-upcoming-appointments.php';
    }

    //Patient: past appointments
    public function pastAppointments() {
        $email = $_SESSION['email'];
        $data = $this->appointments->getPatientPastAppointments($email);
        require_once '../views/patient-past-appointments.php';
    }

    //Admin: all upcoming appointments
    public function adminUpcoming() {
        $email = $_SESSION['email'];
        $data = $this->appointments->getAllUpcomingWithStatus();
        require_once '../views/admin-upcoming-appointments.php';
    }

    //Admin: all past appointments
    public function adminPast() {
        $data = $this->appointments->getAdminPastAppointments();
        require_once '../views/admin-past-appointments.php';
    }

    //Update appointment status
    public function updateStatus() {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $appointment_id = $_POST['appointment_id'];
            $status = $_POST['status'];

            $result = $this->appointments->updateAppointmentStatus($appointment_id, $status);

            if ($result) {
                header ("Location: ../views/admin/upcoming.php?updated=1");
            } else {
                header("Location: ../views/admin/upcoming.php?error=1");
            }
            exit;
        }
    }

    //Patient: book appointment
    public function bookAppointment() {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->appointments->bookAppointment(
                $_POST['lastname'],
                $_POST['firstname'],
                $_POST['middlename'],
                $_POST['age'],
                $_POST['gender'],
                $_POST['phone_number'],
                $_POST['email'],
                $_POST['clinic'],
                $_POST['service'],
                $_POST['date'],
                $_POST['time']
            );

            if ($result) {
                header("Location: ../views/patient/upcoming.php?success=1");
            } else {
                header("Location: ../views/patient/book.php?error=1");
            }
            exit;
        }
    }
}