<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/appointmentModel.php';
require_once __DIR__ . '/../../../models/clinicModel.php';
require_once __DIR__ . '/../../../models/depositModel.php';
require_once __DIR__ . '/../../../models/logbookModel.php';

$db = new Database();
$conn = $db->connect();
$appointmentModel = new Appointment($conn);
$depositModel = new DepositModel($conn);
$depositModel->expireUnpaidAppointments();
$logbookModel = new LogbookModel($conn);
$upcoming = $appointmentModel->getAllUpcomingWithStatus();
$clinics = (new Clinic($conn))->getAllClinics();
$todayLogbook = $logbookModel->getToday();
$arrivedCount = count(array_filter($todayLogbook, static fn($row) => !empty($row['arrived_at'])));
$readyCount = count(array_filter($todayLogbook, static fn($row) => $row['checkin_status'] === 'Ready'));
$reviewCount = $depositModel->getPendingReviewCount();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

function dashboardStatusClass($status) {
    return 'vd-status vd-status-' . strtolower(str_replace(' ', '-', $status));
}
?>

<div class="d-flex flex-column gap-4">
    <div class="vd-stat-grid">
        <div class="vd-stat-card"><div class="vd-stat-label">Today's Appointments</div><div class="vd-stat-value"><?= count($todayLogbook) ?></div><div class="vd-stat-sub">Across <?= count($clinics) ?> clinics</div></div>
        <div class="vd-stat-card"><div class="vd-stat-label">Arrived</div><div class="vd-stat-value"><?= $arrivedCount ?></div><div class="vd-stat-sub"><?= $readyCount ?> ready</div></div>
        <div class="vd-stat-card"><div class="vd-stat-label">Upcoming</div><div class="vd-stat-value"><?= count($upcoming) ?></div><div class="vd-stat-sub">All active requests</div></div>
        <div class="vd-stat-card"><div class="vd-stat-label">Payments to Review</div><div class="vd-stat-value"><?= $reviewCount ?></div><div class="vd-stat-sub">Manual GCash checking</div></div>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header"><span class="vd-dash-card-title">Today's Logbook</span><span class="vd-topbar-date"><?= date('F j, Y') ?></span></div>
        <div class="vd-dash-card-body">
        <div class="d-flex flex-wrap gap-2 mb-4">
            <input type="text" class="form-control vd-input flex-grow-1" id="checkinLookup" placeholder="Enter appointment code (AVC-XXXXXX) or patient name">
            <button type="button" class="btn vd-btn-gold" id="findCheckinAppointment">Find Appointment</button>
        </div>
        <?php if (!$todayLogbook): ?>
            <div class="vd-empty-state">No confirmed appointments scheduled for today.</div>
        <?php else: ?>
            <div class="vd-appt-table-wrap">
                <table class="vd-appt-table w-100">
                    <thead><tr><th>Patient</th><th>Services</th><th>Clinic</th><th>Arrival</th><th>Profile</th><th>Check-in</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($todayLogbook as $entry): ?>
                        <tr>
                            <td><div class="vd-appt-name"><?= htmlspecialchars($entry['lastname'] . ', ' . $entry['firstname']) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($entry['email']) ?></div></td>
                            <td class="vd-appt-meta"><?= htmlspecialchars($entry['service_name'] ?: '—') ?></td>
                            <td class="vd-appt-meta"><?= htmlspecialchars($entry['clinic_name']) ?></td>
                            <td class="vd-appt-meta"><?= $entry['arrived_at'] ? date('g:i A', strtotime($entry['arrived_at'])) : 'Not arrived' ?></td>
                            <td><span class="<?= dashboardStatusClass($entry['profile_completed_at'] ? 'Complete' : 'Incomplete') ?>"><?= $entry['profile_completed_at'] ? 'Complete' : 'Incomplete' ?></span></td>
                            <td>
                                <?php if ($entry['checkin_status']): ?><span class="<?= dashboardStatusClass($entry['checkin_status']) ?>"><?= htmlspecialchars($entry['checkin_status']) ?></span>
                                <?php else: ?><span class="vd-status vd-status-pending">Not Arrived</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$entry['checkin_id'] && $entry['appointment_status'] === 'Confirmed'): ?>
                                    <span class="text-muted small">Awaiting patient code</span>
                                <?php elseif ($entry['checkin_status'] === 'Profile Required'): ?>
                                    <button type="button" class="btn vd-btn-outline btn-sm" data-complete-profile="<?= (int) $entry['patient_id'] ?>">Complete Patient Form</button>
                                <?php else: ?><span class="text-muted small"><?= htmlspecialchars($entry['checked_in_by'] ?: '—') ?></span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header"><span class="vd-dash-card-title">Upcoming Appointments</span><span class="vd-topbar-date"><?= count($upcoming) ?> total</span></div>
        <div class="vd-dash-card-body">
            <?php if (!$upcoming): ?><div class="vd-empty-state">No upcoming appointment requests.</div>
            <?php else: foreach (array_slice($upcoming, 0, 5) as $appt): ?>
                <div class="vd-appt-row">
                    <div class="vd-appt-date-box"><div class="vd-appt-day"><?= date('d', strtotime($appt['date'])) ?></div><div class="vd-appt-mon"><?= date('M', strtotime($appt['date'])) ?></div></div>
                    <div class="vd-appt-info"><div class="vd-appt-name"><?= htmlspecialchars($appt['lastname'] . ', ' . $appt['firstname']) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($appt['service_name']) ?> · <?= htmlspecialchars($appt['clinic_name']) ?></div></div>
                    <span class="<?= dashboardStatusClass($appt['status']) ?>"><?= htmlspecialchars($appt['status']) ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    document.getElementById('findCheckinAppointment')?.addEventListener('click', async () => {
        const term = document.getElementById('checkinLookup').value.trim();
        if (!term) { window.showToast('Enter the patient appointment code or name.', false); return; }
        const lookupBody = new FormData();
        lookupBody.append('action', 'lookup');
        lookupBody.append('term', term);
        lookupBody.append('csrf_token', csrfToken);
        try {
            const lookupResponse = await fetch('../../controllers/logbookController.php', { method: 'POST', body: lookupBody });
            const lookup = await lookupResponse.json();
            if (!lookup.success || !lookup.matches?.length) throw new Error('No confirmed appointment for today matches that code or name.');
            if (lookup.matches.length > 1) throw new Error('More than one patient matched. Enter the appointment code or a more specific name.');
            const match = lookup.matches[0];
            const summary = `${match.firstname} ${match.lastname}\n${match.service_name || 'Service not listed'}\n${match.clinic_name}`;
            if (!confirm(`Confirm this patient has arrived?\n\n${summary}`)) return;
            const body = new FormData();
            body.append('action', 'checkIn');
            body.append('appointment_id', match.appointment_id);
            body.append('lookup_method', match.lookup_method);
            body.append('csrf_token', csrfToken);
            const response = await fetch('../../controllers/logbookController.php', { method: 'POST', body });
            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'Check-in failed.');
            window.showToast(result.message, true);
            document.querySelector('[data-page="dashboard-content.php"]')?.click();
        } catch (error) { window.showToast(error.message, false); }
    });

    document.querySelectorAll('[data-complete-profile]').forEach(button => {
        button.addEventListener('click', async () => {
            const content = document.querySelector('.vd-dash-content');
            LoadingUI.showContent(content, { label: 'Loading patient form…' });
            try {
                const response = await fetch(`partials/_patient-form.php?id=${button.dataset.completeProfile}`);
                if (!response.ok) throw new Error('Unable to load patient form.');
                content.innerHTML = await response.text();
                content.querySelectorAll('script').forEach(oldScript => {
                    const script = document.createElement('script');
                    script.textContent = oldScript.textContent;
                    document.body.appendChild(script);
                    oldScript.remove();
                });
            } catch (error) { content.innerHTML = `<div class="vd-empty-state">${error.message}</div>`; }
            finally { LoadingUI.finishContent(content); }
        });
    });
})();
</script>
