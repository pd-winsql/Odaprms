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

// Build status + month option lists for each table independently
function buildFilterOptions($rows) {
    $statusesFound = [];
    $months        = [];

    foreach ($rows as $r) {
        $statusesFound[$r['status']] = $r['status'];

        $key   = date('Y-m', strtotime($r['date']));
        $label = date('F Y', strtotime($r['date']));
        $months[$key] = $label;
    }

    ksort($statusesFound);
    ksort($months); // chronological order, for From/To range selects

    return ['statuses' => $statusesFound, 'months' => $months];
}

$upcomingFilters = buildFilterOptions($upcoming);
$pastFilters     = buildFilterOptions($past);

function statusClass($status) {
    return 'vd-status vd-status-' . strtolower($status);
}
?>

<div class="d-flex flex-column gap-4">

    <!-- VIEW TOGGLE (Upcoming / Past) -->
    <div class="vd-view-toggle mb-2">
        <button type="button" class="vd-toggle-btn active" data-view="upcoming">Upcoming</button>
        <button type="button" class="vd-toggle-btn" data-view="past">Past</button>
    </div>

    <!-- ── UPCOMING APPOINTMENTS ── -->
    <div id="upcomingView">
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
            <label class="vd-label form-label">From Month</label>
            <select id="filterMonthFromUpcoming" class="form-select vd-input vd-filter-select">
            <option value="">Any</option>
            <?php foreach ($upcomingFilters['months'] as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
            <?php endforeach; ?>
            </select>
        </div>
        <div class="vd-filter-group">
            <label class="vd-label form-label">To Month</label>
            <select id="filterMonthToUpcoming" class="form-select vd-input vd-filter-select">
            <option value="">Any</option>
            <?php foreach ($upcomingFilters['months'] as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
            <?php endforeach; ?>
            </select>
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
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($upcoming as $appt): ?>
                    <tr data-id="<?= $appt['appointment_id'] ?>"
                        data-status="<?= htmlspecialchars($appt['status']) ?>"
                        data-month="<?= date('Y-m', strtotime($appt['date'])) ?>">
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

    </div>

    <!-- ── PAST APPOINTMENTS ── -->
    <div id="pastView" class="d-none">
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
            <label class="vd-label form-label">From Month</label>
            <select id="filterMonthFromPast" class="form-select vd-input vd-filter-select">
            <option value="">Any</option>
            <?php foreach ($pastFilters['months'] as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
            <?php endforeach; ?>
            </select>
        </div>
        <div class="vd-filter-group">
            <label class="vd-label form-label">To Month</label>
            <select id="filterMonthToPast" class="form-select vd-input vd-filter-select">
            <option value="">Any</option>
            <?php foreach ($pastFilters['months'] as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
            <?php endforeach; ?>
            </select>
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
                </tr>
                </thead>
                <tbody>
                <?php foreach ($past as $appt): ?>
                    <tr data-id="<?= $appt['appointment_id'] ?>"
                        data-status="<?= htmlspecialchars($appt['status']) ?>"
                        data-month="<?= date('Y-m', strtotime($appt['date'])) ?>">
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
        if (typeof window.showToast === 'function') { window.showToast(msg, success); return; }
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

    // ── Generic status + month-range filter, reused for Upcoming and Past tables ──
    // Month keys are 'YYYY-MM' strings, which compare correctly with <= and >= directly.
    function setupTableFilter(tableId, statusSelectId, monthFromId, monthToId, clearBtnId, countLabelId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const statusSelect = document.getElementById(statusSelectId);
        const monthFrom     = document.getElementById(monthFromId);
        const monthTo       = document.getElementById(monthToId);
        const clearBtn       = document.getElementById(clearBtnId);
        const countLabel     = document.getElementById(countLabelId);
        const rows            = table.querySelectorAll('tbody tr');
        const totalCount       = rows.length;

        function applyFilter() {
            const status = statusSelect.value;
            const from   = monthFrom.value;
            const to     = monthTo.value;
            let visible  = 0;

            rows.forEach(row => {
                const rowMonth = row.dataset.month;

                const matchStatus = !status || row.dataset.status === status;
                const matchFrom   = !from   || rowMonth >= from;
                const matchTo     = !to     || rowMonth <= to;

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
        monthFrom.addEventListener('change', applyFilter);
        monthTo.addEventListener('change', applyFilter);

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                statusSelect.value = '';
                monthFrom.value    = '';
                monthTo.value      = '';
                applyFilter();
            });
        }
    }

    setupTableFilter('upcomingApptTable', 'filterStatusUpcoming', 'filterMonthFromUpcoming', 'filterMonthToUpcoming', 'clearUpcomingFilters', 'upcomingCountLabel');
    setupTableFilter('pastApptTable', 'filterStatusPast', 'filterMonthFromPast', 'filterMonthToPast', 'clearPastFilters', 'pastCountLabel');

    // Toggle between Upcoming and Past views (uses same design as services-content)
    const toggleBtns = document.querySelectorAll('.vd-toggle-btn');
    const upcomingView = document.getElementById('upcomingView');
    const pastView = document.getElementById('pastView');
    if (toggleBtns && toggleBtns.length) {
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                toggleBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const view = this.dataset.view;
                if (view === 'upcoming') {
                    upcomingView.classList.remove('d-none');
                    pastView.classList.add('d-none');
                } else {
                    upcomingView.classList.add('d-none');
                    pastView.classList.remove('d-none');
                }
            });
        });
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
            row.dataset.status = newStatus;
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