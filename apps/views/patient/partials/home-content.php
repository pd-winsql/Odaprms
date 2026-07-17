<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/appointmentModel.php';

$db   = new Database();
$conn = $db->connect();

$patientModel     = new Patient($conn);
$appointmentModel = new Appointment($conn);

$patient  = $patientModel->getPatientByUserId($_SESSION['user_id']);
$upcoming = $appointmentModel->getPatientUpcomingAppointments($patient['email'] ?? '');
$next     = $upcoming[0] ?? null;

$hour     = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstname = $patient['firstname'] ?? $_SESSION['username'];
?>
<div class="d-flex flex-column gap-4">
    
    <!-- Welcome -->
    <div class="vd-pat-welcome">
        <div class="vd-welcome-greet"><?= $greeting ?>,</div>
        <div class="vd-welcome-name"><?= htmlspecialchars($firstname) ?></div>
    </div>
    
    <!-- Next appointment -->
    <?php if ($next): ?>
    <div class="vd-next-appt-card">
        <div class="vd-next-appt-label">Next Appointment</div>
        <div class="vd-next-appt-service"><?= htmlspecialchars($next['service']) ?></div>
        <div class="vd-next-appt-meta">
        <span><i class="ti ti-building"></i> <?= htmlspecialchars($next['clinic_name'] ?? $next['clinic'] ?? '—') ?></span>
        <span><i class="ti ti-calendar"></i> <?= date('F d, Y', strtotime($next['date'])) ?></span>
        </div>
        <span class="vd-status vd-status-<?= strtolower($next['status']) ?>">
        <?= htmlspecialchars($next['status']) ?>
        </span>
    </div>
    <?php else: ?>
    <div class="vd-next-appt-empty">
        <i class="ti ti-calendar-off" style="font-size:28px; color:var(--border);"></i>
        <div class="mt-2">No upcoming appointments.</div>
        <a href="../../../apps/views/ventura_booking_form.php" class="btn vd-btn-gold mt-3">
        Book an Appointment
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Quick actions -->
    <div class="vd-pat-quick-grid">
        <a href="../../../apps/views/ventura_booking_form.php" class="vd-pat-quick-card">
        <i class="ti ti-calendar-plus"></i>
        <span>Book Appointment</span>
        </a>
        <button class="vd-pat-quick-card" onclick="document.querySelector('[data-page=\'appointments-content.php\']').click()">
        <i class="ti ti-list"></i>
        <span>My Appointments</span>
        </button>
        <button class="vd-pat-quick-card" onclick="document.querySelector('[data-page=\'profile-content.php\']').click()">
        <i class="ti ti-user"></i>
        <span>My Profile</span>
        </button>
    </div>
 
    <!-- Upcoming appointments summary -->
    <?php if (!empty($upcoming)): ?>
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Upcoming Appointments</span>
        <span class="vd-topbar-date"><?= count($upcoming) ?> total</span>
        </div>
        <div class="vd-dash-card-body" style="padding: 0;">
        <?php foreach ($upcoming as $appt): ?>
        <div class="vd-pat-appt-row">
            <div class="vd-appt-date-box">
            <span class="vd-appt-day"><?= date('d', strtotime($appt['date'])) ?></span>
            <span class="vd-appt-mon"><?= date('M', strtotime($appt['date'])) ?></span>
            </div>
            <div class="vd-appt-info">
            <div class="vd-appt-name"><?= htmlspecialchars($appt['service']) ?></div>
            <div class="vd-appt-meta"><?= htmlspecialchars($appt['clinic_name'] ?? $appt['clinic'] ?? '—') ?></div>
            </div>
            <span class="vd-status vd-status-<?= strtolower($appt['status']) ?>">
            <?= htmlspecialchars($appt['status']) ?>
            </span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
 
</div>