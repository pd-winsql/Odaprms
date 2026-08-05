<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/logbookModel.php';

$selectedDate = trim($_GET['date'] ?? '');
$validDate = $selectedDate !== '' && DateTime::createFromFormat('Y-m-d', $selectedDate)?->format('Y-m-d') === $selectedDate;
$entries = [];
if ($validDate) {
    $db = new Database();
    $entries = (new LogbookModel($db->connect()))->getForDate($selectedDate);
}
?>

<div class="d-flex flex-column gap-4">
    <div>
        <div class="vd-welcome-greet">HISTORICAL LOGBOOK</div>
        <div class="vd-welcome-name">Daily patient arrivals</div>
        <p class="text-muted small mb-0 mt-2">Select a date before displaying logbook records.</p>
    </div>

    <div class="vd-dash-card">
        <div class="vd-filter-bar">
            <div class="vd-filter-group">
                <label for="historicalLogbookDate" class="vd-label form-label">Logbook date</label>
                <input type="date" id="historicalLogbookDate" class="form-control vd-input vd-filter-select" max="<?= date('Y-m-d') ?>" value="<?= $validDate ? htmlspecialchars($selectedDate) : '' ?>">
            </div>
            <div class="vd-filter-group vd-filter-clear">
                <button type="button" class="btn vd-btn-gold" id="loadHistoricalLogbook">View Logbook</button>
            </div>
        </div>
    </div>

    <?php if (!$validDate): ?>
        <div class="vd-dash-card"><div class="vd-empty-state">Select a date to view logbook records.</div></div>
    <?php else: ?>
        <div class="vd-dash-card">
            <div class="vd-dash-card-header"><span class="vd-dash-card-title"><?= date('F j, Y', strtotime($selectedDate)) ?></span><span class="vd-topbar-date"><?= count($entries) ?> record<?= count($entries) === 1 ? '' : 's' ?></span></div>
            <div class="vd-dash-card-body">
            <?php if (!$entries): ?>
                <div class="vd-empty-state">No logbook records found for this date.</div>
            <?php else: ?>
                <div class="vd-appt-table-wrap">
                    <table class="vd-appt-table w-100">
                        <thead><tr><th>Patient</th><th>Clinic / Services</th><th>Arrival</th><th>Profile at Arrival</th><th>Checked In By</th><th>Outcome</th></tr></thead>
                        <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><div class="vd-appt-name"><?= htmlspecialchars($entry['lastname'] . ', ' . $entry['firstname']) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($entry['email']) ?></div></td>
                                <td><div class="vd-appt-name"><?= htmlspecialchars($entry['clinic_name']) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($entry['service_name'] ?: '—') ?></div></td>
                                <td class="vd-appt-meta"><?= $entry['arrived_at'] ? date('g:i A', strtotime($entry['arrived_at'])) : 'Did not arrive' ?></td>
                                <td class="vd-appt-meta"><?= $entry['checkin_id'] ? ($entry['profile_required_at_arrival'] ? 'Profile required' : 'Complete') : '—' ?></td>
                                <td class="vd-appt-meta"><?= htmlspecialchars($entry['checked_in_by'] ?: '—') ?></td>
                                <td><span class="vd-status vd-status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $entry['appointment_status']))) ?>"><?= htmlspecialchars($entry['appointment_status']) ?></span></td>
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
    document.getElementById('loadHistoricalLogbook')?.addEventListener('click', async () => {
        const date = document.getElementById('historicalLogbookDate').value;
        if (!date) { window.showToast('Select a logbook date.', false); return; }
        const content = document.querySelector('.vd-dash-content');
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
})();
</script>
