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
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$upcoming = $appointmentModel->getAllUpcomingWithStatus();
$past     = $appointmentModel->getAdminPastAppointments();
$appointmentIds = array_merge(
    array_column($upcoming, 'appointment_id'),
    array_column($past, 'appointment_id')
);
$servicesByAppointment = $appointmentModel->getServiceDetailsForAppointments($appointmentIds);

function allowedStatusOptions($current) {
    $transitions = [
        'Pending Review' => ['Awaiting Deposit', 'Rejected'],
        'Awaiting Deposit' => ['Cancelled'],
        'Confirmed' => ['Cancelled', 'No-show'],
        'Checked In' => ['In Progress'],
        'In Progress' => ['Completed'],
    ];
    return array_values(array_unique(array_merge([$current], $transitions[$current] ?? [])));
}

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
                            data-appointment-details="<?= appointmentDetailsPayload($appt, $servicesByAppointment[(int) $appt['appointment_id']] ?? []) ?>">
                            <i class="ti ti-eye" aria-hidden="true"></i>
                        </button>
                        <select class="vd-status-select"
                            data-id="<?= $appt['appointment_id'] ?>"
                            data-original="<?= htmlspecialchars($appt['status']) ?>"
                            data-email="<?= htmlspecialchars($appt['email']) ?>"
                            data-name="<?= htmlspecialchars($appt['firstname'] . ' ' . $appt['lastname']) ?>">
                            <?php foreach (allowedStatusOptions($appt['status']) as $s): ?>
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
                        <?php if ($appt['status'] === 'Awaiting Deposit'): ?>
                        <button type="button" class="btn vd-btn-outline btn-sm" data-extend-deadline="<?= (int)$appt['appointment_id'] ?>">Extend 8h</button>
                        <button type="button" class="btn vd-btn-outline btn-sm" data-transfer-deposit="<?= (int)$appt['appointment_id'] ?>">Transfer Deposit</button>
                        <?php elseif ($appt['status'] === 'Cancelled'): ?>
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

                <section class="vd-appointment-activity-section" aria-labelledby="appointmentActivityHeading">
                    <h6 class="vd-appointment-details-section-title" id="appointmentActivityHeading">Latest activity</h6>
                    <div id="appointmentActivityDetail"></div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const CONTROLLER = '../../../apps/controllers/appointmentController.php';

    function showToast(msg, success) {
        // Prefer the global showToast provided by the dashboard shell.
        if (typeof window.showToast === 'function') { window.showToast(msg, success); return; }
        // No global toast available (partial may be loaded standalone). Log instead.
        console.warn('showToast not available:', msg);
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

    function openAppointmentDetails(payload) {
        const modalElement = document.getElementById('appointmentDetailsModal');
        const title = document.getElementById('appointmentDetailsTitle');
        const subtitle = document.getElementById('appointmentDetailsSubtitle');
        const detailGrid = document.getElementById('appointmentDetailGrid');
        const serviceList = document.getElementById('appointmentServiceList');
        const serviceCount = document.getElementById('appointmentServiceCount');
        const activityDetail = document.getElementById('appointmentActivityDetail');

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
        let reason = '';
        if (newStatus === 'Rejected') {
            const rejection = await window.showActionModal({
                title: 'Reject Appointment Request',
                kicker: 'Appointment review',
                message: 'The patient will receive this reason by email. Please keep it clear and professional.',
                confirmText: 'Reject Appointment',
                icon: 'ti-calendar-x',
                tone: 'danger',
                details: [{ label: 'Patient', value: name }],
                fields: [{
                    name: 'reason',
                    label: 'Reason for rejection',
                    placeholder: 'Example: The selected schedule is no longer available.',
                    multiline: true,
                    rows: 3,
                    required: true,
                    minlength: 3,
                    maxlength: 255
                }]
            });
            if (!rejection.confirmed) return;
            reason = rejection.values.reason;
        }

        LoadingUI.setButton(btn, true, 'Saving…');
        const formData = new FormData();
        formData.append('action', 'updateStatus');
        formData.append('csrf_token', <?= json_encode($_SESSION['csrf_token']) ?>);
        formData.append('appointment_id', id);
        formData.append('status', newStatus);
        formData.append('email', email);
        formData.append('name', name);
        formData.append('reason', reason);

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

    async function depositAction(action, fields) {
        const body=new FormData();body.append('action',action);body.append('csrf_token',<?= json_encode($_SESSION['csrf_token']) ?>);Object.entries(fields).forEach(([key,value])=>body.append(key,value));
        const response=await fetch('../../../apps/controllers/depositController.php',{method:'POST',body});const result=await response.json();if(!result.success)throw new Error(result.message);showToast(result.message,true);return result;
    }
    document.querySelectorAll('[data-extend-deadline]').forEach(button => button.addEventListener('click', async () => {
        const response = await window.showActionModal({
            title: 'Extend Payment Deadline',
            kicker: 'Deposit deadline',
            message: 'Grant the patient another eight hours to submit the ₱400 deposit.',
            confirmText: 'Extend Deadline',
            icon: 'ti-clock-plus',
            tone: 'warning',
            fields: [{ name: 'reason', label: 'Reason for extension', placeholder: 'Enter the reason for granting more time.', multiline: true, rows: 3, required: true, minlength: 3, maxlength: 255 }]
        });
        if (!response.confirmed) return;
        try { await depositAction('extend', { appointment_id: button.dataset.extendDeadline, reason: response.values.reason }); }
        catch (error) { showToast(error.message, false); }
    }));

    document.querySelectorAll('[data-transfer-deposit]').forEach(button => button.addEventListener('click', async () => {
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
            await depositAction('transfer', { source_appointment_id: response.values.source, target_appointment_id: button.dataset.transferDeposit, reason: response.values.reason });
            document.querySelector('[data-page="appointment-content.php"]')?.click();
        } catch (error) { showToast(error.message, false); }
    }));

    document.querySelectorAll('[data-record-refund]').forEach(button => button.addEventListener('click', async () => {
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
        try { await depositAction('refund', { appointment_id: button.dataset.recordRefund, notes: response.values.notes }); }
        catch (error) { showToast(error.message, false); }
    }));

})();
</script>
