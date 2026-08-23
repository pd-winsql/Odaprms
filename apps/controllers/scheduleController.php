<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../models/scheduleModel.php';
require_once '../helpers/csrf.php';
require_once '../../config/conn.php';

class ScheduleController {
    private $schedules;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->schedules = new Schedule($conn);
    }

    private function requireStaffMutation(): void {
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
            http_response_code(403);
            exit('Forbidden.');
        }
        if (!validate_csrf()) {
            http_response_code(419);
            exit('Your session expired. Refresh and try again.');
        }
    }


    public function available($clinic_id) {
        header('Content-Type: application/json');
        $data = $this->schedules->getAvailableSchedulesByClinic($clinic_id);
        echo json_encode($data);
    }

    public function addSchedule() {
        $this->requireStaffMutation();
        header('Content-Type: text/plain');
        $clinic_id = $_POST['clinic_id'] ?? '';
        $submittedSchedules = $_POST['schedules'] ?? null;

        // single-date form request.
        if (!is_array($submittedSchedules)) {
            $submittedSchedules = [[
                'sched_date' => $_POST['sched_date'] ?? '',
                'max_appointments' => $_POST['max_appointments'] ?? 8,
            ]];
        }

        if (!$clinic_id || empty($submittedSchedules) || count($submittedSchedules) > 100) {
            echo 'Add between 1 and 100 schedules.';
            exit;
        }

        // Validate every row before starting the batch insert.
        $schedules = [];
        $seenDates = [];
        $latestAllowedDate = new DateTimeImmutable('last day of next month');
        foreach ($submittedSchedules as $index => $submittedSchedule) {
            $schedDate = is_array($submittedSchedule) ? ($submittedSchedule['sched_date'] ?? '') : '';
            $maxAppointments = (int) (is_array($submittedSchedule) ? ($submittedSchedule['max_appointments'] ?? 0) : 0);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $schedDate);

            if (!$date || $date->format('Y-m-d') !== $schedDate) {
                echo 'Schedule #' . ($index + 1) . ' has an invalid date.';
                exit;
            }
            if ($date < new DateTimeImmutable('today')) {
                echo 'Schedule #' . ($index + 1) . ' cannot be in the past.';
                exit;
            }
            if ($date > $latestAllowedDate) {
                echo 'Schedules can only be added through the end of next month.';
                exit;
            }
            if ($maxAppointments < 1 || $maxAppointments > 50) {
                echo 'Schedule #' . ($index + 1) . ' must have 1 to 50 maximum appointments.';
                exit;
            }
            // Reject duplicate dates within the same submission.
            if (isset($seenDates[$schedDate])) {
                echo 'The same date was added more than once.';
                exit;
            }
            // Keep the existing rule that a date can only have one schedule.
            if ($this->schedules->existsScheduleOnDate($schedDate)) {
                echo 'A schedule already exists for ' . $date->format('M j, Y') . '.';
                exit;
            }

            $seenDates[$schedDate] = true;
            $schedules[] = ['sched_date' => $schedDate, 'max_appointments' => $maxAppointments];
        }

        // Insert all validated rows together, or none if the insert fails.
        $result = $this->schedules->addSchedules($clinic_id, $schedules);

        if ($result) {
            echo 'success';
        } else {
            echo 'error';
        }
        exit;
    }

    public function deleteSchedule() {
        $this->requireStaffMutation();
        header('Content-Type: application/json');
        $schedule_id = $_POST['schedule_id'] ?? '';

        if (!$schedule_id) {
            echo json_encode(['success' => false, 'message' => 'Missing schedule ID.']);
            exit;
        }

        $booked = $this->schedules->getBookedCountForSchedule($schedule_id);
        if ($booked < 0) {
            echo 'Unable to verify existing bookings. Please try again.';
            exit;
        }
        if ($booked > 0) {
            echo 'Schedules with existing bookings cannot be deleted.';
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

            // Prevent changing the date to one that already exists for another schedule (across clinics)
            if ($this->schedules->existsScheduleOnDate($sched_date, $schedule_id)) {
                header("Location: ../views/admin/schedules.php?error=conflict");
                exit;
            }

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
        $this->requireStaffMutation();
        header('Content-Type: text/plain');

        $schedule_id = $_POST['schedule_id'] ?? '';
        $max_appointments = $_POST['max_appointments'] ?? '';

        $max_appointments = (int) $max_appointments;
        if (!$schedule_id || $max_appointments < 1 || $max_appointments > 50) {
            echo 'Maximum appointments must be between 1 and 50.';
            exit;
        }

        $booked = $this->schedules->getBookedCountForSchedule($schedule_id);
        if ($booked < 0) {
            echo 'Unable to verify existing bookings. Please try again.';
            exit;
        }
        if ($max_appointments < $booked) {
            echo "Capacity cannot be lower than the {$booked} existing booking" . ($booked === 1 ? '.' : 's.');
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

