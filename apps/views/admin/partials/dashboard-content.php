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
$upcoming = array_values(array_filter(
    $appointmentModel->getAllUpcomingWithStatus(),
    static fn(array $appointment): bool => ($appointment['date'] ?? '') > date('Y-m-d')
));
$clinics = (new Clinic($conn))->getAllClinics();
$todayLogbook = $logbookModel->getToday();
$finishedQueueStatuses = ['Completed', 'Cancelled', 'No-show'];
$activeQueueEntries = array_values(array_filter(
    $todayLogbook,
    static fn(array $entry): bool => !in_array($entry['appointment_status'], $finishedQueueStatuses, true)
));
$finishedQueueEntries = array_values(array_filter(
    $todayLogbook,
    static fn(array $entry): bool => in_array($entry['appointment_status'], $finishedQueueStatuses, true)
));
$arrivedCount = count(array_filter($todayLogbook, static fn($row) => !empty($row['arrived_at'])));
$readyCount = count(array_filter($todayLogbook, static fn($row) => $row['checkin_status'] === 'Ready'));
$currentPatient = null;
$nextPatient = null;
foreach ($todayLogbook as $queueEntry) {
    if ($queueEntry['is_in_treatment'] && $currentPatient === null) $currentPatient = $queueEntry;
    if ($queueEntry['is_next'] && $nextPatient === null) $nextPatient = $queueEntry;
}
$queueCount = count(array_filter($todayLogbook, static fn($row) => $row['queue_position'] !== null));
$onHoldCount = count(array_filter($todayLogbook, static fn($row) => $row['queue_status'] === 'On Hold' && $row['appointment_status'] === 'Checked In'));
$reviewCount = $depositModel->getPendingReviewCount();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];
$dashboardDisplayName = $_SESSION['display_name'] ?? $_SESSION['email'] ?? 'Staff member';
// The session name may include middle names. Keep only its first and last
// parts in the dashboard greeting while preserving the full sidebar name.
$dashboardNameParts = preg_split('/\s+/', trim($dashboardDisplayName), -1, PREG_SPLIT_NO_EMPTY);
$dashboardGreetingName = count($dashboardNameParts) > 1
    ? $dashboardNameParts[0] . ' ' . $dashboardNameParts[count($dashboardNameParts) - 1]
    : $dashboardDisplayName;

function dashboardStatusClass($status)
{
    return 'vd-status vd-status-' . strtolower(str_replace(' ', '-', $status));
}

function dashboardQueueState(array $entry): array
{
    if ($entry['appointment_status'] === 'In Progress') {
        return ['label' => 'In treatment', 'class' => 'vd-status vd-status-in-progress', 'detail' => 'Treatment is underway'];
    }
    if ($entry['checkin_status'] === 'Profile Required') {
        return ['label' => 'Needs profile', 'class' => 'vd-status vd-status-profile-required', 'detail' => 'Complete the patient profile'];
    }
    if ($entry['queue_status'] === 'On Hold' && $entry['appointment_status'] === 'Checked In') {
        return ['label' => 'On hold', 'class' => 'vd-status vd-status-warning', 'detail' => 'Temporarily removed from queue'];
    }
    if ($entry['appointment_status'] === 'Checked In' && $entry['checkin_status'] === 'Ready') {
        return [
            'label' => 'Ready to treat',
            'class' => 'vd-status vd-status-ready',
            'detail' => $entry['is_next'] ? 'Next patient in line' : 'Waiting in queue',
        ];
    }
    if (!$entry['checkin_id'] && $entry['appointment_status'] === 'Confirmed') {
        return ['label' => 'Awaiting check-in', 'class' => 'vd-status vd-status-not-arrived', 'detail' => 'Patient has not checked in'];
    }

    return [
        'label' => $entry['appointment_status'],
        'class' => dashboardStatusClass($entry['appointment_status']),
        'detail' => $entry['checked_in_by'] ?: 'No next step available',
    ];
}

