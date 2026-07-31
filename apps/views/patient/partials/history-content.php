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
$past     = $appointmentModel->getPatientPastAppointments($patient['patient_id']);

function statusClass($s) {
    return 'vd-status vd-status-' . strtolower($s);
}
?>

<div class="d-flex flex-column gap-4">

    <div class="vd-pat-welcome">
        <div class="vd-welcome-greet">APPOINTMENT HISTORY</div>
        <div class="vd-welcome-name">Your previous visits</div>
        <p class="text-muted small mb-0 mt-2">
            Review your past appointment dates, clinic locations, services, and final appointment statuses.
        </p>
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
                <div class="vd-appt-name"><?= htmlspecialchars($appt['service_name']) ?></div>
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
