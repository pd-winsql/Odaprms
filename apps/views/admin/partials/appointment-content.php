<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once  __DIR__ . '/../../../../config/conn.php';
require_once  __DIR__ . '/../../../models/appointmentModel.php';
require_once  __DIR__ . '/../../../helpers/paymentSettings.php';

$db   = new Database();
$conn = $db->connect();
$appointmentModel = new Appointment($conn);
$paymentSettings = $conn->query("SELECT deposit_amount, payment_deadline_minutes FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$configuredDepositLabel = vdFormatPesoAmount((float) ($paymentSettings['deposit_amount'] ?? 400));
$configuredDeadlineLabel = vdFormatDurationMinutes((int) ($paymentSettings['payment_deadline_minutes'] ?? 480));
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$upcoming = $appointmentModel->getAllUpcomingWithStatus();
$past     = $appointmentModel->getAdminPastAppointments();
$appointmentIds = array_merge(
    array_column($upcoming, 'appointment_id'),
    array_column($past, 'appointment_id')
);
$servicesByAppointment = $appointmentModel->getServiceDetailsForAppointments($appointmentIds);

// Build date bounds for each table independently.
function buildFilterOptions($rows) {
    $dates         = [];

    foreach ($rows as $r) {
        $dates[] = date('Y-m-d', strtotime($r['date']));
    }

    sort($dates);

    return [
        'minDate' => $dates[0] ?? '',
        'maxDate' => $dates ? $dates[count($dates) - 1] : '',
    ];
}

$statusFilterOrder = [
    'Pending Review',
    'Awaiting Deposit',
    'Payment Under Review',
    'Confirmed',
    'Checked In',
    'In Progress',
    'Completed',
    'Cancelled',
    'No-show',
    'Rejected',
];

$upcomingFilters = buildFilterOptions($upcoming);
$pastFilters     = buildFilterOptions($past);

function statusClass($status) {
    return 'vd-status vd-status-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $status));
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

function appointmentDetailsPayload(array $appointment, array $services): string {
    $fullName = trim(implode(' ', array_filter([
        $appointment['firstname'] ?? '',
        $appointment['middlename'] ?? '',
        $appointment['lastname'] ?? '',
    ])));

    return htmlspecialchars(json_encode([
        'appointmentId' => (int) $appointment['appointment_id'],
        'patientName' => $fullName,
        'email' => $appointment['email'] ?? '',
        'phone' => $appointment['phone_number'] ?? '',
        'age' => $appointment['age'] ?? '',
        'gender' => $appointment['gender'] ?? '',
        'clinic' => $appointment['clinic_name'] ?? '',
        'date' => $appointment['date'] ?? '',
        'status' => $appointment['status'] ?? '',
        'deposit' => !empty($appointment['deposit_id']) ? [
            'id' => (int) $appointment['deposit_id'],
            'amount' => $appointment['deposit_amount'] ?? '',
            'status' => $appointment['deposit_status'] ?? '',
            'reference' => $appointment['gcash_reference'] ?? '',
            'submittedAt' => $appointment['submitted_at'] ?? '',
            'verifiedAt' => $appointment['verified_at'] ?? '',
            'verifiedBy' => $appointment['payment_verified_by'] ?? '',
            'verifiedByRole' => $appointment['payment_verified_by_role'] ?? '',
            'deadlineAt' => $appointment['resubmission_deadline_at'] ?: ($appointment['payment_deadline_at'] ?? ''),
            'rejectionReason' => $appointment['payment_rejection_reason'] ?? '',
            'refundReason' => $appointment['refund_reason'] ?? '',
            'refundedAt' => $appointment['refunded_at'] ?? '',
            'hasReceipt' => (bool) ($appointment['has_receipt'] ?? false),
            'receiptUrl' => !empty($appointment['has_receipt'])
                ? '../../controllers/depositController.php?action=receipt&deposit_id=' . (int) $appointment['deposit_id']
                : '',
        ] : null,
        'appointmentCode' => $appointment['appointment_code'] ?? '',
        'services' => array_map(static fn($service) => [
            'name' => $service['service_name'] ?? '',
            'description' => $service['service_description'] ?? '',
            'category' => $service['category_name'] ?? 'Dental Service',
            'icon' => $service['service_icon'] ?? 'fa-solid fa-tooth',
        ], $services),
        'activity' => !empty($appointment['status_changed_by']) ? [
            'name' => $appointment['status_changed_by'],
            'role' => $appointment['status_changed_by_role'] ?? '',
            'at' => $appointment['status_changed_at'] ?? '',
            'status' => $appointment['status'] ?? '',
        ] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
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

        <div class="vd-status-filter-wrap">
            <div class="vd-status-filter-toggle" id="upcomingStatusToggles" role="group" aria-label="Filter upcoming appointments by status">
                <button type="button" class="vd-status-toggle-btn active" data-status="">All Status</button>
                <?php foreach ($statusFilterOrder as $status): ?>
                    <button type="button" class="vd-status-toggle-btn" data-status="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Date filter bar -->
        <div class="vd-filter-bar">
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
                        <button type="button" class="btn vd-btn-outline btn-md vd-appointment-details-btn"
                            title="View appointment details"
                            data-appointment-details="<?= appointmentDetailsPayload($appt, $servicesByAppointment[(int) $appt['appointment_id']] ?? []) ?>">
                            <i class="ti ti-eye" aria-hidden="true"></i>
                        </button>
                        <?php if ($appt['status'] === 'Pending Review'): ?>
                        <button type="button" class="btn vd-btn-gold btn-sm" data-status-action="Awaiting Deposit"
                            data-appointment-id="<?= (int)$appt['appointment_id'] ?>"
                            data-email="<?= htmlspecialchars($appt['email']) ?>"
                            data-name="<?= htmlspecialchars($appt['firstname'] . ' ' . $appt['lastname']) ?>">Accept</button>
                        <button type="button" class="btn vd-btn-outline btn-sm" data-status-action="Rejected"
                            data-appointment-id="<?= (int)$appt['appointment_id'] ?>"
                            data-email="<?= htmlspecialchars($appt['email']) ?>"
                            data-name="<?= htmlspecialchars($appt['firstname'] . ' ' . $appt['lastname']) ?>">Reject</button>
                        <?php elseif ($appt['status'] === 'Payment Under Review'): ?>
                        <button type="button" class="btn vd-btn-gold btn-sm vd-appointment-details-btn vd-review-payment-btn"
                            data-appointment-details="<?= appointmentDetailsPayload($appt, $servicesByAppointment[(int) $appt['appointment_id']] ?? []) ?>">Review Payment</button>
                        <?php elseif ($appt['status'] === 'Awaiting Deposit'): ?>
                        <button type="button" class="btn vd-btn-outline btn-sm" data-extend-deadline="<?= (int)$appt['appointment_id'] ?>">Extend 8h</button>
                        <button type="button" class="btn vd-btn-outline btn-sm" data-transfer-deposit="<?= (int)$appt['appointment_id'] ?>">Transfer Deposit</button>
                        <button type="button" class="btn vd-btn-outline btn-sm" data-status-action="Cancelled"
                            data-appointment-id="<?= (int)$appt['appointment_id'] ?>"
                            data-email="<?= htmlspecialchars($appt['email']) ?>"
                            data-name="<?= htmlspecialchars($appt['firstname'] . ' ' . $appt['lastname']) ?>">Cancel</button>
                        <?php elseif ($appt['status'] === 'Confirmed'): ?>
                        <button type="button" class="btn vd-btn-outline btn-sm" data-status-action="Cancelled"
                            data-appointment-id="<?= (int)$appt['appointment_id'] ?>" data-email="<?= htmlspecialchars($appt['email']) ?>"
                            data-name="<?= htmlspecialchars($appt['firstname'] . ' ' . $appt['lastname']) ?>">Cancel</button>
                        <button type="button" class="btn vd-btn-outline btn-sm" data-status-action="No-show"
                            data-appointment-id="<?= (int)$appt['appointment_id'] ?>" data-email="<?= htmlspecialchars($appt['email']) ?>"
                            data-name="<?= htmlspecialchars($appt['firstname'] . ' ' . $appt['lastname']) ?>">No-show</button>
                        <?php elseif ($appt['status'] === 'Checked In'): ?>
                        <button type="button" class="btn vd-btn-outline btn-sm" data-open-today-queue>Manage Queue</button>
                        <?php elseif ($appt['status'] === 'In Progress'): ?>
                        <button type="button" class="btn vd-btn-gold btn-sm" data-status-action="Completed"
                            data-appointment-id="<?= (int)$appt['appointment_id'] ?>" data-email="<?= htmlspecialchars($appt['email']) ?>"
                            data-name="<?= htmlspecialchars($appt['firstname'] . ' ' . $appt['lastname']) ?>">Complete</button>
                        <?php elseif ($appt['status'] === 'Cancelled' && ($appt['deposit_status'] ?? '') === 'For Refund'): ?>
                        <button type="button" class="btn vd-btn-outline btn-sm" data-record-refund="<?= (int)$appt['appointment_id'] ?>">Record Refund</button>
                        <?php endif; ?>
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

        <div class="vd-status-filter-wrap">
            <div class="vd-status-filter-toggle" id="pastStatusToggles" role="group" aria-label="Filter past appointments by status">
                <button type="button" class="vd-status-toggle-btn active" data-status="">All Status</button>
                <?php foreach ($statusFilterOrder as $status): ?>
                    <button type="button" class="vd-status-toggle-btn" data-status="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Date filter bar -->
        <div class="vd-filter-bar">
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
                    <th>Clinic</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Latest Activity</th>
                    <th>Action</th>
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
                    <td>
                        <button type="button" class="btn vd-btn-outline btn-md vd-appointment-details-btn"
                            data-appointment-details="<?= appointmentDetailsPayload($appt, $servicesByAppointment[(int) $appt['appointment_id']] ?? []) ?>">
                            <i class="ti ti-eye" aria-hidden="true"></i>
                        </button>
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

<div class="modal fade vd-appointment-details-modal" id="appointmentDetailsModal" tabindex="-1"
    aria-labelledby="appointmentDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-appointment-details-kicker">Appointment details</div>
                    <h5 class="modal-title vd-modal-title" id="appointmentDetailsTitle">Patient appointment</h5>
                    <p class="vd-appointment-details-subtitle mb-0" id="appointmentDetailsSubtitle"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <section aria-labelledby="appointmentInformationHeading">
                    <h6 class="vd-appointment-details-section-title" id="appointmentInformationHeading">Appointment information</h6>
                    <div class="vd-appointment-detail-grid" id="appointmentDetailGrid"></div>
                </section>

                <section class="vd-appointment-services-section" aria-labelledby="appointmentServicesHeading">
                    <div class="vd-appointment-section-heading">
                        <h6 class="vd-appointment-details-section-title mb-0" id="appointmentServicesHeading">Selected services</h6>
                        <span class="vd-appointment-service-count" id="appointmentServiceCount"></span>
                    </div>
                    <div class="vd-appointment-service-list" id="appointmentServiceList"></div>
                </section>

                <section class="vd-appointment-payment-section" aria-labelledby="appointmentPaymentHeading">
                    <div class="vd-appointment-section-heading">
                        <h6 class="vd-appointment-details-section-title mb-0" id="appointmentPaymentHeading">Deposit and payment</h6>
                        <span class="vd-status" id="appointmentDepositStatus"></span>
                    </div>
                    <div class="vd-appointment-detail-grid" id="appointmentPaymentGrid"></div>
                    <div class="vd-appointment-payment-note d-none" id="appointmentPaymentNote"></div>
                    <div class="vd-appointment-receipt-wrap d-none" id="appointmentReceiptWrap">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <strong>Submitted receipt</strong>
                            <button type="button" class="btn vd-btn-outline btn-sm" id="toggleAppointmentReceipt">View Receipt</button>
                        </div>
                        <div class="vd-appointment-receipt-preview d-none" id="appointmentReceiptPreview">
                            <div class="vd-receipt-preview-loading" id="appointmentReceiptLoading">
                                <span class="vd-spinner" aria-hidden="true"></span><span>Loading receipt...</span>
                            </div>
                            <iframe id="appointmentReceiptFrame" class="vd-receipt-preview-frame" title="Payment receipt preview"></iframe>
                        </div>
                    </div>
                </section>

                <section class="vd-appointment-activity-section" aria-labelledby="appointmentActivityHeading">
                    <h6 class="vd-appointment-details-section-title" id="appointmentActivityHeading">Latest activity</h6>
                    <div id="appointmentActivityDetail"></div>
                </section>
            </div>
            <div class="modal-footer">
                <div class="d-flex flex-wrap gap-2 me-auto" id="appointmentModalActions"></div>
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const CONTROLLER = '../../../apps/controllers/appointmentController.php';
    const DEPOSIT_CONTROLLER = '../../../apps/controllers/depositController.php';
    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;
    let activeAppointmentPayload = null;

    function showToast(msg, success) {
        // Prefer the global showToast provided by the dashboard shell.
        if (typeof window.showToast === 'function') { window.showToast(msg, success); return; }
        // No global toast available (partial may be loaded standalone). Log instead.
        console.warn('showToast not available:', msg);
    }

    function deliverQueuedNotification(notification) {
        // Status/payment requests finish first. This separate browser request
        // sends the queued email without delaying the visible staff action.
        if (notification?.id) {
            window.EmailNotificationDelivery?.deliver(notification.id);
        }
    }

    function updateStatusPill(id, newStatus) {
        const pill = document.getElementById('pill-' + id);
        if (!pill) return;
        pill.className = 'vd-status vd-status-' + newStatus.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        pill.textContent = newStatus;
    }

    function updateStatusAudit(id, audit) {
        const cell = document.getElementById('audit-' + id);
        if (!cell || !audit) return;
        renderActivityCard(cell, audit, document.getElementById('pill-' + id)?.textContent.trim() || 'Updated');
    }

    function applyAppointmentResult(id, newStatus, audit, updates = {}) {
        const row = document.querySelector(`tr[data-id="${CSS.escape(String(id))}"]`);
        if (!row) return;

        // Update only the affected row. This avoids downloading and rebuilding
        // the complete appointment list after a successful action.
        row.dataset.status = newStatus;
        updateStatusPill(id, newStatus);
        updateStatusAudit(id, audit);

        const detailsButton = row.querySelector('.vd-action-group > .vd-appointment-details-btn[title]');
        let details = null;
        if (detailsButton?.dataset.appointmentDetails) {
            try {
                details = JSON.parse(detailsButton.dataset.appointmentDetails);
                details.status = newStatus;
                details.activity = audit ? {
                    name: audit.performed_by_name,
                    role: audit.performed_by_role,
                    at: audit.performed_at,
                    status: newStatus
                } : details.activity;
                if (updates.appointmentCode) details.appointmentCode = updates.appointmentCode;
                if (updates.depositStatus) {
                    details.deposit ??= { id: updates.depositId || null };
                    details.deposit.status = updates.depositStatus;
                } else if (newStatus === 'Cancelled' && details.deposit?.status === 'Verified') {
                    // A clinic cancellation moves a verified deposit to refund.
                    details.deposit.status = 'For Refund';
                }
                detailsButton.dataset.appointmentDetails = JSON.stringify(details);
            } catch (error) {
                console.warn('Unable to refresh cached appointment details.', error);
            }
        }

        // Replace actions from the old state with the actions allowed by the
        // newly confirmed state, without refreshing the appointment content.
        const actionGroup = row.querySelector('.vd-action-group');
        if (actionGroup && detailsButton && details) {
            actionGroup.replaceChildren(detailsButton);
            renderRowActions(actionGroup, details);
        }

        // Reapply the current client-side filter so a row whose status changed
        // disappears when it no longer matches, without a server reload.
        const table = row.closest('table');
        const toggleId = table?.id === 'upcomingApptTable' ? 'upcomingStatusToggles' : 'pastStatusToggles';
        document.querySelector(`#${toggleId} .vd-status-toggle-btn.active`)?.click();
    }

    function renderRowActions(actionGroup, details) {
        const statusPayload = {
            appointmentId: details.appointmentId,
            patientName: details.patientName,
            email: details.email,
            deposit: details.deposit
        };
        const addStatusAction = (label, className, status) => {
            actionGroup.append(makeActionButton(label, className, button => runStatusAction(button, statusPayload, status)));
        };
        const addDepositAction = (label, className, dataKey, handler) => {
            const button = makeActionButton(label, className, handler);
            button.dataset[dataKey] = details.appointmentId;
            actionGroup.append(button);
        };

        // This mirrors the server-rendered action map so each row remains fully
        // usable immediately after its status changes.
        if (details.status === 'Pending Review') {
            addStatusAction('Accept', 'btn vd-btn-gold btn-sm', 'Awaiting Deposit');
            addStatusAction('Reject', 'btn vd-btn-outline btn-sm', 'Rejected');
        } else if (details.status === 'Awaiting Deposit') {
            addDepositAction('Extend 8h', 'btn vd-btn-outline btn-sm', 'extendDeadline', button => runExtendDeadline(button));
            addDepositAction('Transfer Deposit', 'btn vd-btn-outline btn-sm', 'transferDeposit', button => runTransferDeposit(button));
            addStatusAction('Cancel', 'btn vd-btn-outline btn-sm', 'Cancelled');
        } else if (details.status === 'Confirmed') {
            addStatusAction('Cancel', 'btn vd-btn-outline btn-sm', 'Cancelled');
            addStatusAction('No-show', 'btn vd-btn-outline btn-sm', 'No-show');
        } else if (details.status === 'Checked In') {
            actionGroup.append(makeActionButton('Manage Queue', 'btn vd-btn-outline btn-sm', () => {
                document.querySelector('[data-page="dashboard-content.php"]')?.click();
            }));
        } else if (details.status === 'In Progress') {
            addStatusAction('Complete', 'btn vd-btn-gold btn-sm', 'Completed');
        } else if (details.status === 'Cancelled' && details.deposit?.status === 'For Refund') {
            addDepositAction('Record Refund', 'btn vd-btn-outline btn-sm', 'recordRefund', button => runRecordRefund(button));
        }
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

    function appendAppointmentDetail(container, label, value, valueClass = '') {
        const item = document.createElement('div');
        item.className = 'vd-appointment-detail-item';
        const term = document.createElement('span');
        term.textContent = label;
        const description = document.createElement('strong');
        if (valueClass) description.className = valueClass;
        description.textContent = value || 'Not provided';
        item.append(term, description);
        container.appendChild(item);
    }

    function makeActionButton(label, className, handler) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.textContent = label;
        button.addEventListener('click', () => handler(button));
        return button;
    }

    function renderPaymentDetails(payload) {
        const deposit = payload.deposit;
        const statusPill = document.getElementById('appointmentDepositStatus');
        const grid = document.getElementById('appointmentPaymentGrid');
        const note = document.getElementById('appointmentPaymentNote');
        const receiptWrap = document.getElementById('appointmentReceiptWrap');
        const receiptPreview = document.getElementById('appointmentReceiptPreview');
        const receiptFrame = document.getElementById('appointmentReceiptFrame');
        const receiptToggle = document.getElementById('toggleAppointmentReceipt');

        grid.replaceChildren();
        note.classList.add('d-none');
        note.textContent = '';
        receiptWrap.classList.add('d-none');
        receiptPreview.classList.add('d-none');
        receiptFrame.removeAttribute('src');
        receiptFrame.classList.remove('is-ready');
        receiptToggle.textContent = 'View Receipt';

        if (!deposit) {
            statusPill.className = 'vd-status';
            statusPill.textContent = 'No deposit yet';
            note.textContent = payload.status === 'Pending Review'
                ? 'Accept this appointment request before the patient can submit a deposit.'
                : 'No deposit record is linked to this appointment.';
            note.classList.remove('d-none');
            return;
        }

        statusPill.className = 'vd-status vd-status-' + String(deposit.status).toLowerCase().replace(/[^a-z0-9]+/g, '-');
        statusPill.textContent = deposit.status || 'Unknown';
        const amount = Number(deposit.amount || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
        appendAppointmentDetail(grid, 'Deposit amount', amount);
        appendAppointmentDetail(grid, 'GCash reference', deposit.reference || 'Not submitted');
        appendAppointmentDetail(grid, 'Submitted', deposit.submittedAt ? formatDateTime(deposit.submittedAt) : 'Not submitted');
        appendAppointmentDetail(grid, 'Payment deadline', deposit.deadlineAt ? formatDateTime(deposit.deadlineAt) : 'Not applicable');
        if (deposit.verifiedAt) appendAppointmentDetail(grid, 'Reviewed', formatDateTime(deposit.verifiedAt));
        if (deposit.verifiedBy) appendAppointmentDetail(grid, 'Reviewed by', [deposit.verifiedBy, deposit.verifiedByRole].filter(Boolean).join(' · '));
        if (payload.appointmentCode) appendAppointmentDetail(grid, 'Appointment code', payload.appointmentCode);

        const noteText = deposit.rejectionReason || deposit.refundReason || '';
        if (noteText) {
            note.textContent = noteText;
            note.classList.remove('d-none');
        }
        if (deposit.hasReceipt && deposit.receiptUrl) {
            receiptWrap.classList.remove('d-none');
            receiptToggle.dataset.receiptUrl = deposit.receiptUrl;
        } else {
            delete receiptToggle.dataset.receiptUrl;
        }
    }

    function renderModalActions(payload) {
        const actions = document.getElementById('appointmentModalActions');
        actions.replaceChildren();
        if (payload.status === 'Pending Review') {
            actions.append(
                makeActionButton('Accept', 'btn vd-btn-gold', button => runStatusAction(button, payload, 'Awaiting Deposit')),
                makeActionButton('Reject', 'btn vd-btn-outline', button => runStatusAction(button, payload, 'Rejected'))
            );
        } else if (payload.status === 'Payment Under Review' && payload.deposit?.id) {
            actions.append(
                makeActionButton('Approve Payment', 'btn vd-btn-gold', button => runPaymentReview(button, payload, 'verify')),
                makeActionButton('Reject Payment', 'btn vd-btn-outline', button => runPaymentReview(button, payload, 'reject'))
            );
        }
    }

    function openAppointmentDetails(payload) {
        const modalElement = document.getElementById('appointmentDetailsModal');
        const title = document.getElementById('appointmentDetailsTitle');
        const subtitle = document.getElementById('appointmentDetailsSubtitle');
        const detailGrid = document.getElementById('appointmentDetailGrid');
        const serviceList = document.getElementById('appointmentServiceList');
        const serviceCount = document.getElementById('appointmentServiceCount');
        const activityDetail = document.getElementById('appointmentActivityDetail');
        activeAppointmentPayload = payload;

        title.textContent = payload.patientName || 'Patient appointment';
        subtitle.textContent = [formatDateTime(payload.date, true), payload.clinic].filter(Boolean).join(' · ');
        detailGrid.replaceChildren();
        appendAppointmentDetail(detailGrid, 'Appointment number', `#${payload.appointmentId}`);
        appendAppointmentDetail(detailGrid, 'Status', payload.status, 'vd-appointment-detail-status');
        appendAppointmentDetail(detailGrid, 'Clinic', payload.clinic);
        appendAppointmentDetail(detailGrid, 'Date', formatDateTime(payload.date, true));
        appendAppointmentDetail(detailGrid, 'Email', payload.email);
        appendAppointmentDetail(detailGrid, 'Contact number', payload.phone);
        appendAppointmentDetail(detailGrid, 'Age', payload.age ? String(payload.age) : 'Not provided');
        appendAppointmentDetail(detailGrid, 'Gender', payload.gender);
        renderPaymentDetails(payload);
        renderModalActions(payload);

        const services = Array.isArray(payload.services) ? payload.services : [];
        serviceCount.textContent = `${services.length} service${services.length === 1 ? '' : 's'}`;
        serviceList.replaceChildren();
        if (!services.length) {
            const empty = document.createElement('div');
            empty.className = 'vd-empty-state vd-appointment-services-empty';
            empty.textContent = 'No services are linked to this appointment.';
            serviceList.appendChild(empty);
        } else {
            services.forEach(service => {
                const card = document.createElement('article');
                card.className = 'vd-appointment-service-card';

                const icon = document.createElement('span');
                icon.className = 'vd-appointment-service-icon';
                const iconGlyph = document.createElement('i');
                iconGlyph.className = service.icon || 'fa-solid fa-tooth';
                iconGlyph.setAttribute('aria-hidden', 'true');
                icon.appendChild(iconGlyph);

                const copy = document.createElement('span');
                copy.className = 'vd-appointment-service-copy';
                const category = document.createElement('span');
                category.className = 'vd-appointment-service-category';
                category.textContent = service.category || 'Dental service';
                const name = document.createElement('strong');
                name.textContent = service.name || 'Service';
                const description = document.createElement('small');
                description.textContent = service.description || 'No service description provided.';
                copy.append(category, name, description);

                const included = document.createElement('span');
                included.className = 'vd-appointment-service-included';
                const includedIcon = document.createElement('i');
                includedIcon.className = 'ti ti-check';
                includedIcon.setAttribute('aria-hidden', 'true');
                const includedText = document.createElement('span');
                includedText.textContent = 'Included';
                included.append(includedIcon, includedText);

                card.append(icon, copy, included);
                serviceList.appendChild(card);
            });
        }

        activityDetail.replaceChildren();
        if (payload.activity) {
            renderActivityCard(activityDetail, {
                performed_by_name: payload.activity.name,
                performed_by_role: payload.activity.role,
                performed_at: payload.activity.at
            }, payload.activity.status || payload.status);
        } else {
            const emptyActivity = document.createElement('div');
            emptyActivity.className = 'vd-activity-card vd-activity-empty';
            const emptyAvatar = document.createElement('span');
            emptyAvatar.className = 'vd-activity-avatar';
            emptyAvatar.textContent = '—';
            const emptyCopy = document.createElement('span');
            emptyCopy.className = 'vd-activity-copy';
            const emptyName = document.createElement('span');
            emptyName.className = 'vd-activity-name';
            emptyName.textContent = 'No audit history';
            const emptyMeta = document.createElement('span');
            emptyMeta.className = 'vd-activity-meta';
            emptyMeta.textContent = 'No status change recorded';
            emptyCopy.append(emptyName, emptyMeta);
            emptyActivity.append(emptyAvatar, emptyCopy);
            activityDetail.appendChild(emptyActivity);
        }

        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }

    document.querySelectorAll('.vd-appointment-details-btn').forEach(button => {
        button.addEventListener('click', () => {
            try {
                openAppointmentDetails(JSON.parse(button.dataset.appointmentDetails));
            } catch (error) {
                showToast('Unable to open appointment details.', false);
                console.error(error);
            }
        });
    });

    // ── Generic status + day-range filter, reused for Upcoming and Past tables ──
    // Date keys are 'YYYY-MM-DD' strings, which compare correctly with <= and >= directly.
    function setupTableFilter(tableId, statusToggleId, dateFromId, dateToId, clearBtnId, countLabelId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const statusToggle = document.getElementById(statusToggleId);
        const dateFrom      = document.getElementById(dateFromId);
        const dateTo        = document.getElementById(dateToId);
        const clearBtn       = document.getElementById(clearBtnId);
        const countLabel     = document.getElementById(countLabelId);
        const rows            = table.querySelectorAll('tbody tr');
        const totalCount       = rows.length;

        function applyFilter() {
            const status = statusToggle.querySelector('.vd-status-toggle-btn.active')?.dataset.status || '';
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
            table.dispatchEvent(new CustomEvent('ventura:table-filtered'));
        }

        statusToggle.querySelectorAll('.vd-status-toggle-btn').forEach(button => {
            button.addEventListener('click', () => {
                statusToggle.querySelectorAll('.vd-status-toggle-btn').forEach(item => item.classList.remove('active'));
                button.classList.add('active');
                applyFilter();
            });
        });
        dateFrom.addEventListener('change', applyFilter);
        dateTo.addEventListener('change', applyFilter);

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                statusToggle.querySelectorAll('.vd-status-toggle-btn').forEach(item => item.classList.toggle('active', item.dataset.status === ''));
                dateFrom.value    = '';
                dateTo.value      = '';
                applyFilter();
            });
        }
    }

    setupTableFilter('upcomingApptTable', 'upcomingStatusToggles', 'filterDateFromUpcoming', 'filterDateToUpcoming', 'clearUpcomingFilters', 'upcomingCountLabel');
    setupTableFilter('pastApptTable', 'pastStatusToggles', 'filterDateFromPast', 'filterDateToPast', 'clearPastFilters', 'pastCountLabel');
    const requestedStatusFilter = sessionStorage.getItem('venturaAppointmentStatusFilter');
    if (requestedStatusFilter) {
        sessionStorage.removeItem('venturaAppointmentStatusFilter');
        document.querySelector(`#upcomingStatusToggles [data-status="${CSS.escape(requestedStatusFilter)}"]`)?.click();
    }

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

    async function runStatusAction(button, payload, newStatus) {
        const id = payload.appointmentId;
        const name = payload.patientName || `Appointment #${id}`;
        let reason = '';
        if (newStatus === 'Rejected') {
            const rejection = await window.showActionModal({
                title: 'Reject Appointment Request', kicker: 'Appointment review',
                message: 'The patient will receive this reason by email. Please keep it clear and professional.',
                confirmText: 'Reject Appointment', icon: 'ti-calendar-x', tone: 'danger',
                details: [{ label: 'Patient', value: name }],
                fields: [{ name: 'reason', label: 'Reason for rejection', placeholder: 'Example: The selected schedule is no longer available.', multiline: true, rows: 3, required: true, minlength: 3, maxlength: 255 }]
            });
            if (!rejection.confirmed) return;
            reason = rejection.values.reason;
        } else {
            const labels = {
                'Awaiting Deposit': ['Accept Appointment Request', 'Accept Appointment', 'The patient will be asked to submit the required deposit.'],
                'Cancelled': ['Cancel Appointment', 'Cancel Appointment', 'This action updates the appointment and notifies the patient.'],
                'No-show': ['Mark Patient as No-show', 'Mark No-show', 'The verified deposit will be marked as forfeited.'],
                'In Progress': ['Start Treatment', 'Start Treatment', 'Confirm that the patient profile and check-in are ready.'],
                'Completed': ['Complete Appointment', 'Mark Completed', 'Confirm that treatment for this appointment is complete.']
            };
            const copy = labels[newStatus] || ['Update Appointment', 'Confirm', `Change this appointment to ${newStatus}.`];
            const confirmation = await window.showActionModal({
                title: copy[0], kicker: 'Appointment action', message: copy[2], confirmText: copy[1],
                icon: newStatus === 'Awaiting Deposit' ? 'ti-calendar-check' : 'ti-calendar-cog',
                tone: ['Cancelled', 'No-show'].includes(newStatus) ? 'warning' : 'success',
                details: [{ label: 'Patient', value: name }]
            });
            if (!confirmation.confirmed) return;
        }

        LoadingUI.setButton(button, true, 'Saving...');
        const formData = new FormData();
        formData.append('action', 'updateStatus');
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('appointment_id', id);
        formData.append('status', newStatus);
        formData.append('reason', reason);
        if (['Awaiting Deposit', 'Rejected', 'Cancelled'].includes(newStatus)) {
            formData.append('email', payload.email || '');
            formData.append('name', name);
        }
        try {
            const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'Failed to update the appointment.');
            applyAppointmentResult(id, result.appointment?.status || newStatus, result.audit);
            deliverQueuedNotification(result.notification);
            bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal'))?.hide();
            showToast(result.message || `Status updated to ${newStatus}.`, true);
        } catch (error) {
            LoadingUI.setButton(button, false);
            showToast(error.message || 'Unable to update the appointment.', false);
        }
    }

    async function runPaymentReview(button, payload, action) {
        const approving = action === 'verify';
        const confirmation = await window.showActionModal({
            title: approving ? 'Approve GCash Deposit' : 'Reject Payment Receipt',
            kicker: 'Payment verification',
            message: approving
                ? 'Confirm that this payment appears in the clinic’s GCash account. The appointment will be confirmed and receive a check-in code.'
                : 'The patient will see the reason and receive more time to submit a replacement receipt.',
            confirmText: approving ? 'Approve Payment' : 'Reject Payment',
            icon: approving ? 'ti-receipt-check' : 'ti-receipt-off',
            tone: approving ? 'success' : 'danger',
            fields: approving ? [] : [{ name: 'reason', label: 'Reason shown to the patient', multiline: true, rows: 3, required: true, minlength: 3, maxlength: 255 }]
        });
        if (!confirmation.confirmed) return;
        LoadingUI.setButton(button, true, approving ? 'Confirming payment…' : 'Rejecting payment…');
        try {
            const fields = { deposit_id: payload.deposit.id };
            if (!approving) fields.reason = confirmation.values.reason;
            const result = await depositAction(action, fields, false);
            applyAppointmentResult(
                payload.appointmentId,
                result.appointment?.status || (approving ? 'Confirmed' : 'Awaiting Deposit'),
                result.audit,
                { appointmentCode: result.appointment_code, depositStatus: result.deposit_status }
            );
            bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal'))?.hide();
            const message = result.notification
                ? (approving
                    ? 'Payment verified. Patient notification scheduled.'
                    : 'Payment rejected. Patient notification scheduled.')
                : result.message;
            showToast(message, true);
        } catch (error) {
            LoadingUI.setButton(button, false);
            showToast(error.message, false);
        }
    }

    document.querySelectorAll('[data-status-action]').forEach(button => {
        button.addEventListener('click', () => runStatusAction(button, {
            appointmentId: button.dataset.appointmentId,
            patientName: button.dataset.name,
            email: button.dataset.email
        }, button.dataset.statusAction));
    });

    document.querySelectorAll('[data-open-today-queue]').forEach(button => button.addEventListener('click', () => {
        document.querySelector('[data-page="dashboard-content.php"]')?.click();
    }));

    document.getElementById('toggleAppointmentReceipt')?.addEventListener('click', function () {
        const preview = document.getElementById('appointmentReceiptPreview');
        const frame = document.getElementById('appointmentReceiptFrame');
        const loading = document.getElementById('appointmentReceiptLoading');
        const opening = preview.classList.contains('d-none');
        preview.classList.toggle('d-none', !opening);
        this.textContent = opening ? 'Hide Receipt' : 'View Receipt';
        if (opening && !frame.getAttribute('src') && this.dataset.receiptUrl) {
            loading.classList.remove('d-none');
            frame.classList.remove('is-ready');
            frame.src = this.dataset.receiptUrl;
        }
    });

    document.getElementById('appointmentReceiptFrame')?.addEventListener('load', function () {
        document.getElementById('appointmentReceiptLoading')?.classList.add('d-none');
        this.classList.add('is-ready');
    });

    document.getElementById('appointmentDetailsModal')?.addEventListener('hidden.bs.modal', () => {
        const frame = document.getElementById('appointmentReceiptFrame');
        frame?.removeAttribute('src');
        frame?.classList.remove('is-ready');
        activeAppointmentPayload = null;
    });

    async function depositAction(action, fields, showSuccess = true) {
        const body=new FormData();body.append('action',action);body.append('csrf_token',CSRF_TOKEN);Object.entries(fields).forEach(([key,value])=>body.append(key,value));
        const response=await fetch(DEPOSIT_CONTROLLER,{method:'POST',body});const result=await response.json();if(!result.success)throw new Error(result.message);deliverQueuedNotification(result.notification);if(showSuccess)showToast(result.message,true);return result;
    }
    async function runExtendDeadline(button) {
        const response = await window.showActionModal({
            title: 'Extend Payment Deadline',
            kicker: 'Deposit deadline',
            message: <?= json_encode('Grant the patient another ' . $configuredDeadlineLabel . ' to submit the ' . $configuredDepositLabel . ' deposit.') ?>,
            confirmText: 'Extend Deadline',
            icon: 'ti-clock-plus',
            tone: 'warning',
            fields: [{ name: 'reason', label: 'Reason for extension', placeholder: 'Enter the reason for granting more time.', multiline: true, rows: 3, required: true, minlength: 3, maxlength: 255 }]
        });
        if (!response.confirmed) return;
        try { await depositAction('extend', { appointment_id: button.dataset.extendDeadline, reason: response.values.reason }); }
        catch (error) { showToast(error.message, false); }
    }

    async function runTransferDeposit(button) {
        const response = await window.showActionModal({
            title: 'Transfer Verified Deposit',
            kicker: 'Deposit adjustment',
            message: 'Move an existing verified deposit to this appointment. Both appointments will retain an audit record of the transfer.',
            confirmText: 'Transfer Deposit',
            icon: 'ti-transfer',
            tone: 'warning',
            details: [{ label: 'New booking', value: `Appointment #${button.dataset.transferDeposit}` }],
            fields: [
                { name: 'source', label: 'Original appointment number', placeholder: 'Enter the appointment number with the verified deposit.', type: 'number', required: true },
                { name: 'reason', label: 'Transfer reason', value: 'Patient requested a new appointment.', multiline: true, rows: 2, required: true, minlength: 3, maxlength: 255 }
            ]
        });
        if (!response.confirmed) return;
        try {
            const result = await depositAction('transfer', { source_appointment_id: response.values.source, target_appointment_id: button.dataset.transferDeposit, reason: response.values.reason });
            applyAppointmentResult(
                button.dataset.transferDeposit,
                result.appointment?.status || 'Confirmed',
                result.audit,
                { appointmentCode: result.appointment_code, depositStatus: result.deposit_status }
            );
        } catch (error) { showToast(error.message, false); }
    }

    async function runRecordRefund(button) {
        const response = await window.showActionModal({
            title: 'Record Deposit Refund',
            kicker: 'Manual refund record',
            message: 'Use this only after the clinic has returned the deposit outside the system.',
            confirmText: 'Record Refund',
            icon: 'ti-cash-banknote-off',
            tone: 'warning',
            fields: [{ name: 'notes', label: 'Refund notes (optional)', placeholder: 'Add the refund method or other useful details.', multiline: true, rows: 3, maxlength: 255 }]
        });
        if (!response.confirmed) return;
        try {
            await depositAction('refund', { appointment_id: button.dataset.recordRefund, notes: response.values.notes });
            button.remove();
        }
        catch (error) { showToast(error.message, false); }
    }

    document.querySelectorAll('[data-extend-deadline]').forEach(button => button.addEventListener('click', () => runExtendDeadline(button)));
    document.querySelectorAll('[data-transfer-deposit]').forEach(button => button.addEventListener('click', () => runTransferDeposit(button)));
    document.querySelectorAll('[data-record-refund]').forEach(button => button.addEventListener('click', () => runRecordRefund(button)));

})();
</script>
