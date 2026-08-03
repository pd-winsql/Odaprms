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

// Build status options and date bounds for each table independently.
function buildFilterOptions($rows) {
    $statusesFound = [];
    $dates         = [];

    foreach ($rows as $r) {
        $statusesFound[$r['status']] = $r['status'];

        $dates[] = date('Y-m-d', strtotime($r['date']));
    }

    ksort($statusesFound);
    sort($dates);

    return [
        'statuses' => $statusesFound,
        'minDate' => $dates[0] ?? '',
        'maxDate' => $dates ? $dates[count($dates) - 1] : '',
    ];
}

$upcomingFilters = buildFilterOptions($upcoming);
$pastFilters     = buildFilterOptions($past);

function statusClass($status) {
    return 'vd-status vd-status-' . strtolower($status);
}

function actorInitials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $parts = array_values(array_filter($parts));
    if (!$parts) return '?';

    $first = function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1);
    $lastPart = count($parts) > 1 ? $parts[count($parts) - 1] : '';
    $last = $lastPart !== ''
        ? (function_exists('mb_substr') ? mb_substr($lastPart, 0, 1) : substr($lastPart, 0, 1))
        : '';

    return strtoupper($first . $last);
}
?>

<div class="d-flex flex-column gap-4">

    <!-- ── UPCOMING APPOINTMENTS ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Upcoming Appointments</span>
        <span class="vd-topbar-date" id="upcomingCountLabel"><?= count($upcoming) ?> of <?= count($upcoming) ?> total</span>
        </div>

        <!-- Filter bar -->
        <div class="vd-filter-bar">
        <div class="vd-filter-group">
            <label class="vd-label form-label">Status</label>
            <select id="filterStatusUpcoming" class="form-select vd-input vd-filter-select">
            <option value="">All Statuses</option>
            <?php foreach ($upcomingFilters['statuses'] as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
            </select>
        </div>
        <div class="vd-filter-group">
            <label class="vd-label form-label">Start Date</label>
            <input type="date" id="filterDateFromUpcoming" class="form-control vd-input vd-filter-select"
                min="<?= htmlspecialchars($upcomingFilters['minDate']) ?>"
                max="<?= htmlspecialchars($upcomingFilters['maxDate']) ?>">
        </div>
        <div class="vd-filter-group">
            <label class="vd-label form-label">End Date</label>
            <input type="date" id="filterDateToUpcoming" class="form-control vd-input vd-filter-select"
                min="<?= htmlspecialchars($upcomingFilters['minDate']) ?>"
                max="<?= htmlspecialchars($upcomingFilters['maxDate']) ?>">
        </div>
        <div class="vd-filter-group vd-filter-clear">
            <button id="clearUpcomingFilters" class="btn vd-btn-outline">Clear</button>
        </div>
        </div>

        <div class="vd-dash-card-body">
        <?php if (empty($upcoming)): ?>
            <div class="vd-empty-state">No upcoming appointments found.</div>
        <?php else: ?>
            <div class="vd-appt-table-wrap">
            <table class="vd-appt-table w-100" id="upcomingApptTable">
                <thead>
                <tr>
                    <th>Patient</th>
                    <th>Service</th>
                    <th>Clinic</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Latest Activity</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($upcoming as $appt): ?>
                    <tr data-id="<?= $appt['appointment_id'] ?>"
                        data-status="<?= htmlspecialchars($appt['status']) ?>"
                        data-date="<?= date('Y-m-d', strtotime($appt['date'])) ?>">
                    <td>
                        <div class="vd-appt-name"><?= htmlspecialchars($appt['lastname'] . ', ' . $appt['firstname']) ?></div>
                        <div class="vd-appt-meta"><?= htmlspecialchars($appt['email']) ?></div>
                    </td>
                    <td class="vd-appt-meta"><?= htmlspecialchars($appt['service_name']) ?></td>
                    <td class="vd-appt-meta"><?= htmlspecialchars($appt['clinic_name']) ?></td>
                    <td class="vd-appt-meta"><?= date('M d, Y', strtotime($appt['date'])) ?></td>
                    <td>
                        <span class="<?= statusClass($appt['status']) ?>" id="pill-<?= $appt['appointment_id'] ?>">
                        <?= htmlspecialchars($appt['status']) ?>
                        </span>
                    </td>
                    <td id="audit-<?= $appt['appointment_id'] ?>" class="vd-activity-cell">
                        <?php if (!empty($appt['status_changed_by'])): ?>
                            <div class="vd-activity-card">
                                <span class="vd-activity-avatar"><?= htmlspecialchars(actorInitials($appt['status_changed_by'])) ?></span>
                                <span class="vd-activity-copy">
                                    <span class="vd-activity-heading">
                                        <span class="vd-activity-name"><?= htmlspecialchars($appt['status_changed_by']) ?></span>
                                        <span class="vd-role-chip"><?= htmlspecialchars($appt['status_changed_by_role']) ?></span>
                                    </span>
                                    <span class="vd-activity-meta">
                                        <?= htmlspecialchars($appt['status']) ?> ·
                                        <?= date('M d, Y g:i A', strtotime($appt['status_changed_at'])) ?>
                                    </span>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="vd-activity-card vd-activity-empty">
                                <span class="vd-activity-avatar">—</span>
                                <span class="vd-activity-copy">
                                    <span class="vd-activity-name">No audit history</span>
                                    <span class="vd-activity-meta">No status change recorded</span>
                                </span>
                            </div>
                        <?php endif; ?>
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
        <span class="vd-topbar-date" id="pastCountLabel"><?= count($past) ?> of <?= count($past) ?> total</span>
        </div>

        <!-- Filter bar -->
        <div class="vd-filter-bar">
        <div class="vd-filter-group">
            <label class="vd-label form-label">Status</label>
            <select id="filterStatusPast" class="form-select vd-input vd-filter-select">
            <option value="">All Statuses</option>
            <?php foreach ($pastFilters['statuses'] as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
            </select>
        </div>
        <div class="vd-filter-group">
            <label class="vd-label form-label">Start Date</label>
            <input type="date" id="filterDateFromPast" class="form-control vd-input vd-filter-select"
                min="<?= htmlspecialchars($pastFilters['minDate']) ?>"
                max="<?= htmlspecialchars($pastFilters['maxDate']) ?>">
        </div>
        <div class="vd-filter-group">
            <label class="vd-label form-label">End Date</label>
            <input type="date" id="filterDateToPast" class="form-control vd-input vd-filter-select"
                min="<?= htmlspecialchars($pastFilters['minDate']) ?>"
                max="<?= htmlspecialchars($pastFilters['maxDate']) ?>">
        </div>
        <div class="vd-filter-group vd-filter-clear">
            <button id="clearPastFilters" class="btn vd-btn-outline">Clear</button>
        </div>
        </div>

        <div class="vd-dash-card-body">
        <?php if (empty($past)): ?>
            <div class="vd-empty-state">No past appointments found.</div>
        <?php else: ?>
            <div class="vd-appt-table-wrap">
            <table class="vd-appt-table w-100" id="pastApptTable">
                <thead>
                <tr>
                    <th>Patient</th>
                    <th>Service</th>
                    <th>Clinic</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Latest Activity</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($past as $appt): ?>
                    <tr data-id="<?= $appt['appointment_id'] ?>"
                        data-status="<?= htmlspecialchars($appt['status']) ?>"
                        data-date="<?= date('Y-m-d', strtotime($appt['date'])) ?>">
                    <td>
                        <div class="vd-appt-name"><?= htmlspecialchars($appt['lastname'] . ', ' . $appt['firstname']) ?></div>
                        <div class="vd-appt-meta"><?= htmlspecialchars($appt['email']) ?></div>
                    </td>
                    <td class="vd-appt-meta"><?= htmlspecialchars($appt['service_name']) ?></td>
                    <td class="vd-appt-meta"><?= htmlspecialchars($appt['clinic_name']) ?></td>
                    <td class="vd-appt-meta"><?= date('M d, Y', strtotime($appt['date'])) ?></td>
                    <td>
                        <span class="<?= statusClass($appt['status']) ?>">
                        <?= htmlspecialchars($appt['status']) ?>
                        </span>
                    </td>
                    <td class="vd-activity-cell">
                        <?php if (!empty($appt['status_changed_by'])): ?>
                            <div class="vd-activity-card">
                                <span class="vd-activity-avatar"><?= htmlspecialchars(actorInitials($appt['status_changed_by'])) ?></span>
                                <span class="vd-activity-copy">
                                    <span class="vd-activity-heading">
                                        <span class="vd-activity-name"><?= htmlspecialchars($appt['status_changed_by']) ?></span>
                                        <span class="vd-role-chip"><?= htmlspecialchars($appt['status_changed_by_role']) ?></span>
                                    </span>
                                    <span class="vd-activity-meta">
                                        <?= htmlspecialchars($appt['status']) ?> ·
                                        <?= date('M d, Y g:i A', strtotime($appt['status_changed_at'])) ?>
                                    </span>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="vd-activity-card vd-activity-empty">
                                <span class="vd-activity-avatar">—</span>
                                <span class="vd-activity-copy">
                                    <span class="vd-activity-name">No audit history</span>
                                    <span class="vd-activity-meta">No status change recorded</span>
                                </span>
                            </div>
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

</div>

<script>
(function () {
    const CONTROLLER = '../../../apps/controllers/appointmentController.php';

    function showToast(msg, success) {
        if (typeof window.showToast === 'function') { window.showToast(msg, success); return; }
        console.warn('showToast not available:', msg);
    }

    function updateStatusPill(id, newStatus) {
        const pill = document.getElementById('pill-' + id);
        if (!pill) return;
        pill.className = 'vd-status vd-status-' + newStatus.toLowerCase();
        pill.textContent = newStatus;
    }

    function updateStatusAudit(id, audit) {
        const cell = document.getElementById('audit-' + id);
        if (!cell || !audit) return;
        renderActivityCard(cell, audit, document.getElementById('pill-' + id)?.textContent.trim() || 'Updated');
    }

    function formatDateTime(value, dateOnly = false) {
        if (!value) return 'Date unavailable';
        const parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return value;
        return dateOnly
            ? parsed.toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' })
            : parsed.toLocaleString([], { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function getInitials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
    }

    function renderActivityCard(cell, audit, status) {
        const card = document.createElement('div');
        card.className = 'vd-activity-card';

        const avatar = document.createElement('span');
        avatar.className = 'vd-activity-avatar';
        avatar.textContent = getInitials(audit.performed_by_name);

        const copy = document.createElement('span');
        copy.className = 'vd-activity-copy';
        const heading = document.createElement('span');
        heading.className = 'vd-activity-heading';
        const name = document.createElement('span');
        name.className = 'vd-activity-name';
        name.textContent = audit.performed_by_name;
        const role = document.createElement('span');
        role.className = 'vd-role-chip';
        role.textContent = audit.performed_by_role;
        const meta = document.createElement('span');
        meta.className = 'vd-activity-meta';
        meta.textContent = status + ' · ' + formatDateTime(audit.performed_at);
        heading.append(name, role);
        copy.append(heading, meta);

        card.append(avatar, copy);
        cell.replaceChildren(card);
    }

    // ── Generic status + day-range filter, reused for Upcoming and Past tables ──
    // Date keys are 'YYYY-MM-DD' strings, which compare correctly with <= and >= directly.
    function setupTableFilter(tableId, statusSelectId, dateFromId, dateToId, clearBtnId, countLabelId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const statusSelect = document.getElementById(statusSelectId);
        const dateFrom      = document.getElementById(dateFromId);
        const dateTo        = document.getElementById(dateToId);
        const clearBtn       = document.getElementById(clearBtnId);
        const countLabel     = document.getElementById(countLabelId);
        const rows            = table.querySelectorAll('tbody tr');
        const totalCount       = rows.length;

        function applyFilter() {
            const status = statusSelect.value;
            const from   = dateFrom.value;
            const to     = dateTo.value;
            let visible  = 0;

            rows.forEach(row => {
                const rowDate = row.dataset.date;

                const matchStatus = !status || row.dataset.status === status;
                const matchFrom   = !from || rowDate >= from;
                const matchTo     = !to || rowDate <= to;

                if (matchStatus && matchFrom && matchTo) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (countLabel) {
                countLabel.textContent = `${visible} of ${totalCount} total`;
            }
        }

        statusSelect.addEventListener('change', applyFilter);
        dateFrom.addEventListener('change', applyFilter);
        dateTo.addEventListener('change', applyFilter);

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                statusSelect.value = '';
                dateFrom.value    = '';
                dateTo.value      = '';
                applyFilter();
            });
        }
    }

    setupTableFilter('upcomingApptTable', 'filterStatusUpcoming', 'filterDateFromUpcoming', 'filterDateToUpcoming', 'clearUpcomingFilters', 'upcomingCountLabel');
    setupTableFilter('pastApptTable', 'filterStatusPast', 'filterDateFromPast', 'filterDateToPast', 'clearPastFilters', 'pastCountLabel');

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

        LoadingUI.setButton(btn, true, 'Saving…');
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
            updateStatusAudit(id, result.audit);
            row.dataset.status = newStatus;
            select.dataset.original = newStatus;
            LoadingUI.setButton(btn, false);
            btn.disabled = true;
            showToast('Status updated to ' + newStatus, true);
            } else {
            LoadingUI.setButton(btn, false);
            showToast(result.message || 'Failed to update.', false);
            }
        } catch (err) {
            LoadingUI.setButton(btn, false);
            showToast('Network error. Please try again.', false);
            console.error(err);
        }
        });
    });

})();
</script>
