<?php
require_once '../models/scheduleModel.php';
require_once '../../config/conn.php';

class ScheduleController {
    private $schedules;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->schedules = new Schedule($conn);
    }

    public function index($clinic_id) {
        $data = $this->schedules->getSchedulesByClinic($clinic_id);
        require_once '../views/schedule-index.php';
    }

    public function available($clinic_id) {
        header('Content-Type: application/json');
        $data = $this->schedules->getAvailableSchedulesByClinic($clinic_id);
        echo json_encode($data);
    }

    public function addSchedule() {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $clinic_id = $_POST['clinic_id'];
            $sched_date = $_POST['sched_date'];
            $max_appointments = $_POST['max_appointments'];

            $result = $this->schedules->addSchedule($clinic_id, $sched_date, $max_appointments);

            if ($result) {
                header("Location: ../views/admin/schedules.php?added=1");
            } else {
                header("Location: ../views/admin/schedules.php?error=1");
            }
            exit;
        }
    }

    public function updateSchedule() {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $schedule_id = $_POST['schedule_id'];
            $clinic_id = $_POST['clinic_id'];
            $sched_date = $_POST['sched_date'];
            $max_appointments = $_POST['max_appointments'];

            $result = $this->schedules->updateSchedule($schedule_id, $clinic_id, $sched_date, $max_appointments);

            if ($result) {
                header("Location: ../views/admin/schedules.php?updated=1");
            } else {
                header("Location: ../views/admin/schedules.php?error=1");
            }
            exit;
        }
    }

    public function deleteSchedule() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $schedule_id = $_POST['schedule_id'];
            $result = $this->schedules->deleteSchedule($schedule_id);
            if ($result) {
                header("Location: ../views/admin/schedules.php?deleted=1");
            } else {
                header("Location: ../views/admin/schedules.php?error=1");
            }
            exit;
        }
    }
}

$controller = new ScheduleController();

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'available':

        $clinic_id = $_GET['clinic_id'] ?? 0;

        $controller->available($clinic_id);

        break;

    default:
        echo json_encode([]);
        break;
}