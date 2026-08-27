<?php
require_once '../models/appointmentModel.php';
require_once '../../config/conn.php';
require_once '../models/patientModel.php';
require_once '../helpers/csrf.php';

// The CSRF helper may have already opened the session. Starting it only when
// needed keeps PHP notices out of JSON responses consumed by fetch().
if (session_status() === PHP_SESSION_NONE) session_start();

class AppointmentController {
    private $appointmentModel;
    private $patientModel;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->appointmentModel = new Appointment($conn);
        $this->patientModel = new Patient($conn);
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

        $user_id = $_SESSION['user_id'];

        $patient = $this->patientModel->getPatientByUserId($user_id);

        if (!$patient) {
            die("Patient record not found.");
        }

        $data = $this->appointmentModel->getPatientUpcomingAppointments($patient['patient_id']);
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

        $user_id = $_SESSION['user_id'];

        $patient = $this->patientModel->getPatientByUserId($user_id);

        if (!$patient) {
            die("Patient record not found.");
        }

        $data = $this->appointmentModel->getPatientUpcomingAppointments($patient['patient_id']);
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

        $data = $this->appointmentModel->getAllUpcomingWithStatus();
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
        $data = $this->appointmentModel->getAdminPastAppointments();
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
            if (!validate_csrf()) {
                echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
                exit;
            }
            $appointment_id = $_POST['appointment_id'] ?? '';
            $status         = $_POST['status'] ?? '';
            $reason         = trim($_POST['reason'] ?? '');

            if (!$appointment_id || !$status) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
                exit;
            }

            $result = $this->appointmentModel->updateAppointmentStatus(
                $appointment_id,
                $status,
                $_SESSION['user_id'],
                $reason
            );

            if ($result['success']) {
                $message = $result['message'];

                // Email is now queued by the model inside the status transaction,
                // so this response is not delayed by the SMTP connection.
                if (!empty($result['notification'])) {
                    $message .= ' Patient notification scheduled.';
                }

                echo json_encode([
                    'success' => true,
                    'changed' => $result['changed'] ?? false,
                    'message' => $message,
                    'audit' => $result['audit'] ?? null,
                    'appointment' => $result['appointment'] ?? null,
                    'notification' => $result['notification'] ?? null,
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
            exit;
        }
    }

    //Patient: book appointment
    public function bookAppointment() {

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Patient') {
                echo json_encode(['success' => false, 'message' => 'Please sign in with a patient account before booking.']);
                exit;
            }
            if (!validate_csrf()) {
                echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
                exit;
            }
            require_once '../models/clinicModel.php';
            require_once '../models/scheduleModel.php';
            require_once '../models/patientModel.php';
            require_once '../models/serviceModel.php';

            $db = new Database();
            $conn = $db->connect();

            $patientModel = new Patient($conn);
            $clinicModel = new Clinic($conn);
            $scheduleModel = new Schedule($conn);
            $serviceModel = new ServiceModel($conn);

            // GET SELECTED CLINIC + SCHEDULE + SERVICES
            $clinic_id   = $_POST['clinic_id'] ?? '';
            $schedule_id = $_POST['schedule_id'] ?? '';
            $service_ids = $_POST['service_ids'] ?? ($_POST['service_id'] ?? []);
            $service_ids = array_values(array_unique(array_filter(array_map('intval', (array) $service_ids))));
            $appointmentRules = require __DIR__ . '/../../config/appointment.php';
            $maxServicesPerVisit = max(1, (int) ($appointmentRules['max_services_per_visit'] ?? 5));

            if (!$clinic_id || !$schedule_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please select a clinic and schedule.'
                ]);
                exit;
            }

            if (empty($service_ids)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please select at least one service.'
                ]);
                exit;
            }

            if (count($service_ids) > $maxServicesPerVisit) {
                echo json_encode([
                    'success' => false,
                    'message' => "You can select up to {$maxServicesPerVisit} services per visit."
                ]);
                exit;
            }

            $clinic = $clinicModel->getClinicById($clinic_id);
            $schedule = $scheduleModel->getScheduleById($schedule_id);
            if (!$clinic || !$schedule || (int) $schedule['clinic_id'] !== (int) $clinic_id || $schedule['sched_date'] < date('Y-m-d')) {
                echo json_encode([
                    'success'=>false,
                    'message'=>'Invalid clinic or schedule.'
                ]);
                exit;
            }

            foreach ($service_ids as $service_id) {
                $service = $serviceModel->getServiceById($service_id);
                if (!$service || (int) ($service['is_active'] ?? 0) !== 1) {
                    echo json_encode([
                        'success'=>false,
                        'message'=>'One or more selected services are invalid.'
                    ]);
                    exit;
                }
            }

            // Every appointment belongs to an authenticated patient account.
            if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'Patient') {
                $patient = $patientModel->getPatientByUserId($_SESSION['user_id']);
                if (!$patient) {
                    echo json_encode(['success' => false, 'message' => 'Patient profile not found.']);
                    exit;
                }
                $patient_id = $patient['patient_id'];
            }

            $totalAppointments = $this->appointmentModel
                ->countAppointmentsBySchedule($schedule_id);

            if ($totalAppointments >= $schedule['max_appointments']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No available slots for this schedule.'
                ]);
                exit;
            }

            // 2. CREATE APPOINTMENT USING patient_id
            $result = $this->appointmentModel->bookAppointment(
                $patient_id,
                $clinic_id,
                $service_ids,
                $schedule['sched_date'],
                $schedule_id,
                $_SESSION['user_id']
            );



            if ($result && ($result['success'] ?? false)) {
                echo json_encode([
                    'success'=>true,
                    'appointment_id'=>(int) $result['appointment_id'],
                    'status'=>'Pending Review',
                    'requires_payment'=>false,
                    'message'=>$result['message']
                ]);
            } else {
                echo json_encode([
                    'success'=>false,
                    'message'=>$result['message'] ?? 'Booking failed. Please try again.'
                ]);
            }
            exit;
        }
    }

    public function filterByStatus() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

        if (!in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        $status = trim($_POST['status'] ?? '');

        if ($status === '') {
            echo json_encode(['success' => false, 'message' => 'Missing status.']);
            exit;
        }

        $data = $this->appointmentModel->getAppointmentsByStatus($status);

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        exit;
    }

    public function latestAppointment() {
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

        if (!in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'latest_appointment_id' => $this->appointmentModel->getLatestAppointmentId(),
            'deposit_feed_version' => $this->appointmentModel->getDepositFeedVersion(),
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'latestAppointment') {
    (new AppointmentController())->latestAppointment();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $controller = new AppointmentController();

    if ($action === 'book') {
        $controller->bookAppointment();
    } elseif ($action === 'updateStatus') {
        $controller->updateStatus();
    } elseif ($action === 'filterByStatus') {
        $controller->filterByStatus();
    }
}
