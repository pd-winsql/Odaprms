<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../models/reportModel.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    exit('Forbidden.');
}

if (($_GET['action'] ?? '') !== 'export_csv') {
    http_response_code(404);
    exit('Unknown report action.');
}

try {
    $filters = ReportModel::normalizeFilters($_GET);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    exit($e->getMessage());
}

$db = new Database();
$conn = $db->connect();
if (!$conn) {
    http_response_code(500);
    exit('The report database is unavailable.');
}

$model = new ReportModel($conn);
$isUtilization = $filters['report_type'] === 'utilization';
$rows = $isUtilization
    ? $model->getClinicUtilizationReport($filters)
    : $model->getAppointmentReport($filters);

$filename = ($isUtilization ? 'clinic-utilization' : 'appointments')
    . '-' . $filters['date_from'] . '-to-' . $filters['date_to'] . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");

function safeCsvCell($value)
{
    $value = (string) $value;
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function writeCsvRow($output, $values)
{
    fputcsv($output, array_map('safeCsvCell', $values));
}

writeCsvRow($output, ['Report', $isUtilization ? 'Clinic Utilization' : 'Appointments']);
writeCsvRow($output, ['Date range', $filters['date_from'] . ' to ' . $filters['date_to']]);
writeCsvRow($output, ['Generated', date('Y-m-d H:i:s')]);
writeCsvRow($output, []);

if ($isUtilization) {
    writeCsvRow($output, ['Clinic', 'Scheduled Days', 'Capacity', 'Booked', 'Available Slots', 'Completed', 'Cancelled / Rejected', 'Utilization Rate']);
    foreach ($rows as $row) {
        writeCsvRow($output, [
            $row['clinic_name'], $row['scheduled_days'], $row['capacity'], $row['booked'],
            $row['available_slots'], $row['completed'], $row['cancelled'], $row['utilization_rate'] . '%',
        ]);
    }
} else {
    writeCsvRow($output, ['Appointment ID', 'Date', 'Patient', 'Service', 'Clinic', 'Status']);
    foreach ($rows as $row) {
        writeCsvRow($output, [
            $row['appointment_id'], $row['date'], $row['patient_name'],
            $row['service_name'], $row['clinic_name'], $row['status'],
        ]);
    }
}

fclose($output);
exit;
