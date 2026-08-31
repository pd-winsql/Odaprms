<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo '<div class="vd-empty-state">Reports are available to administrators only.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/reportModel.php';
require_once __DIR__ . '/../../../helpers/siteBranding.php';

function reportEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$error = '';
try {
    $filters = ReportModel::normalizeFilters($_GET);
} catch (InvalidArgumentException $e) {
    $filters = ReportModel::defaultFilters();
    $error = $e->getMessage();
}

$db = new Database();
$conn = $db->connect();
if (!$conn) {
    echo '<div class="vd-empty-state">The report database is unavailable.</div>';
    exit;
}
$branding = vdLoadSiteBranding($conn);

$reportModel = new ReportModel($conn);
$clinics = $reportModel->getClinics();
$services = $reportModel->getServices();
$isUtilization = $filters['report_type'] === 'utilization';
$rows = $isUtilization
    ? $reportModel->getClinicUtilizationReport($filters)
    : $reportModel->getAppointmentReport($filters);

$clinicLabel = 'All clinics';
foreach ($clinics as $clinic) {
    if ((int) $clinic['clinic_id'] === (int) $filters['clinic_id']) {
        $clinicLabel = $clinic['clinic_name'];
        break;
    }
}

$reportTitle = $isUtilization ? 'Clinic Utilization Report' : 'Appointment Report';
$periodLabel = date('M j, Y', strtotime($filters['date_from'])) . ' - ' . date('M j, Y', strtotime($filters['date_to']));

if ($isUtilization) {
    $summary = [
        'scheduled_days' => array_sum(array_column($rows, 'scheduled_days')),
        'capacity'       => array_sum(array_column($rows, 'capacity')),
        'booked'         => array_sum(array_column($rows, 'booked')),
        'available'      => array_sum(array_column($rows, 'available_slots')),
    ];
    $summary['rate'] = $summary['capacity'] > 0
        ? round(($summary['booked'] / $summary['capacity']) * 100, 1)
        : 0;
} else {
    $statuses = array_count_values(array_column($rows, 'status'));
    $summary = [
        'total'     => count($rows),
        'completed' => $statuses['Completed'] ?? 0,
        'pending'   => $statuses['Pending'] ?? 0,
        'cancelled' => ($statuses['Cancelled'] ?? 0) + ($statuses['Rejected'] ?? 0),
    ];
}

$exportQuery = http_build_query(array_merge($filters, ['action' => 'export_csv']));
?>