function dashboardBillingPayload(array $entry): string
{
    $amountDue = max(0, (float) ($entry['remaining_balance'] ?? 0));
    $cash = (float) ($entry['cash_received'] ?? 0);
    return htmlspecialchars(json_encode([
        'billingId' => (int) ($entry['billing_id'] ?? 0),
        'appointmentId' => (int) $entry['appointment_id'],
        'patient' => trim(($entry['firstname'] ?? '') . ' ' . ($entry['lastname'] ?? '')),
        'services' => $entry['service_name'] ?? '',
        'clinic' => $entry['clinic_name'] ?? '',
        'serviceAmount' => (float) ($entry['actual_service_amount'] ?? 0),
        'depositApplied' => (float) ($entry['deposit_applied'] ?? 0),
        'amountDue' => $amountDue,
        'cashTendered' => $cash,
        'change' => max(0, $cash - $amountDue),
        'status' => $entry['payment_status'] ?? '',
        'recordedBy' => $entry['billing_recorded_by'] ?? '',
        'recordedAt' => $entry['billing_recorded_at'] ?? '',
        'notes' => $entry['billing_notes'] ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}
?>

<div class="d-flex flex-column gap-4">
    <section class="vd-dashboard-welcome" aria-labelledby="vdDashboardGreeting">
        <div>
            <span class="vd-dashboard-welcome-kicker">Clinic operations</span>
            <h1 class="vd-dashboard-greeting" id="vdDashboardGreeting"
                data-user-name="<?= htmlspecialchars($dashboardGreetingName, ENT_QUOTES, 'UTF-8') ?>">
                Today’s overview
            </h1>
            <p class="vd-dashboard-welcome-copy">Welcome, <?= htmlspecialchars($dashboardGreetingName) ?>. Prioritize the live queue, then review upcoming visits.</p>
        </div>
        <button type="button" class="btn vd-btn-outline vd-dashboard-review-btn" id="openPaymentsAwaitingReview">
            <i class="ti ti-receipt" aria-hidden="true"></i>
            Payments to review
            <span class="vd-dashboard-review-count"><?= $reviewCount ?></span>
        </button>
    </section>

    <div class="vd-stat-grid">
        <div class="vd-stat-card">
            <div class="vd-stat-label">Today's Appointments</div>
            <div class="vd-stat-value"><?= count($todayLogbook) ?></div>
            <div class="vd-stat-sub">Appointments expected</div>
        </div>
        <div class="vd-stat-card">
            <div class="vd-stat-label">Arrived</div>
            <div class="vd-stat-value"><?= $arrivedCount ?></div>
            <div class="vd-stat-sub"><?= $readyCount ?> ready</div>
        </div>
        <div class="vd-stat-card">
            <div class="vd-stat-label">Upcoming</div>
            <div class="vd-stat-value"><?= count($upcoming) ?></div>
            <div class="vd-stat-sub">All active requests</div>
        </div>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header"><span class="vd-dash-card-title">Today’s Queue</span><span class="vd-topbar-date"><?= date('F j, Y') ?></span></div>
        <div class="vd-dash-card-body">
            <div class="d-flex flex-wrap gap-2 mb-4">
                <input type="text" class="form-control vd-input flex-grow-1" id="checkinLookup"
                    placeholder="Enter appointment code or patient name" aria-label="Appointment code or patient name"
                    autocomplete="off">
                <button type="button" class="btn vd-btn-gold" id="findCheckinAppointment">Find Appointment</button>
            </div>
            <div class="vd-queue-overview mb-4">
                <div class="vd-queue-focus vd-queue-focus-current">
                    <span class="vd-queue-kicker">Now treating</span>
                    <strong><?= $currentPatient ? htmlspecialchars(trim($currentPatient['firstname'] . ' ' . $currentPatient['lastname'])) : 'No active treatment' ?></strong>
                    <span><?= $currentPatient ? htmlspecialchars($currentPatient['service_name'] ?: 'Service not listed') : 'Start the next ready patient when the dentist is available.' ?></span>
                </div>
                <div class="vd-queue-focus vd-queue-focus-next">
                    <span class="vd-queue-kicker">Next patient</span>
                    <strong><?= $nextPatient ? htmlspecialchars(trim($nextPatient['firstname'] . ' ' . $nextPatient['lastname'])) : 'Queue is clear' ?></strong>
                    <span><?= $nextPatient ? (($nextPatient['serve_next_at'] ? 'Staff priority · ' : '') . htmlspecialchars($nextPatient['service_name'] ?: 'Service not listed')) : 'No ready patients are waiting.' ?></span>
                </div>
                <div class="vd-queue-counts">
                    <span><strong><?= $queueCount ?></strong> waiting</span>
                    <span><strong><?= $onHoldCount ?></strong> on hold</span>
                </div>
            </div>
            <?php if (!$activeQueueEntries): ?>
                <div class="vd-empty-state">No confirmed appointments scheduled for today.</div>
            <?php else: ?>
                <div class="vd-appt-table-wrap">
                    <table class="vd-appt-table vd-today-logbook-table w-100" id="todayLogbookTable">
                        <thead>
                            <tr>
                                <th>Queue</th>
                                <th>Patient</th>
                                <th>Visit</th>
                                <th>Current state</th>
                                <th>Next step</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeQueueEntries as $entry):
                                $queueState = dashboardQueueState($entry);
                                $queueRowClass = $entry['is_in_treatment'] ? 'vd-queue-row-current' : ($entry['is_next'] ? 'vd-queue-row-next' : '');
                            ?>
                                <tr class="<?= $queueRowClass ?>" data-logbook-patient="<?= (int) $entry['patient_id'] ?>">
                                    <td>
                                        <?php if ($entry['is_in_treatment']): ?><span class="vd-queue-badge vd-queue-now">Now</span>
                                        <?php elseif ($entry['is_next']): ?><span class="vd-queue-badge vd-queue-next">Next</span>
                                        <?php elseif ($entry['queue_position'] !== null): ?><span class="vd-queue-badge">#<?= (int) $entry['queue_position'] ?></span>
                                        <?php elseif ($entry['queue_status'] === 'On Hold' && $entry['appointment_status'] === 'Checked In'): ?><span class="vd-queue-badge vd-queue-hold">On hold</span>
                                        <?php elseif ($entry['checkin_status'] === 'Profile Required'): ?><span class="vd-queue-badge vd-queue-blocked">Not ready</span>
                                        <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                                        <?php if ($entry['serve_next_at'] && $entry['appointment_status'] === 'Checked In'): ?><div class="vd-queue-priority"><i class="ti ti-arrow-bar-to-up"></i> Staff priority</div><?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="vd-appt-name"><?= htmlspecialchars($entry['lastname'] . ', ' . $entry['firstname']) ?></div>
                                        <div class="vd-appt-meta"><?= htmlspecialchars($entry['email']) ?></div>
                                        <div class="vd-logbook-arrival"><i class="ti ti-clock" aria-hidden="true"></i><?= $entry['arrived_at'] ? 'Arrived ' . date('g:i A', strtotime($entry['arrived_at'])) : 'Not arrived' ?></div>
                                    </td>
                                    <td>
                                        <div class="vd-appt-name"><?= htmlspecialchars($entry['service_name'] ?: 'Service not listed') ?></div>
                                        <div class="vd-appt-meta"><i class="ti ti-building-hospital" aria-hidden="true"></i><?= htmlspecialchars($entry['clinic_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="vd-queue-state">
                                            <span class="<?= htmlspecialchars($queueState['class']) ?>"><?= htmlspecialchars($queueState['label']) ?></span>
                                            <span><?= htmlspecialchars($queueState['detail']) ?></span>
                                        </div>
                                    </td>
                                    <td class="vd-queue-action-cell">
                                        <?php if (!$entry['checkin_id'] && $entry['appointment_status'] === 'Confirmed'): ?>
                                            <span class="vd-queue-action-note">Waiting for patient code</span>
                                        <?php elseif ($entry['checkin_status'] === 'Profile Required'): ?>
                                            <button type="button" class="btn vd-btn-outline btn-sm vd-queue-primary-action" data-complete-profile="<?= (int) $entry['patient_id'] ?>" data-appointment-id="<?= (int) $entry['appointment_id'] ?>">Review profile</button>
                                        <?php elseif ($entry['appointment_status'] === 'Checked In' && $entry['checkin_status'] === 'Ready' && $entry['is_next']): ?>
                                            <div class="vd-queue-action-group">
                                                <button type="button" class="btn vd-btn-gold btn-sm vd-queue-primary-action" data-visit-status="In Progress" data-appointment-id="<?= (int) $entry['appointment_id'] ?>">
                                                    <i class="ti ti-player-play" aria-hidden="true"></i>Start treatment
                                                </button>
                                                <details class="vd-queue-more-actions">
                                                    <summary title="More queue actions" aria-label="More queue actions"><i class="ti ti-dots" aria-hidden="true"></i></summary>
                                                    <div class="vd-queue-more-menu">
                                                        <button type="button" class="btn vd-btn-outline btn-sm" data-queue-action="placeOnHold" data-appointment-id="<?= (int) $entry['appointment_id'] ?>" data-patient="<?= htmlspecialchars(trim($entry['firstname'] . ' ' . $entry['lastname'])) ?>">Place on hold</button>
                                                    </div>
                                                </details>
                                            </div>
                                        <?php elseif ($entry['appointment_status'] === 'Checked In' && $entry['queue_status'] === 'On Hold'): ?>
                                            <button type="button" class="btn vd-btn-outline btn-sm vd-queue-primary-action" data-queue-action="returnToQueue" data-appointment-id="<?= (int) $entry['appointment_id'] ?>" data-patient="<?= htmlspecialchars(trim($entry['firstname'] . ' ' . $entry['lastname'])) ?>">Return to queue</button>
                                        <?php elseif ($entry['appointment_status'] === 'Checked In' && $entry['checkin_status'] === 'Ready'): ?>
                                            <?php if (!$entry['serve_next_at']): ?>
                                                <button type="button" class="btn vd-btn-outline btn-sm vd-queue-primary-action" data-queue-action="serveNext" data-appointment-id="<?= (int) $entry['appointment_id'] ?>" data-patient="<?= htmlspecialchars(trim($entry['firstname'] . ' ' . $entry['lastname'])) ?>">Move to next</button>
                                            <?php else: ?><span class="vd-queue-action-note">Selected by staff</span><?php endif; ?>
                                        <?php elseif ($entry['appointment_status'] === 'In Progress'): ?>
                                            <button type="button" class="btn vd-btn-gold btn-sm vd-queue-primary-action" data-complete-with-billing
                                                data-appointment-id="<?= (int) $entry['appointment_id'] ?>"
                                                data-patient="<?= htmlspecialchars(trim($entry['firstname'] . ' ' . $entry['lastname'])) ?>"
                                                data-services="<?= htmlspecialchars($entry['service_name'] ?: 'Service not listed') ?>"
                                                data-clinic="<?= htmlspecialchars($entry['clinic_name']) ?>"
                                                data-deposit="<?= htmlspecialchars((string) ((float) $entry['verified_deposit'])) ?>">
                                                <i class="ti ti-check" aria-hidden="true"></i>Complete visit
                                            </button>
                                        <?php else: ?><span class="vd-queue-action-note"><?= htmlspecialchars($entry['checked_in_by'] ?: 'No action available') ?></span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($finishedQueueEntries): ?>
                <details class="vd-finished-today mt-4">
                    <summary>
                        <span><i class="ti ti-circle-check" aria-hidden="true"></i>Finished today</span>
                        <span class="vd-finished-count"><?= count($finishedQueueEntries) ?></span>
                        <i class="ti ti-chevron-down vd-finished-chevron" aria-hidden="true"></i>
                    </summary>
                    <div class="vd-finished-list">
                        <?php foreach ($finishedQueueEntries as $entry): ?>
                            <div class="vd-finished-row">
                                <div>
                                    <strong><?= htmlspecialchars($entry['lastname'] . ', ' . $entry['firstname']) ?></strong>
                                    <span><?= htmlspecialchars($entry['service_name'] ?: 'Service not listed') ?> · <?= htmlspecialchars($entry['clinic_name']) ?></span>
                                </div>
                                <span class="<?= dashboardStatusClass($entry['appointment_status']) ?>"><?= htmlspecialchars($entry['appointment_status']) ?></span>
                                <div class="vd-finished-action">
                                    <?php if ($entry['appointment_status'] === 'Completed' && !empty($entry['billing_id'])): ?>
                                        <button type="button" class="btn vd-btn-outline btn-sm" data-view-logbook-billing="<?= dashboardBillingPayload($entry) ?>">View billing</button>
                                    <?php elseif ($entry['appointment_status'] === 'Completed'): ?>
                                        <span class="vd-status vd-status-warning">Billing missing</span>
                                    <?php else: ?>
                                        <span class="vd-appt-meta">Recorded</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header"><span class="vd-dash-card-title">Upcoming Appointments</span><span class="vd-topbar-date"><?= count($upcoming) ?> total</span></div>
        <div class="vd-dash-card-body">
            <?php if (!$upcoming): ?><div class="vd-empty-state">No upcoming appointment requests.</div>
                <?php else: foreach (array_slice($upcoming, 0, 3) as $appt): ?>
                    <div class="vd-appt-row">
                        <div class="vd-appt-date-box">
                            <div class="vd-appt-day"><?= date('d', strtotime($appt['date'])) ?></div>
                            <div class="vd-appt-mon"><?= date('M', strtotime($appt['date'])) ?></div>
                        </div>
                        <div class="vd-appt-info">
                            <div class="vd-appt-name"><?= htmlspecialchars($appt['lastname'] . ', ' . $appt['firstname']) ?></div>
                            <div class="vd-appt-meta"><?= htmlspecialchars($appt['service_name']) ?> · <?= htmlspecialchars($appt['clinic_name']) ?></div>
                        </div>
                        <span class="<?= dashboardStatusClass($appt['status']) ?>"><?= htmlspecialchars($appt['status']) ?></span>
                    </div>
            <?php endforeach;
            endif; ?>
        </div>
    </div>
</div>

<div class="modal fade vd-final-billing-modal" id="finalBillingModal" tabindex="-1" aria-labelledby="finalBillingTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-action-modal-kicker">Complete transaction</div>
                    <h5 class="modal-title vd-modal-title" id="finalBillingTitle">Final Billing</h5>
                    <p class="text-muted small mb-0" id="finalBillingSubtitle"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="vd-appointment-detail-grid mb-4" id="finalBillingVisitDetails"></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="vd-label form-label" for="finalServiceAmount">Actual treatment charge</label><input type="number" min="0" step="0.01" class="form-control vd-input" id="finalServiceAmount" required></div>
                    <div class="col-md-6"><label class="vd-label form-label" for="finalCashTendered">Cash tendered</label><input type="number" min="0" step="0.01" class="form-control vd-input" id="finalCashTendered" value="0" required></div>
                    <div class="col-12"><label class="vd-label form-label" for="finalBillingNotes">Billing notes (optional)</label><textarea class="form-control vd-input" id="finalBillingNotes" rows="2" maxlength="255"></textarea></div>
                </div>
                <div class="vd-final-billing-summary mt-4">
                    <div><span>Actual charge</span><strong id="finalChargeDisplay">₱0.00</strong></div>
                    <div><span>Deposit applied</span><strong id="finalDepositDisplay">−₱0.00</strong></div>
                    <div class="vd-final-billing-total"><span>Amount due</span><strong id="finalAmountDueDisplay">₱0.00</strong></div>
                    <div><span>Cash tendered</span><strong id="finalCashDisplay">₱0.00</strong></div>
                    <div><span>Change</span><strong id="finalChangeDisplay">₱0.00</strong></div>
                </div>
                <div class="alert alert-danger d-none mt-3 mb-0" id="finalBillingError"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn vd-btn-gold" id="recordPaymentAndComplete" disabled>Record Payment &amp; Complete Visit</button></div>
        </div>
    </div>
</div>

<div class="modal fade vd-transaction-receipt-modal" id="logbookBillingDetailsModal" tabindex="-1" aria-labelledby="logbookBillingDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content vd-modal-content vd-receipt-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-action-modal-kicker">Completed transaction</div>
                    <h5 class="modal-title vd-modal-title" id="logbookBillingDetailsTitle">Payment receipt</h5>
                    <p class="vd-receipt-modal-subtitle mb-0" id="logbookBillingDetailsSubtitle"></p>
                </div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <article class="vd-transaction-receipt" aria-label="Completed transaction receipt">
                    <div class="vd-receipt-brand">
                        <span class="vd-receipt-mark"><i class="ti ti-tooth" aria-hidden="true"></i></span>
                        <div><strong>Dr. Aprille Ventura</strong><span>Clinica Dental</span></div>
                        <span class="vd-status vd-status-paid" id="logbookReceiptStatus">Paid</span>
                    </div>
                    <div class="vd-receipt-visit">
                        <div><span>Patient</span><strong id="logbookReceiptPatient"></strong></div>
                        <div><span>Clinic</span><strong id="logbookReceiptClinic"></strong></div>
                    </div>
                    <section class="vd-receipt-services" aria-labelledby="logbookReceiptServicesHeading">
                        <div class="vd-receipt-section-heading"><h6 id="logbookReceiptServicesHeading">Services availed</h6><span>Service prices pending setup</span></div>
                        <ul id="logbookReceiptServices"></ul>
                    </section>
                    <section class="vd-receipt-totals" id="logbookReceiptTotals" aria-label="Payment breakdown"></section>
                    <div class="vd-receipt-meta" id="logbookReceiptMeta"></div>
                    <div class="vd-appointment-payment-note d-none" id="logbookBillingNotes"></div>
                </article>
            </div>
            <div class="modal-footer"><button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script>
    (function() {
        const money = value => Number(value || 0).toLocaleString('en-PH', {
            style: 'currency',
            currency: 'PHP'
        });
        const addBillingDetail = (container, label, value) => {
            const item = document.createElement('div');
            item.className = 'vd-appointment-detail-item';
            const term = document.createElement('span');
            term.textContent = label;
            const detail = document.createElement('strong');
            detail.textContent = value || 'Not provided';
            item.append(term, detail);
            container.appendChild(item);
        };
        document.getElementById('openPaymentsAwaitingReview')?.addEventListener('click', () => {
            sessionStorage.setItem('venturaAppointmentStatusFilter', 'Payment Under Review');
            document.querySelector('[data-page="appointment-content.php"]')?.click();
        });
        const csrfToken = <?= json_encode($csrfToken) ?>;
        let activeBillingAppointment = null;
        const finalBillingModalElement = document.getElementById('finalBillingModal');
        let finalBillingModal = null;
        const serviceAmountInput = document.getElementById('finalServiceAmount');
        const cashTenderedInput = document.getElementById('finalCashTendered');
        const completeBillingButton = document.getElementById('recordPaymentAndComplete');

        function updateFinalBillingSummary() {
            const charge = Math.max(0, Number(serviceAmountInput.value) || 0);
            const deposit = Math.min(Number(activeBillingAppointment?.deposit || 0), charge);
            const due = Math.max(0, charge - deposit);
            const cash = Math.max(0, Number(cashTenderedInput.value) || 0);
            const change = Math.max(0, cash - due);
            document.getElementById('finalChargeDisplay').textContent = money(charge);
            document.getElementById('finalDepositDisplay').textContent = '−' + money(deposit);
            document.getElementById('finalAmountDueDisplay').textContent = money(due);
            document.getElementById('finalCashDisplay').textContent = money(cash);
            document.getElementById('finalChangeDisplay').textContent = money(change);
            completeBillingButton.disabled = serviceAmountInput.value === '' || cash < due;
            const error = document.getElementById('finalBillingError');
            if (serviceAmountInput.value !== '' && cash < due) {
                error.textContent = `Cash tendered is ${money(due - cash)} short of the amount due.`;
                error.classList.remove('d-none');
            } else {
                error.classList.add('d-none');
                error.textContent = '';
            }
        }

        document.querySelectorAll('[data-complete-with-billing]').forEach(button => button.addEventListener('click', () => {
            activeBillingAppointment = {
                id: button.dataset.appointmentId,
                patient: button.dataset.patient,
                services: button.dataset.services,
                clinic: button.dataset.clinic,
                deposit: Number(button.dataset.deposit || 0)
            };
            document.getElementById('finalBillingTitle').textContent = `Final Billing · ${activeBillingAppointment.patient}`;
            document.getElementById('finalBillingSubtitle').textContent = `Appointment #${activeBillingAppointment.id}`;
            const details = document.getElementById('finalBillingVisitDetails');
            details.replaceChildren();
            addBillingDetail(details, 'Patient', activeBillingAppointment.patient);
            addBillingDetail(details, 'Services', activeBillingAppointment.services);
            addBillingDetail(details, 'Clinic', activeBillingAppointment.clinic);
            addBillingDetail(details, 'Verified deposit', money(activeBillingAppointment.deposit));
            serviceAmountInput.value = '';
            cashTenderedInput.value = '0';
            document.getElementById('finalBillingNotes').value = '';
            updateFinalBillingSummary();
            finalBillingModal = bootstrap.Modal.getOrCreateInstance(finalBillingModalElement);
            finalBillingModal.show();
        }));
        [serviceAmountInput, cashTenderedInput].forEach(input => input?.addEventListener('input', updateFinalBillingSummary));

        completeBillingButton?.addEventListener('click', async function() {
            if (!activeBillingAppointment) return;
            const confirmation = await window.showActionModal({
                title: 'Confirm Final Payment',
                kicker: 'Complete transaction',
                message: 'This records the cash payment and completes the visit. The transaction cannot be edited from Today’s Logbook afterward.',
                confirmText: 'Confirm & Complete',
                icon: 'ti-cash-check',
                tone: 'success',
                details: [{
                        label: 'Patient',
                        value: activeBillingAppointment.patient
                    },
                    {
                        label: 'Amount due',
                        value: document.getElementById('finalAmountDueDisplay').textContent
                    },
                    {
                        label: 'Cash tendered',
                        value: document.getElementById('finalCashDisplay').textContent
                    },
                    {
                        label: 'Change',
                        value: document.getElementById('finalChangeDisplay').textContent
                    }
                ]
            });
            if (!confirmation.confirmed) return;
            LoadingUI.setButton(this, true, 'Completing...');
            const body = new FormData();
            body.append('action', 'settleAndComplete');
            body.append('csrf_token', csrfToken);
            body.append('appointment_id', activeBillingAppointment.id);
            body.append('service_amount', serviceAmountInput.value);
            body.append('cash_received', cashTenderedInput.value);
            body.append('notes', document.getElementById('finalBillingNotes').value);
            try {
                const response = await fetch('../../controllers/billingController.php', {
                    method: 'POST',
                    body
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to complete the transaction.');
                finalBillingModal.hide();
                window.showToast(result.message, true);
                document.querySelector('[data-page="dashboard-content.php"]')?.click();
            } catch (error) {
                LoadingUI.setButton(this, false);
                window.showToast(error.message, false);
            }
        });

        finalBillingModalElement?.addEventListener('hidden.bs.modal', () => {
            activeBillingAppointment = null;
            LoadingUI.setButton(completeBillingButton, false);
        });

        document.querySelectorAll('[data-view-logbook-billing]').forEach(button => button.addEventListener('click', () => {
            const billing = JSON.parse(button.dataset.viewLogbookBilling);
            document.getElementById('logbookBillingDetailsTitle').textContent = `Receipt #${billing.billingId || billing.appointmentId}`;
            document.getElementById('logbookBillingDetailsSubtitle').textContent = `Appointment #${billing.appointmentId}`;
            document.getElementById('logbookReceiptPatient').textContent = billing.patient || 'Patient';
            document.getElementById('logbookReceiptClinic').textContent = billing.clinic || 'Clinic not listed';
            const status = document.getElementById('logbookReceiptStatus');
            status.className = `vd-status vd-status-${String(billing.status || 'paid').toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
            status.textContent = billing.status || 'Paid';

            const services = document.getElementById('logbookReceiptServices');
            services.replaceChildren();
            String(billing.services || 'Service not listed').split(',').map(item => item.trim()).filter(Boolean).forEach(service => {
                const item = document.createElement('li');
                item.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i>';
                const name = document.createElement('span');
                name.textContent = service;
                item.appendChild(name);
                services.appendChild(item);
            });

            const totals = document.getElementById('logbookReceiptTotals');
            totals.replaceChildren();
            [['Treatment total', money(billing.serviceAmount)], ['Deposit applied', `−${money(billing.depositApplied)}`], ['Amount due', money(billing.amountDue)], ['Cash tendered', money(billing.cashTendered)], ['Change', money(billing.change)]].forEach(([label, value], index) => {
                const row = document.createElement('div');
                if (index === 2) row.className = 'vd-receipt-amount-due';
                const term = document.createElement('span');
                term.textContent = label;
                const amount = document.createElement('strong');
                amount.textContent = value;
                row.append(term, amount);
                totals.appendChild(row);
            });

            const recordedAt = billing.recordedAt ? new Date(String(billing.recordedAt).replace(' ', 'T')).toLocaleString([], { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : 'Not recorded';
            document.getElementById('logbookReceiptMeta').innerHTML = `<span>Recorded by <strong></strong></span><span>Recorded <strong></strong></span>`;
            const metaValues = document.querySelectorAll('#logbookReceiptMeta strong');
            metaValues[0].textContent = billing.recordedBy || 'Clinic staff';
            metaValues[1].textContent = recordedAt;
            const note = document.getElementById('logbookBillingNotes');
            note.textContent = billing.notes || '';
            note.classList.toggle('d-none', !billing.notes);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('logbookBillingDetailsModal')).show();
        }));
        document.getElementById('findCheckinAppointment')?.addEventListener('click', async () => {
            const term = document.getElementById('checkinLookup').value.trim();
            if (term.length < 2) {
                window.showToast('Enter an appointment code or at least two letters of the patient name.', false);
                return;
            }
            const lookupBody = new FormData();
            lookupBody.append('action', 'lookup');
            lookupBody.append('term', term);
            lookupBody.append('csrf_token', csrfToken);
            try {
                const lookupResponse = await fetch('../../controllers/logbookController.php', {
                    method: 'POST',
                    body: lookupBody
                });
                const lookup = await lookupResponse.json();
                if (!lookup.success || !lookup.matches?.length) throw new Error('No confirmed appointment for today matches that search.');

                let match = lookup.matches[0];
                const confirmation = lookup.matches.length > 1 ?
                    await window.showActionModal({
                        title: 'Select Patient',
                        kicker: 'Logbook check-in',
                        message: 'More than one patient matched. Select the correct appointment before checking in.',
                        confirmText: 'Check In Patient',
                        icon: 'ti-users',
                        tone: 'success',
                        fields: [{
                            name: 'appointment_id',
                            label: 'Today\'s matching appointments',
                            placeholder: 'Select a patient',
                            required: true,
                            options: lookup.matches.map(item => ({
                                value: String(item.appointment_id),
                                label: `${item.firstname} ${item.lastname} — ${item.service_name || 'Service not listed'} — ${item.clinic_name}`
                            }))
                        }]
                    }) :
                    await window.showActionModal({
                        title: 'Confirm Patient Arrival',
                        kicker: 'Logbook check-in',
                        message: 'Verify the appointment details before recording this patient as arrived.',
                        confirmText: 'Check In Patient',
                        icon: 'ti-login-2',
                        tone: 'success',
                        details: [{
                                label: 'Patient',
                                value: `${match.firstname} ${match.lastname}`
                            },
                            {
                                label: 'Service',
                                value: match.service_name || 'Service not listed'
                            },
                            {
                                label: 'Clinic',
                                value: match.clinic_name
                            }
                        ]
                    });
                if (!confirmation.confirmed) return;
                if (lookup.matches.length > 1) {
                    match = lookup.matches.find(item => String(item.appointment_id) === confirmation.values.appointment_id);
                    if (!match) throw new Error('Select a valid appointment.');
                }
                const body = new FormData();
                body.append('action', 'checkIn');
                body.append('appointment_id', match.appointment_id);
                body.append('lookup_method', match.lookup_method);
                body.append('csrf_token', csrfToken);
                const response = await fetch('../../controllers/logbookController.php', {
                    method: 'POST',
                    body
                });
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Check-in failed.');
                window.showToast(result.message, true);
                document.querySelector('[data-page="dashboard-content.php"]')?.click();
            } catch (error) {
                window.showToast(error.message, false);
            }
        });

        document.querySelectorAll('[data-complete-profile]').forEach(button => {
            button.addEventListener('click', async () => {
                const content = document.querySelector('.vd-dash-content');
                LoadingUI.showContent(content, {
                    label: 'Loading patient form…'
                });
                try {
                    const response = await fetch(`partials/_patient-checkin-form.php?id=${button.dataset.completeProfile}&appointment_id=${button.dataset.appointmentId}`);
                    if (!response.ok) throw new Error('Unable to load patient form.');
                    content.innerHTML = await response.text();
                    content.querySelectorAll('script').forEach(oldScript => {
                        const script = document.createElement('script');
                        script.textContent = oldScript.textContent;
                        document.body.appendChild(script);
                        oldScript.remove();
                    });
                } catch (error) {
                    content.innerHTML = `<div class="vd-empty-state">${error.message}</div>`;
                } finally {
                    LoadingUI.finishContent(content);
                }
            });
        });

        const highlightedPatient = sessionStorage.getItem('highlightLogbookPatient');
        if (highlightedPatient) {
            const row = document.querySelector(`[data-logbook-patient="${highlightedPatient}"]`);
            if (row) {
                row.classList.add('vd-logbook-row-highlight');
                row.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                window.setTimeout(() => row.classList.remove('vd-logbook-row-highlight'), 3500);
            }
            sessionStorage.removeItem('highlightLogbookPatient');
        }

        document.querySelectorAll('[data-queue-action]').forEach(button => {
            button.addEventListener('click', async () => {
                const action = button.dataset.queueAction;
                const patient = button.dataset.patient || 'this patient';
                const needsReason = action !== 'returnToQueue';
                const serveNext = action === 'serveNext';
                /* Explain each manual queue change and require a reason when staff changes the waiting order. */
                const confirmation = await window.showActionModal({
                    title: serveNext ? 'Serve Patient Next' : (action === 'placeOnHold' ? 'Place Patient on Hold' : 'Return Patient to Queue'),
                    kicker: 'Patient queue',
                    message: serveNext ?
                        'This patient will become next and temporarily move ahead of the current waiting order.' :
                        (action === 'placeOnHold' ? 'The patient will be removed from the active queue until staff returns them.' : 'The patient will return at the end of the queue.'),
                    confirmText: serveNext ? 'Serve Next' : (action === 'placeOnHold' ? 'Place on Hold' : 'Return to Queue'),
                    icon: serveNext ? 'ti-arrow-bar-to-up' : (action === 'placeOnHold' ? 'ti-player-pause' : 'ti-arrow-back-up'),
                    tone: serveNext ? 'warning' : 'info',
                    details: [{
                        label: 'Patient',
                        value: patient
                    }],
                    fields: needsReason ? [{
                        name: 'reason',
                        label: serveNext ? 'Reason for serving next' : 'Reason for placing on hold',
                        multiline: true,
                        rows: 2,
                        required: true,
                        minlength: 3,
                        maxlength: 255
                    }] : []
                });
                if (!confirmation.confirmed) return;

                const body = new FormData();
                body.append('action', action);
                body.append('appointment_id', button.dataset.appointmentId);
                body.append('csrf_token', csrfToken);
                if (needsReason) body.append('reason', confirmation.values.reason);
                LoadingUI.setButton(button, true, 'Updating…');
                try {
                    const response = await fetch('../../controllers/logbookController.php', {
                        method: 'POST',
                        body
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'Unable to update the queue.');
                    window.showToast(result.message, true);
                    document.querySelector('[data-page="dashboard-content.php"]')?.click();
                } catch (error) {
                    window.showToast(error.message || 'Unable to update the queue.', false);
                    LoadingUI.setButton(button, false);
                }
            });
        });

        document.querySelectorAll('[data-visit-status]').forEach(button => {
            button.addEventListener('click', async () => {
                const status = button.dataset.visitStatus;
                const isStarting = status === 'In Progress';
                const confirmation = await window.showActionModal({
                    title: isStarting ? 'Start Treatment' : 'Complete Visit',
                    kicker: "Today's logbook",
                    message: isStarting ?
                        'Confirm that the patient is ready and treatment is beginning.' :
                        'Confirm that treatment for this visit has been completed.',
                    confirmText: isStarting ? 'Start Treatment' : 'Complete Visit',
                    icon: isStarting ? 'ti-player-play' : 'ti-check',
                    tone: 'success'
                });
                if (!confirmation.confirmed) return;

                const body = new FormData();
                body.append('action', 'updateVisitStatus');
                body.append('appointment_id', button.dataset.appointmentId);
                body.append('status', status);
                body.append('csrf_token', csrfToken);

                LoadingUI.setButton(button, true, isStarting ? 'Starting…' : 'Completing…');
                try {
                    const response = await fetch('../../controllers/logbookController.php', {
                        method: 'POST',
                        body
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'Unable to update the visit.');
                    window.showToast(isStarting ? 'Treatment started.' : 'Visit completed.', true);
                    document.querySelector('[data-page="dashboard-content.php"]')?.click();
                } catch (error) {
                    window.showToast(error.message || 'Unable to update the visit.', false);
                    LoadingUI.setButton(button, false);
                }
            });
        });
    })();
</script>
