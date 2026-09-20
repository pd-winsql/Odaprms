<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin' && ($_SESSION['user_role'] ?? '') !== 'Dental Assistant') {
    http_response_code(403);
    echo '<div class="vd-empty-state">Billing insights are available to administrators only.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/billingModel.php';

$model = new BillingModel((new Database())->connect());
$clinics = $model->getBillingInsightClinics();
$defaultFilters = BillingModel::normalizeInsightFilters([]);

function billingInsightEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<section class="vd-billing-insights" id="billingInsightsPage"
    data-endpoint="<?= htmlspecialchars(vdAppUrl('apps/controllers/billingInsightsController.php'), ENT_QUOTES, 'UTF-8') ?>">
    <div class="vd-dash-card vd-billing-controls">
        <div class="vd-dash-card-header">
            <div>
                <span class="vd-dash-card-title">Revenue &amp; Settlements</span>
                <div class="vd-report-help">Track finalized collections, refunds, and clinic contribution by settlement date.</div>
            </div>
            <span class="vd-billing-period" id="billingPeriodLabel">Current month</span>
        </div>
        <form class="vd-billing-filter-grid" id="billingFilterForm">
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="billingPeriod">Period</label>
                <select class="form-select vd-input" id="billingPeriod" name="period">
                    <option value="month">This month</option>
                    <option value="30days">Last 30 days</option>
                    <option value="year">This year</option>
                    <option value="all">All time</option>
                    <option value="custom">Custom range</option>
                </select>
            </div>
            <div class="vd-billing-custom-dates d-none" id="billingCustomDates">
                <div class="vd-filter-group">
                    <label class="vd-label form-label" for="billingDateFrom">From</label>
                    <input class="form-control vd-input" type="date" id="billingDateFrom" name="date_from"
                        value="<?= billingInsightEscape($defaultFilters['date_from']) ?>">
                </div>
                <div class="vd-filter-group">
                    <label class="vd-label form-label" for="billingDateTo">To</label>
                    <input class="form-control vd-input" type="date" id="billingDateTo" name="date_to"
                        value="<?= billingInsightEscape($defaultFilters['date_to']) ?>">
                </div>
            </div>
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="billingClinic">Clinic</label>
                <select class="form-select vd-input" id="billingClinic" name="clinic_id">
                    <option value="">All clinics</option>
                    <?php foreach ($clinics as $clinic): ?>
                        <option value="<?= (int) $clinic['clinic_id'] ?>"><?= billingInsightEscape($clinic['clinic_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vd-billing-filter-actions">
                <button type="submit" class="btn vd-btn-gold" id="billingApply">
                    <i class="ti ti-adjustments-horizontal" aria-hidden="true"></i><span>Apply</span>
                </button>
                <button type="button" class="btn vd-btn-outline" id="billingReset">Reset</button>
            </div>
        </form>
        <div class="vd-report-alert d-none" id="billingInsightsError" role="alert"></div>
    </div>

    <div class="vd-billing-loading" id="billingInsightsLoading" aria-live="polite">
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <span>Calculating settlement activity…</span>
    </div>

    <div class="vd-billing-results d-none" id="billingInsightsResults">
        <section class="vd-billing-snapshot" aria-labelledby="billingSnapshotHeading">
            <div class="vd-billing-net">
                <span class="vd-billing-eyebrow" id="billingSnapshotHeading">Net collected</span>
                <strong id="billingNetCollected">₱0.00</strong>
                <span class="vd-billing-change" id="billingChange"></span>
                <p><span id="billingGrossCollected">₱0.00</span> gross collections less <span id="billingRefunds">₱0.00</span> refunded.</p>
            </div>
            <div class="vd-billing-supporting-metrics">
                <div><span>Settled visits</span><strong id="billingSettledVisits">0</strong><small>Finalized in period</small></div>
                <div><span>Average per visit</span><strong id="billingAverage">₱0.00</strong><small>Net collected ÷ visits</small></div>
                <div><span>Deposit contribution</span><strong id="billingDepositShare">0.0%</strong><small><span id="billingDeposits">₱0.00</span> applied</small></div>
            </div>
        </section>

        <div class="vd-billing-analysis-grid">
            <article class="vd-dash-card vd-billing-chart-panel">
                <div class="vd-dash-card-header">
                    <div>
                        <span class="vd-dash-card-title">Collection movement</span>
                        <div class="vd-report-help">Deposits and balances collected, with refunds shown below zero.</div>
                    </div>
                </div>
                <div class="vd-billing-chart">
                    <canvas id="billingCollectionChart" aria-label="Collection trend split into deposits, balances, and refunded deposits."></canvas>
                    <div class="vd-empty-state d-none" id="billingTrendEmpty">No collection activity in this period.</div>
                </div>
                <details class="vd-chart-definition">
                    <summary>How this chart is calculated</summary>
                    <p>Collections use the final settlement date. Refunds use the date the refund was recorded and reduce net collected.</p>
                </details>
            </article>

            <article class="vd-dash-card vd-billing-clinic-panel">
                <div class="vd-dash-card-header">
                    <div>
                        <span class="vd-dash-card-title">Clinic contribution</span>
                        <div class="vd-report-help">Net collections and settled visits by branch.</div>
                    </div>
                </div>
                <div class="vd-billing-clinic-list" id="billingClinicComparison"></div>
                <div class="vd-empty-state d-none" id="billingClinicEmpty">No clinic activity in this period.</div>
            </article>
        </div>

        <article class="vd-dash-card vd-billing-records">
            <div class="vd-dash-card-header">
                <div>
                    <span class="vd-dash-card-title">Recent settlements</span>
                    <div class="vd-report-help">Read-only finalized billing records within the selected period.</div>
                </div>
                <span class="vd-topbar-date" id="billingRecordCount">0 records</span>
            </div>
            <div class="vd-dash-card-body">
                <div class="vd-appt-table-wrap" id="billingRecordsWrap">
                    <table class="vd-report-table w-100" id="billingRecordsTable">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Visit</th>
                                <th>Settlement</th>
                                <th>Finalized</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="vd-empty-state d-none" id="billingRecordsEmpty">No finalized settlements match these filters.</div>
                <nav class="vd-billing-pagination d-none" id="billingPagination" aria-label="Settlement pages">
                    <span id="billingPaginationSummary"></span>
                    <div>
                        <button type="button" class="btn vd-btn-outline btn-sm" id="billingPrevious"><i class="ti ti-chevron-left" aria-hidden="true"></i><span>Previous</span></button>
                        <span id="billingPageLabel">Page 1 of 1</span>
                        <button type="button" class="btn vd-btn-outline btn-sm" id="billingNext"><span>Next</span><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
                    </div>
                </nav>
            </div>
        </article>

        <details class="vd-billing-definition">
            <summary>How the summary is calculated</summary>
            <p><strong>Net collected</strong> is deposits applied plus balances collected at final settlement, minus deposits refunded during the selected period. Reporting follows settlement and refund dates, not appointment dates.</p>
        </details>
    </div>
</section>

<div class="modal fade" id="billingRecordModal" tabindex="-1" aria-labelledby="billingRecordTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-action-modal-kicker">Final settlement</div>
                    <h5 class="modal-title vd-modal-title" id="billingRecordTitle">Billing details</h5>
                    <p class="text-muted small mb-0" id="billingRecordSubtitle"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <section>
                    <h6 class="vd-appointment-details-section-title">Visit information</h6>
                    <div class="vd-appointment-detail-grid" id="billingVisitGrid"></div>
                </section>
                <section class="vd-appointment-payment-section">
                    <h6 class="vd-appointment-details-section-title">Payment breakdown</h6>
                    <div class="vd-final-billing-summary" id="billingPaymentSummary"></div>
                </section>
                <section class="vd-appointment-activity-section">
                    <h6 class="vd-appointment-details-section-title">Record information</h6>
                    <div class="vd-appointment-detail-grid" id="billingRecordGrid"></div>
                    <div class="vd-appointment-payment-note d-none" id="billingRecordNotes"></div>
                </section>
            </div>
            <div class="modal-footer"><button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script>
    window.AdminBillingSummary?.init(document.getElementById('billingInsightsPage'));
</script>