<section class="vd-report-page" id="reportPage">
    <div class="vd-report-print-header">
        <?= vdRenderSiteBranding($branding, '../../../public/assets', 'report') ?>
        <h1><?= reportEscape($reportTitle) ?></h1>
        <div><?= reportEscape($periodLabel) ?> &middot; <?= reportEscape($clinicLabel) ?></div>
    </div>

    <div class="vd-dash-card vd-report-controls">
        <div class="vd-dash-card-header">
            <div>
                <span class="vd-dash-card-title">Reports &amp; Export</span>
                <div class="vd-report-help">Generate operational summaries from appointments and schedules.</div>
            </div>
            <div class="vd-report-actions">
                <a class="btn vd-btn-outline" href="../../../apps/controllers/reportController.php?<?= reportEscape($exportQuery) ?>">
                    <i class="ti ti-file-spreadsheet me-1"></i> Export CSV
                </a>
                <button type="button" class="btn vd-btn-gold" id="printReportBtn">
                    <i class="ti ti-printer me-1"></i> Print / Save as PDF
                </button>
            </div>
        </div>

        <form class="vd-report-filter-grid" id="reportFilterForm">
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="reportType">Report Type</label>
                <select class="form-select vd-input" id="reportType" name="report_type">
                    <option value="appointments" <?= !$isUtilization ? 'selected' : '' ?>>Appointments</option>
                    <option value="utilization" <?= $isUtilization ? 'selected' : '' ?>>Clinic Utilization</option>
                </select>
            </div>
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="reportDateFrom">From</label>
                <input class="form-control vd-input" id="reportDateFrom" type="date" name="date_from" value="<?= reportEscape($filters['date_from']) ?>" required>
            </div>
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="reportDateTo">To</label>
                <input class="form-control vd-input" id="reportDateTo" type="date" name="date_to" value="<?= reportEscape($filters['date_to']) ?>" required>
            </div>
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="reportClinic">Clinic</label>
                <select class="form-select vd-input" id="reportClinic" name="clinic_id">
                    <option value="">All clinics</option>
                    <?php foreach ($clinics as $clinic): ?>
                        <option value="<?= (int) $clinic['clinic_id'] ?>" <?= (int) $filters['clinic_id'] === (int) $clinic['clinic_id'] ? 'selected' : '' ?>>
                            <?= reportEscape($clinic['clinic_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vd-filter-group vd-appointment-filter <?= $isUtilization ? 'd-none' : '' ?>">
                <label class="vd-label form-label" for="reportService">Service</label>
                <select class="form-select vd-input" id="reportService" name="service_id" <?= $isUtilization ? 'disabled' : '' ?>>
                    <option value="">All services</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= (int) $service['service_id'] ?>" <?= (int) $filters['service_id'] === (int) $service['service_id'] ? 'selected' : '' ?>>
                            <?= reportEscape($service['service_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vd-filter-group vd-appointment-filter <?= $isUtilization ? 'd-none' : '' ?>">
                <label class="vd-label form-label" for="reportStatus">Status</label>
                <select class="form-select vd-input" id="reportStatus" name="status" <?= $isUtilization ? 'disabled' : '' ?>>
                    <option value="">All statuses</option>
                    <?php foreach (['Pending', 'Confirmed', 'Completed', 'Cancelled', 'Rejected'] as $status): ?>
                        <option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vd-report-filter-actions">
                <button type="submit" class="btn vd-btn-gold"><i class="ti ti-filter me-1"></i> Generate</button>
                <button type="button" class="btn vd-btn-outline vd-filter-reset" id="clearReportFilters">Reset</button>
            </div>
        </form>
        <?php if ($error): ?>
            <div class="vd-report-alert"><?= reportEscape($error) ?></div>
        <?php endif; ?>
    </div>

    <div class="vd-report-meta">
        <div>
            <h2><?= reportEscape($reportTitle) ?></h2>
            <span><?= reportEscape($periodLabel) ?> &middot; <?= reportEscape($clinicLabel) ?></span>
        </div>
        <span>Generated <?= date('M j, Y g:i A') ?></span>
    </div>

    <?php if ($isUtilization): ?>
        <div class="vd-stat-grid vd-report-summary">
            <div class="vd-stat-card"><div class="vd-stat-label">Scheduled Days</div><div class="vd-stat-value"><?= (int) $summary['scheduled_days'] ?></div><div class="vd-stat-sub">Across selected clinics</div></div>
            <div class="vd-stat-card"><div class="vd-stat-label">Total Capacity</div><div class="vd-stat-value"><?= (int) $summary['capacity'] ?></div><div class="vd-stat-sub">Available appointment capacity</div></div>
            <div class="vd-stat-card"><div class="vd-stat-label">Booked</div><div class="vd-stat-value"><?= (int) $summary['booked'] ?></div><div class="vd-stat-sub"><?= reportEscape($summary['rate']) ?>% utilization</div></div>
            <div class="vd-stat-card"><div class="vd-stat-label">Open Slots</div><div class="vd-stat-value"><?= (int) $summary['available'] ?></div><div class="vd-stat-sub">Remaining capacity</div></div>
        </div>
    <?php else: ?>
        <div class="vd-stat-grid vd-report-summary">
            <div class="vd-stat-card"><div class="vd-stat-label">Appointments</div><div class="vd-stat-value"><?= (int) $summary['total'] ?></div><div class="vd-stat-sub">Matching selected filters</div></div>
            <div class="vd-stat-card"><div class="vd-stat-label">Completed</div><div class="vd-stat-value"><?= (int) $summary['completed'] ?></div><div class="vd-stat-sub">Finished appointments</div></div>
            <div class="vd-stat-card"><div class="vd-stat-label">Pending</div><div class="vd-stat-value"><?= (int) $summary['pending'] ?></div><div class="vd-stat-sub">Awaiting confirmation</div></div>
            <div class="vd-stat-card"><div class="vd-stat-label">Cancelled</div><div class="vd-stat-value"><?= (int) $summary['cancelled'] ?></div><div class="vd-stat-sub">Cancelled or rejected</div></div>
        </div>
    <?php endif; ?>

    <div class="vd-dash-card vd-report-results">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Report Preview</span>
            <span class="vd-topbar-date"><?= count($rows) ?> <?= count($rows) === 1 ? 'row' : 'rows' ?></span>
        </div>
        <div class="vd-dash-card-body">
            <?php if (empty($rows)): ?>
                <div class="vd-empty-state">No records match the selected filters.</div>
            <?php else: ?>
                <div class="vd-appt-table-wrap">
                    <table class="w-100 vd-report-table">
                        <?php if ($isUtilization): ?>
                            <thead><tr><th>Clinic</th><th>Scheduled Days</th><th>Capacity</th><th>Booked</th><th>Open Slots</th><th>Completed</th><th>Cancelled</th><th>Utilization</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><div class="vd-appt-name"><?= reportEscape($row['clinic_name']) ?></div></td>
                                    <td><?= (int) $row['scheduled_days'] ?></td><td><?= (int) $row['capacity'] ?></td><td><?= (int) $row['booked'] ?></td>
                                    <td><?= (int) $row['available_slots'] ?></td><td><?= (int) $row['completed'] ?></td><td><?= (int) $row['cancelled'] ?></td>
                                    <td><strong><?= reportEscape($row['utilization_rate']) ?>%</strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        <?php else: ?>
                            <thead><tr><th>Schedule</th><th>Patient</th><th>Service</th><th>Clinic</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($row['date'])) ?><div class="vd-appt-meta"><?= date('g:i A', strtotime($row['start_time'])) ?>–<?= date('g:i A', strtotime($row['end_time'])) ?></div></td>
                                    <td><div class="vd-appt-name"><?= reportEscape($row['patient_name']) ?></div><div class="vd-appt-meta">#<?= (int) $row['appointment_id'] ?></div></td>
                                    <td><?= reportEscape($row['service_name']) ?></td>
                                    <td><?= reportEscape($row['clinic_name']) ?></td>
                                    <td><span class="vd-status vd-status-<?= strtolower(reportEscape($row['status'])) ?>"><?= reportEscape($row['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        <?php endif; ?>
                    </table>
                </div>
                <nav class="vd-table-pagination vd-report-pagination" id="reportPagination" aria-label="Report table pagination">
                    <div class="vd-report-pagination-status">
                        <span class="vd-table-pagination-summary" id="reportPaginationSummary" aria-live="polite"></span>
                        <label class="vd-report-page-size" for="reportPageSize">
                            Rows per page
                            <select id="reportPageSize" aria-label="Rows per page">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </label>
                    </div>
                    <div class="vd-table-pagination-controls">
                        <button type="button" class="btn vd-table-page-btn" id="reportPreviousPage">
                            <i class="ti ti-chevron-left" aria-hidden="true"></i><span>Previous</span>
                        </button>
                        <span class="vd-table-page-label" id="reportPageLabel" aria-live="polite"></span>
                        <button type="button" class="btn vd-table-page-btn" id="reportNextPage">
                            <span>Next</span><i class="ti ti-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <div class="vd-report-print-footer">Generated by <?= reportEscape($_SESSION['display_name'] ?? $_SESSION['email'] ?? 'Administrator') ?> on <?= date('M j, Y g:i A') ?></div>
</section>

<script>
(function () {
    const form = document.getElementById('reportFilterForm');
    const type = document.getElementById('reportType');
    const appointmentFilters = document.querySelectorAll('.vd-appointment-filter');
    const reportTable = document.querySelector('.vd-report-table');
    const reportPagination = document.getElementById('reportPagination');

    function updateFilterVisibility() {
        const hide = type.value === 'utilization';
        appointmentFilters.forEach(group => {
            group.classList.toggle('d-none', hide);
            group.querySelectorAll('select, input').forEach(input => input.disabled = hide);
        });
    }

    type.addEventListener('change', updateFilterVisibility);
    updateFilterVisibility();

    if (reportTable && reportPagination) {
        const rows = Array.from(reportTable.querySelectorAll('tbody tr'));
        const tableWrap = reportTable.closest('.vd-appt-table-wrap');
        const pageSizeSelect = document.getElementById('reportPageSize');
        const summary = document.getElementById('reportPaginationSummary');
        const pageLabel = document.getElementById('reportPageLabel');
        const previous = document.getElementById('reportPreviousPage');
        const next = document.getElementById('reportNextPage');
        let currentPage = 1;

        function renderReportPage() {
            const pageSize = Number.parseInt(pageSizeSelect.value, 10);
            const totalRows = rows.length;
            const totalPages = Math.max(1, Math.ceil(totalRows / pageSize));
            currentPage = Math.min(currentPage, totalPages);

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, totalRows);

            rows.forEach((row, index) => {
                row.classList.toggle('vd-report-row-hidden', index < startIndex || index >= endIndex);
            });

            summary.textContent = `Showing ${totalRows ? startIndex + 1 : 0}–${endIndex} of ${totalRows}`;
            pageLabel.textContent = `Page ${currentPage} of ${totalPages}`;
            previous.disabled = currentPage === 1;
            next.disabled = currentPage === totalPages;
        }

        previous.addEventListener('click', function () {
            if (currentPage === 1) return;
            currentPage--;
            renderReportPage();
            tableWrap?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        next.addEventListener('click', function () {
            const pageSize = Number.parseInt(pageSizeSelect.value, 10);
            const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
            if (currentPage === totalPages) return;
            currentPage++;
            renderReportPage();
            tableWrap?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        pageSizeSelect.addEventListener('change', function () {
            currentPage = 1;
            renderReportPage();
        });

        renderReportPage();
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        loadpage('reports-content.php?' + params.toString());
    });

    document.getElementById('clearReportFilters').addEventListener('click', function () {
        loadpage('reports-content.php');
    });

    document.getElementById('printReportBtn').addEventListener('click', function () {
        window.print();
    });
})();
</script>
