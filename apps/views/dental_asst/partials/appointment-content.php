<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once  __DIR__ . '/../../../../config/conn.php';
require_once  __DIR__ . '/../../../models/appointmentModel.php';

$db   = new Database();
$conn = $db->connect();
$appointmentModel = new Appointment($conn);

$upcoming = $appointmentModel->getAllUpcomingWithStatus();
$past     = $appointmentModel->getAdminPastAppointments();

$statuses = ['Pending', 'Confirmed', 'Cancelled'];

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
                    <td>
                        <span class="<?= statusClass($appt['status']) ?>" id="pill-<?= $appt['appointment_id'] ?>">
                        <?= htmlspecialchars($appt['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="vd-action-group">
                        <select class="vd-status-select"
                            data-id="<?= $appt['appointment_id'] ?>"
                            data-original="<?= htmlspecialchars($appt['status']) ?>"
                            data-email="<?= htmlspecialchars($appt['email']) ?>"
                            data-name="<?= htmlspecialchars($appt['firstname'] . ' ' . $appt['lastname']) ?>">
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $appt['status'] === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn vd-btn-gold btn-sm vd-save-btn"
                            data-id="<?= $appt['appointment_id'] ?>"
                            disabled>
                            Save & Notify
                        </button>
                        </div>
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
                    <td>
                        <span class="<?= statusClass($appt['status']) ?>">
                        <?= htmlspecialchars($appt['status']) ?>
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

<!-- Toast -->
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

    function updateStatusPill(id, newStatus) {
        const pill = document.getElementById('pill-' + id);
        if (!pill) return;
        pill.className = 'vd-status vd-status-' + newStatus.toLowerCase();
        pill.textContent = newStatus;
    }

    // Enable/disable Save & Notify button based on whether status changed
    document.querySelectorAll('.vd-status-select').forEach(select => {
        select.addEventListener('change', function () {
        const row    = this.closest('tr');
        const saveBtn = row.querySelector('.vd-save-btn');
        saveBtn.disabled = this.value === this.dataset.original;
        });
    });

    // Save & Notify button click
    document.querySelectorAll('.vd-save-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
        const id     = this.dataset.id;
        const row    = this.closest('tr');
        const select = row.querySelector('.vd-status-select');
        const newStatus = select.value;
        const email     = select.dataset.email;
        const name      = select.dataset.name;

        btn.disabled  = true;
        btn.textContent = 'Saving…';

        const formData = new FormData();
        formData.append('action', 'updateStatus');
        formData.append('appointment_id', id);
        formData.append('status', newStatus);
        formData.append('email', email);
        formData.append('name', name);

        try {
            const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
            const result   = await response.json();

            if (result.success) {
            updateStatusPill(id, newStatus);
            select.dataset.original = newStatus;
            btn.textContent = 'Save & Notify';
            showToast('Status updated to ' + newStatus, true);
            } else {
            btn.disabled = false;
            btn.textContent = 'Save & Notify';
            showToast(result.message || 'Failed to update.', false);
            }
        } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Save & Notify';
            showToast('Network error. Please try again.', false);
            console.error(err);
        }
        });
    });

})();
</script>