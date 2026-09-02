<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/logbookModel.php';

$selectedDate = trim($_GET['date'] ?? '');
$validDate = $selectedDate !== '' && DateTime::createFromFormat('Y-m-d', $selectedDate)?->format('Y-m-d') === $selectedDate;
$db = new Database();
$logbookModel = new LogbookModel($db->connect());
$recordDates = $logbookModel->getRecordDates();
$hasSelectedRecordDate = $validDate && in_array($selectedDate, $recordDates, true);
$isToday = $hasSelectedRecordDate && $selectedDate === date('Y-m-d');
$entries = [];
if ($hasSelectedRecordDate) {
    $entries = $logbookModel->getForDate($selectedDate);
}
?>

<div class="d-flex flex-column gap-4">
    <div>
        <div class="vd-welcome-greet">HISTORICAL LOGBOOK</div>
        <div class="vd-welcome-name">Daily patient arrivals</div>
        <p class="text-muted small mb-0 mt-2">Only dates with logbook records can be selected.</p>
    </div>

    <div class="vd-dash-card">
        <div class="vd-filter-bar">
            <div class="vd-filter-group">
                <label for="historicalLogbookDate" class="vd-label form-label">Logbook date</label>
                <input type="text" id="historicalLogbookDateValue" class="form-control vd-input vd-filter-select" value="<?= $hasSelectedRecordDate ? htmlspecialchars($selectedDate) : '' ?>" placeholder="Select a date" autocomplete="off" readonly>
            </div>
            <div class="vd-filter-group vd-filter-clear">
                <button type="button" class="btn vd-btn-gold" id="loadHistoricalLogbook" <?= $recordDates ? '' : 'disabled' ?>>View Logbook</button>
            </div>
        </div>
    </div>

    <?php if (!$hasSelectedRecordDate): ?>
        <div class="vd-dash-card"><div class="vd-empty-state"><?= $recordDates ? 'Select a date to view logbook records.' : 'No logbook records are available yet.' ?></div></div>
    <?php else: ?>
        <div class="vd-dash-card">
            <div class="vd-dash-card-header"><span class="vd-dash-card-title"><?= date('F j, Y', strtotime($selectedDate)) ?></span><span class="vd-topbar-date"><?= count($entries) ?> record<?= count($entries) === 1 ? '' : 's' ?></span></div>
            <div class="vd-dash-card-body">
            <?php if (!$entries): ?>
                <div class="vd-empty-state">No logbook records found for this date.</div>
            <?php else: ?>
                <div class="vd-appt-table-wrap">
                    <table class="vd-appt-table w-100">
                        <thead><tr><th>Queue</th><th>Patient</th><th>Clinic / Services</th><th>Arrival</th><th>Profile at Arrival</th><th>Checked In By</th><th>Outcome</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td>
                                    <?php if ($entry['is_in_treatment']): ?><span class="vd-queue-badge vd-queue-now">Now</span>
                                    <?php elseif ($entry['is_next']): ?><span class="vd-queue-badge vd-queue-next">Next</span>
                                    <?php elseif ($entry['queue_position'] !== null): ?><span class="vd-queue-badge">#<?= (int) $entry['queue_position'] ?></span>
                                    <?php elseif ($entry['queue_status'] === 'On Hold' && $entry['appointment_status'] === 'Checked In'): ?><span class="vd-queue-badge vd-queue-hold">On hold</span>
                                    <?php elseif ($entry['checkin_status'] === 'Profile Required'): ?><span class="vd-queue-badge vd-queue-blocked">Not ready</span>
                                    <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                                </td>
                                <td><div class="vd-appt-name"><?= htmlspecialchars($entry['lastname'] . ', ' . $entry['firstname']) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($entry['email']) ?></div></td>
                                <td><div class="vd-appt-name"><?= htmlspecialchars($entry['clinic_name']) ?></div><div class="vd-appt-meta"><?= date('g:i A', strtotime($entry['start_time'])) ?>–<?= date('g:i A', strtotime($entry['end_time'])) ?> · <?= htmlspecialchars($entry['service_name'] ?: '—') ?></div></td>
                                <td class="vd-appt-meta"><?= $entry['arrived_at'] ? date('g:i A', strtotime($entry['arrived_at'])) : 'Did not arrive' ?></td>
                                <td class="vd-appt-meta"><?= $entry['checkin_id'] ? ($entry['profile_required_at_arrival'] ? 'Profile required' : 'Complete') : '—' ?></td>
                                <td class="vd-appt-meta"><?= htmlspecialchars($entry['checked_in_by'] ?: '—') ?></td>
                                <td><span class="vd-status vd-status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $entry['appointment_status']))) ?>"><?= htmlspecialchars($entry['appointment_status']) ?></span></td>
                                <td>
                                    <?php if ($isToday && $entry['appointment_status'] === 'Checked In' && $entry['checkin_status'] === 'Ready' && $entry['is_next']): ?>
                                        <button type="button" class="btn vd-btn-gold btn-sm" data-visit-status="In Progress" data-appointment-id="<?= (int) $entry['appointment_id'] ?>">
                                            <i class="ti ti-player-play me-1"></i>Start Next
                                        </button>
                                    <?php elseif ($isToday && $entry['appointment_status'] === 'Checked In'): ?>
                                        <button type="button" class="btn vd-btn-outline btn-sm" data-open-today-queue>Manage Queue</button>
                                    <?php elseif ($isToday && $entry['appointment_status'] === 'In Progress'): ?>
                                        <button type="button" class="btn vd-btn-gold btn-sm" data-open-today-queue>
                                            <i class="ti ti-cash-check me-1"></i>Open Final Billing
                                        </button>
                                    <?php elseif ($entry['appointment_status'] === 'Completed'): ?>
                                        <span class="vd-appt-meta">Visit completed</span>
                                    <?php else: ?>
                                        <span class="vd-appt-meta">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const recordDates = <?= json_encode($recordDates) ?>;
    const dateInput = document.getElementById('historicalLogbookDateValue');
    let datePicker = null;

    if (dateInput && typeof flatpickr === 'function') {
        datePicker = flatpickr(dateInput, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'F j, Y',
            allowInput: false,
            disableMobile: true,
            enable: recordDates,
            defaultDate: <?= $hasSelectedRecordDate ? json_encode($selectedDate) : 'null' ?>,
            onReady(selectedDates, dateString, instance) {
                instance.calendarContainer.classList.add('vd-schedule-calendar');
                if (instance.altInput) instance.altInput.id = 'historicalLogbookDate';
                if (selectedDates.length === 0 && recordDates.length > 0) {
                    instance.jumpToDate(recordDates[recordDates.length - 1]);
                }
            }
        });
    }

    document.getElementById('loadHistoricalLogbook')?.addEventListener('click', async () => {
        const date = datePicker?.input.value || dateInput?.value || '';
        if (!date) { window.showToast('Select a logbook date.', false); return; }
        const content = document.querySelector('.vd-dash-content');
        datePicker?.destroy();
        datePicker = null;
        LoadingUI.showContent(content, { label: 'Loading logbook…' });
        try {
            const response = await fetch(`partials/logbook-content.php?date=${encodeURIComponent(date)}`);
            if (!response.ok) throw new Error('Unable to load logbook.');
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

    document.querySelectorAll('[data-visit-status]').forEach(button => {
        button.addEventListener('click', async () => {
            const status = button.dataset.visitStatus;
            const appointmentId = button.dataset.appointmentId;
            const label = status === 'In Progress' ? 'start treatment' : 'complete this visit';

            if (typeof window.showActionModal === 'function') {
                const confirmation = await window.showActionModal({
                    title: status === 'In Progress' ? 'Start Treatment' : 'Complete Visit',
                    kicker: 'Appointment workflow',
                    message: `Are you sure you want to ${label}?`,
                    confirmText: status === 'In Progress' ? 'Start Treatment' : 'Complete Visit',
                    icon: status === 'In Progress' ? 'ti-player-play' : 'ti-check',
                    tone: 'primary'
                });
                if (!confirmation.confirmed) return;
            }

            const body = new FormData();
            body.append('action', 'updateVisitStatus');
            body.append('csrf_token', <?= json_encode($_SESSION['csrf_token']) ?>);
            body.append('appointment_id', appointmentId);
            body.append('status', status);

            LoadingUI.setButton(button, true, status === 'In Progress' ? 'Starting…' : 'Completing…');
            try {
                const response = await fetch('../../controllers/logbookController.php', { method: 'POST', body });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to update the visit.');
                window.showToast(result.message || `Visit updated to ${status}.`, true);
                document.getElementById('loadHistoricalLogbook')?.click();
            } catch (error) {
                window.showToast(error.message || 'Unable to update the visit.', false);
                LoadingUI.setButton(button, false);
            }
        });
    });
    document.querySelectorAll('[data-open-today-queue]').forEach(button => button.addEventListener('click', () => {
        document.querySelector('[data-page="dashboard-content.php"]')?.click();
    }));
})();
</script>
