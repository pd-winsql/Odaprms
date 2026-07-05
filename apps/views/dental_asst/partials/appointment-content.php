<?php
// Self-contained partial — fetches its own data so it works
// whether included directly or loaded via AJAX fetch()
if (session_status() === PHP_SESSION_NONE) session_start();

// Auth guard
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once '../../../../config/conn.php';
require_once '../../../models/appointmentModel.php';

$db   = new Database();
$conn = $db->connect();
$appointmentModel = new Appointment($conn);

$upcoming = $appointmentModel->getAllUpcomingWithStatus();
$past     = $appointmentModel->getAdminPastAppointments();

$statuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];

function statusClass($status) {
    return 'vd-status vd-status-' . strtolower($status);
}
?>

<div class="d-flex flex-column gap-4">

<!-- ── UPCOMING APPOINTMENTS ── -->
<div class="vd-dash-card">
    <div class="vd-dash-card-header">
    <span class="vd-dash-card-title">Upcoming Appointments</span>
    <span class="vd-topbar-date"><?= count($upcoming) ?> total</span>
    </div>
    <div class="vd-dash-card-body">
    <?php if (empty($upcoming)): ?>
        <div class="vd-empty-state">No upcoming appointments found.</div>
    <?php else: ?>
        <div class="vd-appt-table-wrap">
        <table class="vd-appt-table w-100">
            <thead>
            <tr>
                <th>Patient</th>
                <th>Service</th>
                <th>Clinic</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($upcoming as $appt): ?>
                <tr data-id="<?= $appt['appointment_id'] ?>">
                <td>
                    <div class="vd-appt-name"><?= htmlspecialchars($appt['lastname'] . ', ' . $appt['firstname']) ?></div>
                    <div class="vd-appt-meta"><?= htmlspecialchars($appt['email']) ?></div>
                </td>
                <td class="vd-appt-meta"><?= htmlspecialchars($appt['service']) ?></td>
                <td class="vd-appt-meta"><?= htmlspecialchars($appt['clinic_name']) ?></td>
                <td class="vd-appt-meta"><?= date('M d, Y', strtotime($appt['date'])) ?></td>
                <td><span class="<?= statusClass($appt['status']) ?>"><?= htmlspecialchars($appt['status']) ?></span></td>
                <td>
                    <select class="vd-status-select" data-id="<?= $appt['appointment_id'] ?>">
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $appt['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                    </select>
                </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    </div>
</div>

<!-- ── PAST APPOINTMENTS ── -->
<div class="vd-dash-card">
    <div class="vd-dash-card-header">
    <span class="vd-dash-card-title">Past Appointments</span>
    <span class="vd-topbar-date"><?= count($past) ?> total</span>
    </div>
    <div class="vd-dash-card-body">
    <?php if (empty($past)): ?>
        <div class="vd-empty-state">No past appointments found.</div>
    <?php else: ?>
        <div class="vd-appt-table-wrap">
        <table class="vd-appt-table w-100">
            <thead>
            <tr>
                <th>Patient</th>
                <th>Service</th>
                <th>Clinic</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($past as $appt): ?>
                <tr data-id="<?= $appt['appointment_id'] ?>">
                <td>
                    <div class="vd-appt-name"><?= htmlspecialchars($appt['lastname'] . ', ' . $appt['firstname']) ?></div>
                    <div class="vd-appt-meta"><?= htmlspecialchars($appt['email']) ?></div>
                </td>
                <td class="vd-appt-meta"><?= htmlspecialchars($appt['service']) ?></td>
                <td class="vd-appt-meta"><?= htmlspecialchars($appt['clinic_name']) ?></td>
                <td class="vd-appt-meta"><?= date('M d, Y', strtotime($appt['date'])) ?></td>
                <td><span class="<?= statusClass($appt['status']) ?>"><?= htmlspecialchars($appt['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    </div>
</div>

</div>

<!-- Toast notification -->
<div id="statusToast" class="vd-toast d-none">
    <span id="statusToastMsg"></span>
</div>

<script>
(function () {
    const CONTROLLER = '../../../apps/controllers/appointmentController.php';

    function showToast(msg, success) {
        const toast = document.getElementById('statusToast');
        const msgEl = document.getElementById('statusToastMsg');
        msgEl.textContent = msg;
        toast.classList.remove('d-none', 'vd-toast-success', 'vd-toast-error');
        toast.classList.add(success ? 'vd-toast-success' : 'vd-toast-error');
        setTimeout(() => toast.classList.add('d-none'), 3000);
    }

    function updateStatusPill(row, newStatus) {
        const pill = row.querySelector('.vd-status');
        if (!pill) return;
        pill.className = 'vd-status vd-status-' + newStatus.toLowerCase();
        pill.textContent = newStatus;
    }

    document.querySelectorAll('.vd-status-select').forEach(select => {
        select.addEventListener('change', async function () {
        const appointmentId = this.dataset.id;
        const newStatus     = this.value;
        const row           = this.closest('tr');
        const original      = this.querySelector('option[selected]')?.value || this.dataset.original;

      // Store original for rollback
        if (!this.dataset.original) this.dataset.original = this.querySelector('option[selected]')?.value;

        const formData = new FormData();
        formData.append('action', 'updateStatus');
        formData.append('appointment_id', appointmentId);
        formData.append('status', newStatus);

        try {
            const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
            const result   = await response.json();

        if (result.success) {
            updateStatusPill(row, newStatus);
            this.dataset.original = newStatus;
          // Update selected attribute
            this.querySelectorAll('option').forEach(o => o.removeAttribute('selected'));
            this.querySelector(`option[value="${newStatus}"]`).setAttribute('selected', 'selected');
            showToast('Status updated to ' + newStatus, true);
        } else {
          // Roll back
            this.value = this.dataset.original;
            showToast(result.message || 'Failed to update status.', false);
        }
        } catch (err) {
        this.value = this.dataset.original;
        showToast('Network error. Please try again.', false);
        console.error(err);
        }
    });
    });
})();
</script>