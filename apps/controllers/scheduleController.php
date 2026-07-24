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


    public function available($clinic_id) {
        header('Content-Type: application/json');
        $data = $this->schedules->getAvailableSchedulesByClinic($clinic_id);
        echo json_encode($data);
    }

    public function addSchedule() {
        header('Content-Type: application/json');
        $clinic_id = $_POST['clinic_id'] ?? '';
        $sched_date = $_POST['sched_date'] ?? '';
        $max_appointments = $_POST['max_appointments'] ?? 8;

        if(!$clinic_id || !$sched_date) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }
        $result = $this->schedules->addSchedule($clinic_id, $sched_date, $max_appointments);

        if ($result) {
            echo 'success';
        } else {
            echo 'error';
        }
        exit;
    }

    public function deleteSchedule() {
        header('Content-Type: application/json');
        $schedule_id = $_POST['schedule_id'] ?? '';

        if (!$schedule_id) {
            echo json_encode(['success' => false, 'message' => 'Missing schedule ID.']);
            exit;
        }

        $result = $this->schedules->deleteSchedule($schedule_id);

        if ($result) {
            echo 'success';
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete schedule.']);
        }
        exit;
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

    public function updateMaxAppointments()
    {
        header('Content-Type: text/plain');

        $schedule_id = $_POST['schedule_id'] ?? '';
        $max_appointments = $_POST['max_appointments'] ?? '';

        if (!$schedule_id || $max_appointments === '') {
            echo 'error';
            exit;
        }

        $result = $this->schedules->updateMaxAppointments(
            $schedule_id,
            $max_appointments
        );

        echo $result ? 'success' : 'error';
        exit;
    }
}

$controller = new ScheduleController();

if($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $clinic_id = $_GET['clinic_id'] ?? 0;

    if ($action === 'available' && $clinic_id) {
        $controller->available($clinic_id);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_schedule') {
        $controller->addSchedule();
    } elseif ($action === 'delete_schedule') {
        $controller->deleteSchedule();
    } elseif ($action === 'edit_schedule') {
        $controller->updateMaxAppointments();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
        }
    }

