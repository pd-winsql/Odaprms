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
$email    = $patient['email'] ?? '';
$upcoming = $appointmentModel->getPatientUpcomingAppointments($email);
$past     = $appointmentModel->getPatientPastAppointments($email);

function statusClass($s) {
    return 'vd-status vd-status-' . strtolower($s);
}
?>

<div class="d-flex flex-column gap-4">

    <!-- Book button -->
    <div class="d-flex justify-content-end">
        <a href="../../../apps/views/ventura_booking_form.php" class="btn vd-btn-gold">
        <i class="ti ti-plus me-1"></i> Book Appointment
        </a>
    </div>

    <!-- Upcoming -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Upcoming Appointments</span>
        <span class="vd-topbar-date"><?= count($upcoming) ?> total</span>
        </div>
        <div class="vd-dash-card-body" style="padding:0;">
        <?php if (empty($upcoming)): ?>
            <div class="vd-empty-state">No upcoming appointments.</div>
        <?php else: ?>
            <?php foreach ($upcoming as $appt): ?>
            <div class="vd-pat-appt-row">
            <div class="vd-appt-date-box">
                <span class="vd-appt-day"><?= date('d', strtotime($appt['date'])) ?></span>
                <span class="vd-appt-mon"><?= date('M', strtotime($appt['date'])) ?></span>
            </div>
            <div class="vd-appt-info">
                <div class="vd-appt-name"><?= htmlspecialchars($appt['service']) ?></div>
                <div class="vd-appt-meta">
                <?= htmlspecialchars($appt['clinic_name'] ?? $appt['clinic'] ?? '—') ?>
                </div>
            </div>
            <span class="<?= statusClass($appt['status']) ?>">
                <?= htmlspecialchars($appt['status']) ?>
            </span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>

    <!-- Past -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Past Appointments</span>
        <span class="vd-topbar-date"><?= count($past) ?> total</span>
        </div>
        <div class="vd-dash-card-body" style="padding:0;">
        <?php if (empty($past)): ?>
            <div class="vd-empty-state">No past appointments.</div>
        <?php else: ?>
            <?php foreach ($past as $appt): ?>
            <div class="vd-pat-appt-row">
            <div class="vd-appt-date-box">
                <span class="vd-appt-day"><?= date('d', strtotime($appt['date'])) ?></span>
                <span class="vd-appt-mon"><?= date('M', strtotime($appt['date'])) ?></span>
            </div>
            <div class="vd-appt-info">
                <div class="vd-appt-name"><?= htmlspecialchars($appt['service']) ?></div>
                <div class="vd-appt-meta">
                <?= htmlspecialchars($appt['clinic_name'] ?? $appt['clinic'] ?? '—') ?>
                </div>
            </div>
            <span class="<?= statusClass($appt['status']) ?>">
                <?= htmlspecialchars($appt['status']) ?>
            </span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div>