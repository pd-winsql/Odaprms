<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/appointmentModel.php';

$patient_id = $_GET['id'] ?? null;

if (!$patient_id) {
    echo '<div class="vd-empty-state">No patient specified.</div>';
    exit;
}

$db   = new Database();
$conn = $db->connect();

$patientModel     = new Patient($conn);
$appointmentModel = new Appointment($conn);

$patient      = $patientModel->getPatient($patient_id);
$transactions = $appointmentModel->getPatientTransactionHistory($patient_id);

if (!$patient) {
    echo '<div class="vd-empty-state">Patient not found.</div>';
    exit;
}

function txStatusClass($status) {
    return 'vd-status vd-status-' . strtolower($status);
}
?>

<div class="mb-3">
    <button id="backToPatients" class="btn vd-btn-outline vd-back-btn">
        &larr; Back to Patients
    </button>
</div>

<div class="d-flex flex-column gap-4">




    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">
                Transaction History — <?= htmlspecialchars($patient['lastname'] . ', ' . $patient['firstname']) ?>
            </span>
            <span class="vd-topbar-date"><?= count($transactions) ?> total</span>
        </div>

        <div class="vd-dash-card-body">
            

            <?php if (empty($transactions)): ?>
                <div class="vd-empty-state">No transaction history found for this patient.</div>
            <?php else: ?>
                <div class="vd-appt-table-wrap">
                    <table class="vd-appt-table w-100">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Clinic</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td class="vd-appt-meta"><?= htmlspecialchars($t['service_name']) ?></td>
                                    <td class="vd-appt-meta"><?= htmlspecialchars($t['clinic_name'] ?? '—') ?></td>
                                    <td class="vd-appt-meta"><?= date('M d, Y', strtotime($t['date'])) ?></td>
                                    <td>
                                        <span class="<?= txStatusClass($t['status']) ?>">
                                            <?= htmlspecialchars($t['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
(function () {
    const backBtn = document.getElementById('backToPatients');
    if (backBtn) {
        backBtn.addEventListener('click', async () => {
            if (typeof loadpage === 'function') {
                await loadpage('patient-content.php');
            }
        });
    }
})();
</script>