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
            <label class="vd-label form-label">From Day</label>
            <input type="date" id="filterDateFromUpcoming" class="form-control vd-input vd-filter-select"
                min="<?= htmlspecialchars($upcomingFilters['minDate']) ?>"
                max="<?= htmlspecialchars($upcomingFilters['maxDate']) ?>">
        </div>
        <div class="vd-filter-group">
            <label class="vd-label form-label">To Day</label>
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
            <label class="vd-label form-label">From Day</label>
            <input type="date" id="filterDateFromPast" class="form-control vd-input vd-filter-select"
                min="<?= htmlspecialchars($pastFilters['minDate']) ?>"
                max="<?= htmlspecialchars($pastFilters['maxDate']) ?>">
        </div>
        <div class="vd-filter-group">
            <label class="vd-label form-label">To Day</label>
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
