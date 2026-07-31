<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/appointmentModel.php';

date_default_timezone_set('Asia/Manila');

$db   = new Database();
$conn = $db->connect(); 

$patientModel     = new Patient($conn);
$appointmentModel = new Appointment($conn);

$patient  = $patientModel->getPatientByUserId($_SESSION['user_id']);
$upcoming = $appointmentModel->getPatientUpcomingAppointments($patient['patient_id'] ?? '');
$next     = $upcoming[0] ?? null;

$hour     = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstname = $patient['firstname'] ?? $_SESSION['username'];

$profileRequirements = [
    'first name' => $patient['firstname'] ?? '',
    'last name' => $patient['lastname'] ?? '',
    'birthdate' => $patient['birthdate'] ?? '',
    'gender' => $patient['gender'] ?? '',
    'phone number' => $patient['phone_number'] ?? '',
    'email address' => $patient['email'] ?? '',
];
$missingProfileFields = array_keys(array_filter(
    $profileRequirements,
    static fn($value) => trim((string) $value) === ''
));
?>
<div class="d-flex flex-column gap-4">
    
    <!-- Welcome -->
    <div class="vd-pat-welcome">
        <div class="vd-welcome-greet"><?= $greeting ?>,</div>
        <div class="vd-welcome-name"><?= htmlspecialchars($firstname) ?></div>
        <p class="text-muted small mb-0 mt-2">Here is a quick overview of your next visit and account readiness.</p>
    </div>

    <?php if (!empty($missingProfileFields)): ?>
    <div class="vd-home-profile-notice">
        <div class="vd-home-profile-notice-icon"><i class="ti ti-user-exclamation"></i></div>
        <div class="vd-home-profile-notice-copy">
            <strong>Complete your patient profile</strong>
            <span>Add your <?= htmlspecialchars(implode(', ', $missingProfileFields)) ?> so future bookings can be prepared faster.</span>
        </div>
        <button type="button" class="btn vd-btn-outline btn-sm" onclick="document.querySelector('[data-page=\'profile-content.php\']').click()">
            Complete Profile
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Next appointment -->
    <?php if ($next): ?>
    <div class="vd-next-appt-card">
        <div class="vd-next-appt-label">Next Appointment</div>
        <div class="vd-next-appt-service"><?= htmlspecialchars($next['service_name']) ?></div>
        <div class="vd-next-appt-meta">
        <span><i class="ti ti-building"></i> <?= htmlspecialchars($next['clinic_name'] ?? $next['clinic'] ?? '—') ?></span>
        <span><i class="ti ti-calendar"></i> <?= date('F d, Y', strtotime($next['date'])) ?></span>
        </div>
        <span class="vd-status vd-status-<?= strtolower($next['status']) ?>">
        <?= htmlspecialchars($next['status']) ?>
        </span>
        <button type="button" class="btn vd-home-next-cta" onclick="document.querySelector('[data-page=\'booking-content.php\']').click()">
            <i class="ti ti-calendar-plus me-1"></i> View Available Schedules
        </button>
    </div>
    <?php else: ?>
    <div class="vd-next-appt-empty">
        <i class="ti ti-calendar-off" style="font-size:28px; color:var(--border);"></i>
        <div class="mt-2">You have no upcoming appointments.</div>
        <div class="text-muted small mt-1">Choose a clinic and view its available schedules when you are ready.</div>
        <button type="button" class="btn vd-btn-gold vd-home-empty-cta mt-3" onclick="document.querySelector('[data-page=\'booking-content.php\']').click()">
        View Available Schedules
        </button>
    </div>
    <?php endif; ?>
</div>
