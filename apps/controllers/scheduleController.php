<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../models/scheduleModel.php';
require_once '../models/clinicModel.php';
require_once '../helpers/csrf.php';
require_once '../../config/conn.php';

class ScheduleController {
    private $schedules;
    private $clinics;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->schedules = new Schedule($conn);
        $this->clinics = new Clinic($conn);
    }

    private function conflictMessage(array $conflict, int $clinicId): string {
        $date = date('M j, Y', strtotime($conflict['sched_date']));
        if ((int) $conflict['clinic_id'] === $clinicId) {
            return "This clinic already has a schedule for {$date}.";
        }
        $window = Schedule::formatTimeRange($conflict['start_time'], $conflict['end_time']);
        $gap = $this->schedules->getTransitionMinutes();
        return "This window conflicts with {$conflict['clinic_name']} ({$window}). Allow at least {$gap} minutes between clinics.";
    }

    private function validateScheduleWindow(string $startTime, string $endTime): array {
        $start = Schedule::normalizeTime($startTime);
        $end = Schedule::normalizeTime($endTime);
        if (!$start || !$end) {
            return ['success' => false, 'message' => 'Enter valid opening and closing times.'];
        }
        if (!Schedule::usesFiveMinuteIncrement($start) || !Schedule::usesFiveMinuteIncrement($end)) {
            return ['success' => false, 'message' => 'Opening and closing times must use five-minute increments.'];
        }
        if ($start >= $end) {
            return ['success' => false, 'message' => 'Closing time must be later than opening time.'];
        }
        return ['success' => true, 'start_time' => $start, 'end_time' => $end];
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
        $clinicId = (int) ($_POST['clinic_id'] ?? 0);
        $submittedSchedules = $_POST['schedules'] ?? null;
        $sharedStartTime = (string) ($_POST['start_time'] ?? '');
        $sharedEndTime = (string) ($_POST['end_time'] ?? '');

        if (!$clinicId || !$this->clinics->getClinicById($clinicId)) {
            echo 'Select a valid clinic.';
            exit;
        }
        if (!is_array($submittedSchedules)) {
            $submittedSchedules = [[
                'sched_date' => $_POST['sched_date'] ?? '',
                'start_time' => $sharedStartTime,
                'end_time' => $sharedEndTime,
                'max_appointments' => $_POST['max_appointments'] ?? 8,
            ]];
        }
        if (empty($submittedSchedules) || count($submittedSchedules) > 100) {
            echo 'Add between 1 and 100 schedules.';
            exit;
        }

        $schedules = [];
        $seenDates = [];
        $latestAllowedDate = new DateTimeImmutable('last day of next month');
        foreach ($submittedSchedules as $index => $submittedSchedule) {
            $row = is_array($submittedSchedule) ? $submittedSchedule : [];
            $schedDate = (string) ($row['sched_date'] ?? '');
            $startTime = (string) ($row['start_time'] ?? $sharedStartTime);
            $endTime = (string) ($row['end_time'] ?? $sharedEndTime);
            $maxAppointments = (int) ($row['max_appointments'] ?? 0);
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
            $window = $this->validateScheduleWindow($startTime, $endTime);
            if (!$window['success']) {
                echo 'Schedule #' . ($index + 1) . ': ' . $window['message'];
                exit;
            }
            if ($schedDate === date('Y-m-d') && $window['start_time'] < date('H:i:s')) {
                echo 'Schedule #' . ($index + 1) . ' starts in the past.';
                exit;
            }
            if ($maxAppointments < 1 || $maxAppointments > 50) {
                echo 'Schedule #' . ($index + 1) . ' must have 1 to 50 maximum appointments.';
                exit;
            }
            if (isset($seenDates[$schedDate])) {
                echo 'The same date was added more than once.';
                exit;
            }
            $conflict = $this->schedules->findWindowConflict(
                $clinicId,
                $schedDate,
                $window['start_time'],
                $window['end_time']
            );
            if ($conflict) {
                echo $this->conflictMessage($conflict, $clinicId);
                exit;
            }

            $seenDates[$schedDate] = true;
            $schedules[] = [
                'sched_date' => $schedDate,
                'start_time' => $window['start_time'],
                'end_time' => $window['end_time'],
                'max_appointments' => $maxAppointments,
            ];
        }

        $result = $this->schedules->addSchedules($clinicId, $schedules);
        if ($result['success'] ?? false) {
            echo 'success';
        } elseif (!empty($result['conflict'])) {
            echo $this->conflictMessage($result['conflict'], $clinicId);
        } else {
            echo 'Unable to add the schedules. Please try again.';
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
    

    public function updateScheduleWindow(): void {
        $this->requireStaffMutation();
        header('Content-Type: application/json');
        $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
        $clinicId = (int) ($_POST['clinic_id'] ?? 0);
        $schedDate = trim((string) ($_POST['sched_date'] ?? ''));
        $maxAppointments = (int) ($_POST['max_appointments'] ?? 0);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $schedDate);
        $window = $this->validateScheduleWindow(
            (string) ($_POST['start_time'] ?? ''),
            (string) ($_POST['end_time'] ?? '')
        );

        if (!$scheduleId || !$this->clinics->getClinicById($clinicId) || !$date || $date->format('Y-m-d') !== $schedDate) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid clinic and schedule date.']);
            exit;
        }
        if ($date < new DateTimeImmutable('today') || $date > new DateTimeImmutable('last day of next month')) {
            echo json_encode(['success' => false, 'message' => 'The schedule date must be between today and the end of next month.']);
            exit;
        }
        if (!$window['success']) {
            echo json_encode($window);
            exit;
        }
        if ($schedDate === date('Y-m-d') && $window['start_time'] < date('H:i:s')) {
            echo json_encode(['success' => false, 'message' => 'The schedule cannot start in the past.']);
            exit;
        }
        if ($maxAppointments < 1 || $maxAppointments > 50) {
            echo json_encode(['success' => false, 'message' => 'Maximum appointments must be between 1 and 50.']);
            exit;
        }

        $result = $this->schedules->updateScheduleWindow(
            $scheduleId,
            $clinicId,
            $schedDate,
            $window['start_time'],
            $window['end_time'],
            $maxAppointments
        );
        if (!empty($result['conflict'])) {
            $result['message'] = $this->conflictMessage($result['conflict'], $clinicId);
        }
        echo json_encode($result);
        exit;
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
    } elseif ($action === 'update_schedule_window') {
        $controller->updateScheduleWindow();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
        }
    }

