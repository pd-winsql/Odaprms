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
$otherUpcoming = array_slice($upcoming, 1);

$hour     = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstname = $patient['firstname'] ?? $_SESSION['display_name'] ?? 'Patient';

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
        <span class="vd-status vd-status-<?= htmlspecialchars(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $next['status']))) ?>">
        <?= htmlspecialchars($next['status']) ?>
        </span>
        <?php if (!empty($next['appointment_code'])): ?>
        <div class="alert alert-success mt-3 mb-0">
            <small class="d-block text-uppercase">Front-desk appointment code</small>
            <strong style="font-size:1.3rem;letter-spacing:.12em"><?= htmlspecialchars($next['appointment_code']) ?></strong>
        </div>
        <?php endif; ?>
        <button type="button" class="btn vd-home-next-cta" onclick="document.querySelector('[data-page=\'booking-content.php\']').click()">
            <i class="ti ti-calendar-plus me-1"></i> View Available Schedules
        </button>
    </div>

    <?php if (!empty($otherUpcoming)): ?>
    <section class="vd-other-appts" aria-labelledby="otherUpcomingTitle">
        <div class="vd-other-appts-heading">
            <div>
                <div class="vd-next-appt-label">Coming Up</div>
                <h2 id="otherUpcomingTitle">Other Upcoming Appointments</h2>
            </div>
            <span class="vd-other-appts-count"><?= count($otherUpcoming) ?></span>
        </div>

        <div class="vd-other-appts-list">
            <?php foreach ($otherUpcoming as $appointment): ?>
                <?php
                $appointmentStatus = $appointment['status'] ?? 'Pending';
                $statusClass = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $appointmentStatus));
                ?>
                <article class="vd-other-appt-item">
                    <div class="vd-other-appt-date" aria-label="<?= date('F d, Y', strtotime($appointment['date'])) ?>">
                        <span><?= date('M', strtotime($appointment['date'])) ?></span>
                        <strong><?= date('d', strtotime($appointment['date'])) ?></strong>
                    </div>
                    <div class="vd-other-appt-copy">
                        <h3><?= htmlspecialchars($appointment['service_name'] ?: 'Dental appointment') ?></h3>
                        <div class="vd-other-appt-meta">
                            <span><i class="ti ti-building"></i> <?= htmlspecialchars($appointment['clinic_name'] ?? $appointment['clinic'] ?? '—') ?></span>
                            <span><i class="ti ti-calendar"></i> <?= date('F d, Y', strtotime($appointment['date'])) ?></span>
                        </div>
                    </div>
                    <span class="vd-status vd-status-<?= htmlspecialchars($statusClass) ?>">
                        <?= htmlspecialchars($appointmentStatus) ?>
                    </span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <p class="vd-no-other-appts mb-0">No other upcoming appointments are scheduled.</p>
    <?php endif; ?>
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
