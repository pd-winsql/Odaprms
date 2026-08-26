<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/odontogramView.php';

$patientId = (int) ($_GET['id'] ?? 0);
$appointmentId = (int) ($_GET['appointment_id'] ?? 0);
$readOnly = ($_SESSION['user_role'] ?? '') !== 'Admin';
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
?>

<div class="mb-3">
    <button class="btn vd-btn-outline vd-back-btn" id="backFromOdontogram">
        <i class="ti ti-arrow-left me-1" aria-hidden="true"></i> Back
    </button>
</div>
<div class="vd-dash-card">
    <div class="vd-dash-card-body">
        <?php vdRenderOdontogramWorkspace('patientOdontogramWorkspace', $readOnly); ?>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById('patientOdontogramWorkspace');
    const workspace = window.VdOdontogram?.mount(root);
    workspace?.load(<?= $patientId ?>, <?= $appointmentId ?>).catch(error => console.error('Odontogram load failed:', error));
    document.getElementById('backFromOdontogram')?.addEventListener('click', () => {
        const patientNav = document.querySelector('[data-page="patient-content.php"]');
        if (patientNav) patientNav.click();
        else document.querySelector('[data-page="dashboard-content.php"]')?.click();
    });
})();
</script>
