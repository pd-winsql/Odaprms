<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo '<div class="vd-empty-state">Analytics are available to administrators only.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/analyticsModel.php';

$model = new AnalyticsModel((new Database())->connect());
$filters = AnalyticsModel::defaultFilters();
$clinics = $model->getClinics();

function analyticsEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<section class="vd-analytics-page" id="analyticsPage"
    data-endpoint="../../controllers/analyticsController.php">
    <div class="vd-dash-card vd-analytics-controls">
        <div class="vd-dash-card-header">
            <div>
                <span class="vd-dash-card-title">Admin Analytics</span>
                <div class="vd-report-help">Monitor appointment demand, patient growth, clinic activity, and visit outcomes.</div>
            </div>
            <a class="btn vd-btn-outline" id="analyticsExport" href="#">
                <i class="ti ti-file-spreadsheet me-1"></i> Export CSV
            </a>
        </div>

        <form class="vd-analytics-filter-grid" id="analyticsFilterForm">
            <input type="hidden" name="group_by" id="analyticsGroupBy" value="day">
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="analyticsPreset">Date range</label>
                <select class="form-select vd-input" id="analyticsPreset">
                    <option value="month">Current month</option>
                    <option value="30days">Last 30 days</option>
                    <option value="year">Current year</option>
                    <option value="custom">Custom range</option>
                </select>
            </div>
            <div class="vd-analytics-custom-dates d-none" id="analyticsCustomDates">
                <div class="vd-filter-group">
                    <label class="vd-label form-label" for="analyticsDateFrom">From</label>
                    <input class="form-control vd-input" id="analyticsDateFrom" type="date" name="date_from" value="<?= analyticsEscape($filters['date_from']) ?>" required>
                </div>
                <div class="vd-filter-group">
                    <label class="vd-label form-label" for="analyticsDateTo">To</label>
                    <input class="form-control vd-input" id="analyticsDateTo" type="date" name="date_to" value="<?= analyticsEscape($filters['date_to']) ?>" required>
                </div>
            </div>
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="analyticsClinic">Clinic</label>
                <select class="form-select vd-input" id="analyticsClinic" name="clinic_id">
                    <option value="">All clinics</option>
                    <?php foreach ($clinics as $clinic): ?>
                        <option value="<?= (int) $clinic['clinic_id'] ?>"><?= analyticsEscape($clinic['clinic_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vd-analytics-filter-action">
                <button type="submit" class="btn vd-btn-gold" id="analyticsApply">
                    <i class="ti ti-chart-bar me-1"></i> Apply
                </button>
                <button type="button" class="btn vd-btn-outline" id="analyticsReset">Reset</button>
            </div>
        </form>
        <div class="vd-report-alert d-none" id="analyticsError" role="alert"></div>
    </div>

    <div class="vd-analytics-loading" id="analyticsLoading" aria-live="polite">
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <span>Calculating analytics...</span>
    </div>

    <div class="vd-analytics-results d-none" id="analyticsResults">
        <div class="vd-analytics-period" id="analyticsPeriod"></div>

        <div class="vd-stat-grid vd-analytics-kpis">
            <button type="button" class="vd-stat-card vd-analytics-kpi" data-drilldown="appointments">
                <div class="vd-stat-label">Appointments</div>
                <div class="vd-stat-value vd-analytics-skeleton-line" data-kpi="appointments">0</div>
                <div class="vd-stat-sub">Scheduled in this period</div>
                <span class="vd-analytics-open-hint">View records <i class="ti ti-arrow-up-right"></i></span>
            </button>
            <button type="button" class="vd-stat-card vd-analytics-kpi" data-drilldown="completed">
                <div class="vd-stat-label">Completed Visits</div>
                <div class="vd-stat-value vd-analytics-skeleton-line" data-kpi="completed">0</div>
                <div class="vd-stat-sub">Finished appointments</div>
                <span class="vd-analytics-open-hint">View records <i class="ti ti-arrow-up-right"></i></span>
            </button>
            <button type="button" class="vd-stat-card vd-analytics-kpi" data-drilldown="patients">
                <div class="vd-stat-label">New Patients</div>
                <div class="vd-stat-value vd-analytics-skeleton-line" data-kpi="new_patients">0</div>
                <div class="vd-stat-sub">Records created</div>
                <span class="vd-analytics-open-hint">View records <i class="ti ti-arrow-up-right"></i></span>
            </button>
            <button type="button" class="vd-stat-card vd-analytics-kpi" data-drilldown="schedules">
                <div class="vd-stat-label">Utilization</div>
                <div class="vd-stat-value vd-analytics-skeleton-line" data-kpi="utilization_rate">0%</div>
                <div class="vd-stat-sub" data-kpi-sub="utilization">0 of 0 capacity used</div>
                <span class="vd-analytics-open-hint">View schedules <i class="ti ti-arrow-up-right"></i></span>
            </button>
            <button type="button" class="vd-stat-card vd-analytics-kpi" data-drilldown="cancelled">
                <div class="vd-stat-label">Cancellation Rate</div>
                <div class="vd-stat-value vd-analytics-skeleton-line" data-kpi="cancellation_rate">0%</div>
                <div class="vd-stat-sub">Cancelled &divide; accepted requests</div>
                <span class="vd-analytics-open-hint">View records <i class="ti ti-arrow-up-right"></i></span>
            </button>
            <button type="button" class="vd-stat-card vd-analytics-kpi" data-drilldown="no_show">
                <div class="vd-stat-label">No-show Rate</div>
                <div class="vd-stat-value vd-analytics-skeleton-line" data-kpi="no_show_rate">0%</div>
                <div class="vd-stat-sub">No-shows &divide; completed/no-show outcomes</div>
                <span class="vd-analytics-open-hint">View records <i class="ti ti-arrow-up-right"></i></span>
            </button>
        </div>

        <div class="vd-analytics-section-header">
            <div><span class="vd-analytics-section-kicker">Trends over time</span><h2>Activity Timeline</h2><p>Choose how the two timeline charts group records.</p></div>
            <div class="vd-analytics-group-toggle" role="group" aria-label="Trend chart grouping">
                <button type="button" class="active" data-group-by="day">Daily</button>
                <button type="button" data-group-by="month">Monthly</button>
            </div>
        </div>

        <div class="vd-analytics-grid vd-analytics-trend-grid">
            <article class="vd-dash-card vd-analytics-chart">
                <div class="vd-dash-card-header">
                    <div><span class="vd-dash-card-title">Appointment Trend</span><div class="vd-report-help">Appointments by scheduled date</div></div>
                </div>
                <div class="vd-analytics-canvas"><div class="vd-chart-skeleton"></div><canvas id="appointmentTrendChart" aria-label="Appointment trend chart. Select a point to view its appointments."></canvas></div>
                <details class="vd-chart-definition"><summary>How this chart is calculated</summary><p>Counts appointments by their scheduled date. Daily or monthly grouping follows the selected chart grouping.</p></details>
            </article>

            <article class="vd-dash-card vd-analytics-chart">
                <div class="vd-dash-card-header">
                    <div><span class="vd-dash-card-title">New Patient Trend</span><div class="vd-report-help">Patient records created during the period</div></div>
                </div>
                <div class="vd-analytics-canvas"><div class="vd-chart-skeleton"></div><canvas id="patientGrowthChart" aria-label="New patient trend chart. Select a point to view patient records."></canvas></div>
                <details class="vd-chart-definition"><summary>How this chart is calculated</summary><p>Counts patient records by registration date. A clinic filter includes patients associated with that clinic.</p></details>
            </article>
        </div>

        <div class="vd-analytics-section-header vd-analytics-section-header-static">
            <div><span class="vd-analytics-section-kicker">Breakdowns</span><h2>Distribution &amp; Comparison</h2><p>Explore statuses, services, and clinic performance.</p></div>
        </div>

        <div class="vd-analytics-grid">

            <article class="vd-dash-card vd-analytics-chart">
                <div class="vd-dash-card-header">
                    <div><span class="vd-dash-card-title">Status Distribution</span><div class="vd-report-help">Current appointment stages</div></div>
                </div>
                <div class="vd-analytics-canvas"><div class="vd-chart-skeleton"></div><canvas id="statusDistributionChart" aria-label="Appointment status distribution chart. Select a segment to view records."></canvas></div>
                <details class="vd-chart-definition"><summary>How this chart is calculated</summary><p>Each appointment is counted once under its current workflow status within the selected period.</p></details>
            </article>

            <article class="vd-dash-card vd-analytics-chart">
                <div class="vd-dash-card-header">
                    <div><span class="vd-dash-card-title">Most Requested Services</span><div class="vd-report-help">Top six selected services</div></div>
                </div>
                <div class="vd-analytics-canvas"><div class="vd-chart-skeleton"></div><canvas id="topServicesChart" aria-label="Most requested services chart. Select a bar to view records."></canvas></div>
                <details class="vd-chart-definition"><summary>How this chart is calculated</summary><p>Counts appointment-service selections. A multi-service appointment appears once under each selected service.</p></details>
            </article>

            <article class="vd-dash-card vd-analytics-chart vd-analytics-chart-wide">
                <div class="vd-dash-card-header">
                    <div><span class="vd-dash-card-title">Clinic Comparison</span><div class="vd-report-help">Appointments, completed visits, and utilization</div></div>
                </div>
                <div class="vd-analytics-canvas"><div class="vd-chart-skeleton"></div><canvas id="clinicComparisonChart" aria-label="Clinic comparison chart. Select a clinic to view records."></canvas></div>
                <details class="vd-chart-definition"><summary>How this chart is calculated</summary><p>Compares total appointments, completed visits, and active bookings divided by configured capacity for each clinic.</p></details>
            </article>

        </div>

    </div>
</section>

<div class="modal fade" id="analyticsDrilldownModal" tabindex="-1" aria-labelledby="analyticsDrilldownTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div><div class="vd-action-modal-kicker">Analytics details</div><h5 class="modal-title vd-modal-title" id="analyticsDrilldownTitle">Records</h5><p class="text-muted small mb-0" id="analyticsDrilldownPeriod"></p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="vd-analytics-drill-loading d-none" id="analyticsDrilldownLoading"><span class="spinner-border spinner-border-sm"></span> Loading records...</div>
                <div class="vd-report-alert d-none" id="analyticsDrilldownError"></div>
                <div class="vd-appt-table-wrap d-none" id="analyticsDrilldownTableWrap">
                    <table class="w-100 vd-report-table" id="analyticsDrilldownTable"><thead></thead><tbody></tbody></table>
                </div>
                <div class="vd-empty-state d-none" id="analyticsDrilldownEmpty">No matching records were found.</div>
            </div>
            <div class="modal-footer vd-analytics-drill-footer">
                <span class="text-muted small me-auto" id="analyticsDrilldownCount"></span>
                <nav class="vd-analytics-pagination d-none" id="analyticsPagination" aria-label="Analytics detail pages">
                    <button type="button" class="btn vd-btn-outline btn-sm" id="analyticsPagePrevious"><i class="ti ti-chevron-left"></i> Previous</button>
                    <span id="analyticsPageLabel">Page 1 of 1</span>
                    <button type="button" class="btn vd-btn-outline btn-sm" id="analyticsPageNext">Next <i class="ti ti-chevron-right"></i></button>
                </nav>
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
window.AdminAnalytics?.init(document.getElementById('analyticsPage'));
</script>
