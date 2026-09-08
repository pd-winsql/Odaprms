<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$scheduleReadOnly = !empty($scheduleReadOnly);

if (!isset($_SESSION['user_id']) || (
    ($_SESSION['user_role'] ?? '') !== 'Dental Assistant'
    && !($scheduleReadOnly && ($_SESSION['user_role'] ?? '') === 'Admin')
)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/scheduleModel.php';
require_once __DIR__ . '/../../../models/clinicModel.php';

$db = new Database();
$conn = $db->connect();
$scheduleModel = new Schedule($conn);
$clinicModel = new Clinic($conn);
$clinics = $clinicModel->getAllClinics();
$schedulesByClinic = [];
$scheduleSummaryByClinic = [];
foreach ($clinics as $clinic) {
    $clinicSchedules = $scheduleModel->getAvailableSchedulesByClinic($clinic['clinic_id']);
    $clinicId = (int) $clinic['clinic_id'];
    $clinicCapacity = 0;
    $clinicBooked = 0;
    $schedulesByClinic[$clinicId] = $clinicSchedules;
    foreach ($clinicSchedules as $schedule) {
        $clinicCapacity += (int) $schedule['max_appointments'];
        $clinicBooked += (int) $schedule['total_appointments'];
    }
    $scheduleSummaryByClinic[$clinicId] = [
        'upcoming' => count($clinicSchedules),
        'capacity' => $clinicCapacity,
        'booked' => $clinicBooked,
        'available' => max(0, $clinicCapacity - $clinicBooked),
    ];
}
$scheduleIds = [];
foreach ($schedulesByClinic as $clinicSchedules) {
    $scheduleIds = array_merge($scheduleIds, array_column($clinicSchedules, 'schedule_id'));
}
$scheduleAppointmentsBySchedule = $scheduleReadOnly
    ? $scheduleModel->getActiveAppointmentsByScheduleIds($scheduleIds)
    : $scheduleModel->getConfirmedAppointmentsByScheduleIds($scheduleIds);
$scheduleRosterLabel = $scheduleReadOnly ? 'booked' : 'confirmed';
$firstClinic = $clinics[0] ?? null;
$activeSummary = $firstClinic
    ? $scheduleSummaryByClinic[(int) $firstClinic['clinic_id']]
    : ['upcoming' => 0, 'capacity' => 0, 'booked' => 0, 'available' => 0];

// A clinic can only have one window per date. The other clinic may use that
// same date when its time window preserves the transition interval.
$occupiedScheduleDatesByClinic = [];
$scheduleWindows = [];
$clinicNamesById = [];
foreach ($clinics as $clinic) {
    $clinicNamesById[(int) $clinic['clinic_id']] = $clinic['clinic_name'];
}
foreach ($schedulesByClinic as $clinicId => $clinicSchedules) {
    $occupiedScheduleDatesByClinic[$clinicId] = array_values(array_unique(array_column($clinicSchedules, 'sched_date')));
    foreach ($clinicSchedules as $schedule) {
        $scheduleWindows[] = [
            'schedule_id' => (int) $schedule['schedule_id'],
            'clinic_id' => (int) $clinicId,
            'clinic_name' => $clinicNamesById[(int) $clinicId] ?? 'Another clinic',
            'sched_date' => $schedule['sched_date'],
            'start_time' => substr($schedule['start_time'], 0, 5),
            'end_time' => substr($schedule['end_time'], 0, 5),
        ];
    }
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
?>

<div class="d-flex flex-column gap-4 <?= $scheduleReadOnly ? 'vd-upcoming-overview' : '' ?>">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <p class="text-muted small mb-0"><?= $scheduleReadOnly
                ? 'Review upcoming clinic schedules and open each roster to see booked patients and their services.'
                : 'Create appointment dates, review remaining availability, and adjust each schedule’s capacity.' ?></p>
            <?php if (!$scheduleReadOnly): ?>
            <button type="button" class="btn vd-btn-gold align-self-start" id="addScheduleForActiveClinic"
                data-bs-toggle="modal" data-bs-target="#addScheduleModal"
                data-schedule-mode="add"
                data-clinic-id="<?= (int) ($firstClinic['clinic_id'] ?? 0) ?>"
                data-clinic-name="<?= htmlspecialchars($firstClinic['clinic_name'] ?? '', ENT_QUOTES) ?>"
                data-default-start="<?= htmlspecialchars(substr($firstClinic['default_start_time'] ?? '08:00:00', 0, 5)) ?>"
                data-default-end="<?= htmlspecialchars(substr($firstClinic['default_end_time'] ?? '17:00:00', 0, 5)) ?>"
                <?= $firstClinic ? '' : 'disabled' ?>>
                <i class="ti ti-calendar-plus me-1"></i> Add Schedule
            </button>
            <?php endif; ?>
        </div>

        <div class="vd-clinic-switch" role="tablist" aria-label="Schedule clinic">
            <?php foreach ($clinics as $index => $clinic): ?>
            <?php $summary = $scheduleSummaryByClinic[(int) $clinic['clinic_id']]; ?>
            <button type="button" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                class="vd-clinic-switch-btn vd-schedule-clinic-btn <?= $index === 0 ? 'active' : '' ?>"
                data-clinic-id="<?= (int) $clinic['clinic_id'] ?>"
                data-clinic-name="<?= htmlspecialchars($clinic['clinic_name'], ENT_QUOTES) ?>"
                data-default-start="<?= htmlspecialchars(substr($clinic['default_start_time'] ?? '08:00:00', 0, 5)) ?>"
                data-default-end="<?= htmlspecialchars(substr($clinic['default_end_time'] ?? '17:00:00', 0, 5)) ?>"
                data-upcoming="<?= $summary['upcoming'] ?>"
                data-capacity="<?= $summary['capacity'] ?>"
                data-booked="<?= $summary['booked'] ?>"
                data-available="<?= $summary['available'] ?>">
                <i class="ti ti-building-hospital"></i> <?= htmlspecialchars($clinic['clinic_name']) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="vd-schedule-summary-grid" aria-live="polite">
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-calendar-event"></i></span><span><small>Upcoming Dates</small><strong id="scheduleSummaryUpcoming"><?= $activeSummary['upcoming'] ?></strong></span></div>
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-users"></i></span><span><small>Total Capacity</small><strong id="scheduleSummaryCapacity"><?= $activeSummary['capacity'] ?></strong></span></div>
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-user-check"></i></span><span><small>Booked Slots</small><strong id="scheduleSummaryBooked"><?= $activeSummary['booked'] ?></strong></span></div>
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-armchair"></i></span><span><small>Available Slots</small><strong id="scheduleSummaryAvailable"><?= $activeSummary['available'] ?></strong></span></div>
        </div>

        <!-- Schedule Overview -->
        <?php foreach ($clinics as $clinicIndex => $clinic):
            $schedules = $schedulesByClinic[(int) $clinic['clinic_id']] ?? [];
        ?>
        <div role="tabpanel" class="vd-dash-card vd-schedule-clinic-panel <?= $clinicIndex === 0 ? '' : 'd-none' ?>" data-clinic-panel="<?= (int) $clinic['clinic_id'] ?>">
            <div class="vd-dash-card-header">
                <span class="vd-dash-card-title">
                    <i class="ti ti-building me-1"></i>
                    <?= htmlspecialchars($clinic['clinic_name']) ?>
                </span>
                <span class="vd-topbar-date"><?= count($schedules) ?> upcoming date<?= count($schedules) === 1 ? '' : 's' ?></span>
            </div>
            <div class="vd-dash-card-body">
                <?php if (empty($schedules)): ?>
                    <div class="vd-empty-state">No schedules yet.</div>
                <?php else: ?>
                    <div class="vd-sched-grid">
                        <?php foreach ($schedules as $sched):
                            $d      = new DateTime($sched['sched_date']);
                            $isPast = $d < new DateTime('today');
                            $booked = (int) $sched['total_appointments'];
                            $capacity = (int) $sched['max_appointments'];
                            $available = max(0, (int) $sched['available_slots']);
                            $usagePercent = $capacity > 0 ? min(100, (int) round(($booked / $capacity) * 100)) : 0;
                            $timeRange = Schedule::formatTimeRange($sched['start_time'], $sched['end_time']);
                            $scheduleAppointments = $scheduleAppointmentsBySchedule[(int) $sched['schedule_id']] ?? [];
                        ?>
                        <div class="vd-sched-card <?= $isPast ? 'past' : '' ?>"
                            id="schedCard-<?= $sched['schedule_id'] ?>" data-booked="<?= $booked ?>" data-capacity="<?= $capacity ?>"
                            data-clinic-id="<?= (int) $clinic['clinic_id'] ?>" data-date="<?= htmlspecialchars($sched['sched_date']) ?>"
                            data-start-time="<?= htmlspecialchars(substr($sched['start_time'], 0, 5)) ?>" data-end-time="<?= htmlspecialchars(substr($sched['end_time'], 0, 5)) ?>">

                            <div class="vd-sched-card-view">
                                <div class="vd-sched-date">
                                <span class="vd-sched-dayname"><?= $d->format('D') ?></span>
                                <span class="vd-sched-daynum"><?= $d->format('d') ?></span>
                                <span class="vd-sched-month"><?= $d->format('M Y') ?></span>
                                </div>
                                <div class="vd-sched-window"><i class="ti ti-clock" aria-hidden="true"></i><span><?= htmlspecialchars($timeRange) ?></span></div>
                                <span class="vd-sched-slots" id="slots-<?= $sched['schedule_id'] ?>">
                                <?= $available ?> available
                                </span>
                                <div class="vd-sched-capacity">
                                    <span id="usage-<?= $sched['schedule_id'] ?>"><?= $booked ?> booked of <?= $capacity ?></span>
                                    <div class="vd-sched-capacity-track"><span id="progress-<?= $sched['schedule_id'] ?>" style="width:<?= $usagePercent ?>%"></span></div>
                                </div>
                                <?php if (!$isPast && !$scheduleReadOnly): ?>
                                <div class="vd-sched-actions">
                                <button type="button" class="vd-sched-btn vd-edit-sched-btn"
                                    data-bs-toggle="modal" data-bs-target="#addScheduleModal" data-schedule-mode="edit"
                                    data-id="<?= $sched['schedule_id'] ?>"
                                    data-clinic-id="<?= (int) $clinic['clinic_id'] ?>"
                                    data-clinic-name="<?= htmlspecialchars($clinic['clinic_name'], ENT_QUOTES) ?>"
                                    data-booked="<?= $booked ?>"
                                    data-date="<?= htmlspecialchars($sched['sched_date']) ?>"
                                    data-start-time="<?= htmlspecialchars(substr($sched['start_time'], 0, 5)) ?>"
                                    data-end-time="<?= htmlspecialchars(substr($sched['end_time'], 0, 5)) ?>"
                                    data-max="<?= $sched['max_appointments'] ?>"
                                    title="Edit schedule" aria-label="Edit this schedule">
                                    <i class="ti ti-pencil" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="vd-sched-btn vd-delete-btn"
                                    data-id="<?= $sched['schedule_id'] ?>"
                                    <?= $booked > 0 ? 'disabled' : '' ?>
                                    title="<?= $booked > 0 ? 'Schedules with bookings cannot be deleted' : 'Delete schedule' ?>"
                                    aria-label="<?= $booked > 0 ? 'Cannot delete this schedule because it has bookings' : 'Delete this schedule' ?>">
                                    <i class="ti ti-trash" aria-hidden="true"></i>
                                </button>
                                </div>
                                <?php endif; ?>
                                <button type="button" class="btn <?= $scheduleAppointments ? 'vd-btn-gold' : 'vd-btn-outline' ?> vd-sched-patients-button"
                                    data-view-schedule-patients="<?= (int) $sched['schedule_id'] ?>"
                                    data-schedule-label="<?= htmlspecialchars($clinic['clinic_name'] . ' · ' . $d->format('M j, Y'), ENT_QUOTES) ?>"
                                    data-schedule-window="<?= htmlspecialchars($timeRange, ENT_QUOTES) ?>"
                                    aria-label="View <?= count($scheduleAppointments) ?> <?= $scheduleRosterLabel ?> patient<?= count($scheduleAppointments) === 1 ? '' : 's' ?>"
                                    <?= $scheduleAppointments ? '' : 'disabled' ?>>
                                    <i class="ti ti-users" aria-hidden="true"></i>
                                    <span>View Patients</span>
                                    <strong><?= count($scheduleAppointments) ?></strong>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
</div>

<!-- Patients booked for one schedule -->
<div class="modal fade vd-schedule-patients-modal" id="schedulePatientsModal" tabindex="-1"
    aria-labelledby="schedulePatientsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-appointment-details-kicker">Schedule roster</div>
                    <h5 class="modal-title vd-modal-title" id="schedulePatientsModalTitle"><?= $scheduleReadOnly ? 'Booked patients' : 'Confirmed patients' ?></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="vd-schedule-roster-context">
                    <span class="vd-schedule-roster-context-icon"><i class="ti ti-calendar-event" aria-hidden="true"></i></span>
                    <span class="vd-schedule-roster-context-copy">
                        <strong id="schedulePatientsModalSubtitle"></strong>
                        <small id="schedulePatientsModalWindow"></small>
                    </span>
                    <span class="vd-schedule-roster-count" id="schedulePatientsModalCount"></span>
                </div>
                <div class="vd-schedule-patient-modal-list" id="schedulePatientsModalList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Full details for the selected appointment -->
<div class="modal fade vd-appointment-details-modal vd-schedule-appointment-modal" id="scheduleAppointmentModal" tabindex="-1"
    aria-labelledby="scheduleAppointmentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content vd-modal-content">
            <div class="modal-header vd-schedule-appointment-header">
                <div class="vd-schedule-appointment-identity">
                    <span class="vd-schedule-appointment-avatar" id="scheduleAppointmentAvatar">?</span>
                    <span>
                        <span class="vd-appointment-details-kicker">Appointment details</span>
                        <h5 class="modal-title vd-modal-title" id="scheduleAppointmentModalTitle">Patient appointment</h5>
                        <p class="vd-appointment-details-subtitle mb-0" id="scheduleAppointmentModalSubtitle"></p>
                    </span>
                </div>
                <span class="vd-schedule-confirmed-mark" id="scheduleAppointmentStatusMark" data-status="confirmed"><i class="ti ti-circle-check-filled" aria-hidden="true"></i><span>Confirmed</span></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="vd-schedule-visit-banner">
                    <span class="vd-schedule-visit-date-icon"><i class="ti ti-calendar" aria-hidden="true"></i></span>
                    <span class="vd-schedule-visit-date">
                        <small>Visit date</small>
                        <strong id="scheduleAppointmentVisitDate"></strong>
                    </span>
                    <span class="vd-schedule-visit-divider" aria-hidden="true"></span>
                    <span class="vd-schedule-visit-meta">
                        <span><i class="ti ti-clock" aria-hidden="true"></i><span id="scheduleAppointmentVisitTime"></span></span>
                        <span><i class="ti ti-building-hospital" aria-hidden="true"></i><span id="scheduleAppointmentVisitClinic"></span></span>
                    </span>
                </div>

                <div class="vd-schedule-detail-columns">
                    <section class="vd-schedule-detail-section" aria-labelledby="scheduleAppointmentRecordHeading">
                        <h6 class="vd-appointment-details-section-title" id="scheduleAppointmentRecordHeading">Appointment record</h6>
                        <dl class="vd-schedule-detail-list" id="scheduleAppointmentRecordList"></dl>
                    </section>
                    <section class="vd-schedule-detail-section" aria-labelledby="schedulePatientInformationHeading">
                        <h6 class="vd-appointment-details-section-title" id="schedulePatientInformationHeading">Patient information</h6>
                        <dl class="vd-schedule-detail-list" id="schedulePatientInformationList"></dl>
                    </section>
                </div>

                <section class="vd-schedule-detail-section vd-schedule-services-section" aria-labelledby="scheduleAppointmentServicesHeading">
                    <h6 class="vd-appointment-details-section-title" id="scheduleAppointmentServicesHeading">Selected services</h6>
                    <div class="vd-schedule-appointment-services" id="scheduleAppointmentServices"></div>
                </section>
                <section class="vd-schedule-detail-section vd-schedule-deposit-section" aria-labelledby="scheduleAppointmentPaymentHeading">
                    <h6 class="vd-appointment-details-section-title" id="scheduleAppointmentPaymentHeading">Deposit information</h6>
                    <dl class="vd-schedule-detail-list vd-schedule-deposit-list" id="scheduleAppointmentPaymentList"></dl>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn vd-btn-outline me-auto" id="backToSchedulePatients">
                    <i class="ti ti-arrow-left me-1" aria-hidden="true"></i> Back to patients
                </button>
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if (!$scheduleReadOnly): ?>
<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content vd-confirm-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title vd-modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete this schedule? This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="vd-btn-outline btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="vd-btn-gold btn" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
    const scheduleAppointmentsBySchedule = <?= json_encode(
        $scheduleAppointmentsBySchedule,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const scheduleRosterLabel = <?= json_encode($scheduleRosterLabel) ?>;
    function refreshPage() {
        if (typeof loadpage === 'function') loadpage(<?= json_encode($scheduleReadOnly ? 'upcoming-appointments-content.php' : 'schedule-content.php') ?>);
    }
    window.refreshSchedulePage = refreshPage;

    const clinicButtons = Array.from(document.querySelectorAll('.vd-schedule-clinic-btn'));
    const clinicPanels = Array.from(document.querySelectorAll('.vd-schedule-clinic-panel'));
    const addScheduleButton = document.getElementById('addScheduleForActiveClinic');
    const summaryFields = {
        upcoming: document.getElementById('scheduleSummaryUpcoming'),
        capacity: document.getElementById('scheduleSummaryCapacity'),
        booked: document.getElementById('scheduleSummaryBooked'),
        available: document.getElementById('scheduleSummaryAvailable')
    };

    function updateScheduleSummary(button) {
        Object.entries(summaryFields).forEach(([key, field]) => {
            if (field) field.textContent = button.dataset[key] || '0';
        });
    }

    clinicButtons.forEach(button => button.addEventListener('click', () => {
        clinicButtons.forEach(item => {
            const isActive = item === button;
            item.classList.toggle('active', isActive);
            item.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        clinicPanels.forEach(panel => panel.classList.toggle('d-none', panel.dataset.clinicPanel !== button.dataset.clinicId));
        if (addScheduleButton) {
            addScheduleButton.dataset.clinicId = button.dataset.clinicId;
            addScheduleButton.dataset.clinicName = button.dataset.clinicName;
            addScheduleButton.dataset.defaultStart = button.dataset.defaultStart;
            addScheduleButton.dataset.defaultEnd = button.dataset.defaultEnd;
        }
        updateScheduleSummary(button);
    }));

    const patientsModalElement = document.getElementById('schedulePatientsModal');
    const appointmentModalElement = document.getElementById('scheduleAppointmentModal');
    const patientsModal = bootstrap.Modal.getOrCreateInstance(patientsModalElement);
    const appointmentModal = bootstrap.Modal.getOrCreateInstance(appointmentModalElement);
    const patientsList = document.getElementById('schedulePatientsModalList');
    const patientsSubtitle = document.getElementById('schedulePatientsModalSubtitle');
    const patientsWindow = document.getElementById('schedulePatientsModalWindow');
    const patientsCount = document.getElementById('schedulePatientsModalCount');
    let activeScheduleId = null;
    let returnToPatientList = false;

    function formatScheduleDate(value) {
        if (!value) return 'Date unavailable';
        return new Date(`${value}T00:00:00`).toLocaleDateString([], {
            month: 'long', day: 'numeric', year: 'numeric'
        });
    }

    function formatScheduleTime(value) {
        if (!value) return 'Time unavailable';
        return new Date(`1970-01-01T${value}`).toLocaleTimeString([], {
            hour: 'numeric', minute: '2-digit'
        });
    }

    function patientInitials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        return `${parts[0][0]}${parts.length > 1 ? parts[parts.length - 1][0] : ''}`.toUpperCase();
    }

    function appendDetailRow(container, label, value) {
        const item = document.createElement('div');
        item.className = 'vd-schedule-detail-row';
        const term = document.createElement('span');
        term.className = 'vd-schedule-detail-term';
        term.textContent = label;
        const description = document.createElement('strong');
        description.className = 'vd-schedule-detail-value';
        description.textContent = value || 'Not provided';
        item.append(term, description);
        container.appendChild(item);
    }

    function showAppointmentDetails(appointment) {
        document.getElementById('scheduleAppointmentModalTitle').textContent = appointment.patient_name || 'Patient appointment';
        document.getElementById('scheduleAppointmentModalSubtitle').textContent =
            appointment.appointment_code || `Appointment #${appointment.appointment_id}`;
        document.getElementById('scheduleAppointmentAvatar').textContent = patientInitials(appointment.patient_name);
        document.getElementById('scheduleAppointmentVisitDate').textContent = formatScheduleDate(appointment.date);
        document.getElementById('scheduleAppointmentVisitTime').textContent = `${formatScheduleTime(appointment.start_time)}–${formatScheduleTime(appointment.end_time)}`;
        document.getElementById('scheduleAppointmentVisitClinic').textContent = appointment.clinic_name || 'Clinic unavailable';
        const statusMark = document.getElementById('scheduleAppointmentStatusMark');
        const appointmentStatus = appointment.status || 'Status unavailable';
        statusMark.dataset.status = appointmentStatus.toLowerCase().replaceAll(' ', '-');
        statusMark.querySelector('span').textContent = appointmentStatus;

        const recordList = document.getElementById('scheduleAppointmentRecordList');
        recordList.replaceChildren();
        appendDetailRow(recordList, 'Appointment number', `#${appointment.appointment_id}`);
        appendDetailRow(recordList, 'Reference', appointment.appointment_code || 'Reference pending');
        appendDetailRow(recordList, 'Status', appointment.status);

        const patientList = document.getElementById('schedulePatientInformationList');
        patientList.replaceChildren();
        appendDetailRow(patientList, 'Email', appointment.email);
        appendDetailRow(patientList, 'Contact number', appointment.phone_number);
        appendDetailRow(patientList, 'Age', appointment.age ? String(appointment.age) : 'Not provided');
        appendDetailRow(patientList, 'Gender', appointment.gender);

        const paymentList = document.getElementById('scheduleAppointmentPaymentList');
        paymentList.replaceChildren();
        const depositAmount = appointment.deposit_amount === null || appointment.deposit_amount === ''
            ? 'Not provided'
            : new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(appointment.deposit_amount));
        appendDetailRow(paymentList, 'Deposit status', appointment.deposit_status);
        appendDetailRow(paymentList, 'Deposit amount', depositAmount);
        appendDetailRow(paymentList, 'GCash reference', appointment.gcash_reference);

        const services = document.getElementById('scheduleAppointmentServices');
        services.replaceChildren();
        const selectedServices = Array.isArray(appointment.services) && appointment.services.length
            ? appointment.services
            : String(appointment.service_name || 'Service not specified').split(',').map(serviceName => ({
                service_name: serviceName.trim(),
                service_icon: 'fa-solid fa-tooth'
            }));
        selectedServices.forEach(selectedService => {
            const service = document.createElement('div');
            service.className = 'vd-schedule-appointment-service';
            const serviceIcon = document.createElement('span');
            serviceIcon.className = 'vd-schedule-appointment-service-icon';
            const serviceIconGlyph = document.createElement('i');
            serviceIconGlyph.className = selectedService.service_icon || 'fa-solid fa-tooth';
            serviceIconGlyph.setAttribute('aria-hidden', 'true');
            serviceIcon.appendChild(serviceIconGlyph);
            const serviceText = document.createElement('strong');
            serviceText.textContent = selectedService.service_name || 'Service';
            const included = document.createElement('span');
            included.className = 'vd-schedule-appointment-service-status';
            included.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i> Included';
            service.append(serviceIcon, serviceText, included);
            services.appendChild(service);
        });

        patientsModalElement.addEventListener('hidden.bs.modal', () => appointmentModal.show(), { once: true });
        patientsModal.hide();
    }

    function showSchedulePatients(button) {
        activeScheduleId = String(button.dataset.viewSchedulePatients);
        const appointments = scheduleAppointmentsBySchedule[activeScheduleId] || [];
        patientsSubtitle.textContent = button.dataset.scheduleLabel || '';
        patientsWindow.textContent = button.dataset.scheduleWindow || '';
        patientsCount.textContent = `${appointments.length} ${scheduleRosterLabel}`;
        patientsList.replaceChildren();

        appointments.forEach(appointment => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'vd-schedule-patient-option';
            row.setAttribute('aria-label', `View full appointment details for ${appointment.patient_name || 'patient'}`);
            const avatar = document.createElement('span');
            avatar.className = 'vd-schedule-patient-avatar';
            avatar.textContent = patientInitials(appointment.patient_name);
            const copy = document.createElement('div');
            copy.className = 'vd-schedule-patient-modal-copy';
            const name = document.createElement('strong');
            name.textContent = appointment.patient_name || 'Patient';
            const service = document.createElement('span');
            service.textContent = appointment.service_name || 'Service not specified';
            const reference = document.createElement('small');
            reference.textContent = `${appointment.appointment_code || 'Reference pending'} · ${appointment.status || 'Status unavailable'}`;
            copy.append(name, service, reference);
            const arrow = document.createElement('span');
            arrow.className = 'vd-schedule-patient-option-arrow';
            arrow.innerHTML = '<span>View details</span><i class="ti ti-chevron-right" aria-hidden="true"></i>';
            row.addEventListener('click', () => showAppointmentDetails(appointment));
            row.append(avatar, copy, arrow);
            patientsList.appendChild(row);
        });

        patientsModal.show();
    }

    document.querySelectorAll('[data-view-schedule-patients]').forEach(button => {
        button.addEventListener('click', () => showSchedulePatients(button));
    });

    document.getElementById('backToSchedulePatients').addEventListener('click', () => {
        returnToPatientList = true;
        appointmentModal.hide();
    });
    appointmentModalElement.addEventListener('hidden.bs.modal', () => {
        if (!returnToPatientList || !activeScheduleId) return;
        returnToPatientList = false;
        patientsModal.show();
    });

    // Show toast for query param results (e.g., edit conflict) — use global showToast if available
    (function () {
        const params = new URLSearchParams(window.location.search);
        if (params.get('error') === 'conflict') {
            if (typeof showToast === 'function') showToast('Cannot change date: another schedule exists for that date.', false);
            // remove param from URL to avoid repeat on refresh
            history.replaceState(null, '', window.location.pathname);
        }
        if (params.get('updated') === '1') {
            if (typeof showToast === 'function') showToast('Schedule updated.', true);
            history.replaceState(null, '', window.location.pathname);
        }
    })();

    // ── Delete schedule via confirmation modal ──
    let scheduleToDelete = null;
    document.querySelectorAll('.vd-delete-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            scheduleToDelete = btn.dataset.id;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteScheduleModal'));
            deleteModal.show();
        });
    });

    document.getElementById('confirmDeleteBtn')?.addEventListener('click', async function () {
        if (!scheduleToDelete) return;
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Deleting…';

        LoadingUI.setButton(btn, true, 'Deleting…');
        const formData = new FormData();
        formData.append('action', 'delete_schedule');
        formData.append('schedule_id', scheduleToDelete);
        formData.append('csrf_token', csrfToken);

        let shouldRefresh = false;
        try {
            const resp = await fetch('../../controllers/scheduleController.php', { method: 'POST', body: formData });
            const text = await resp.text();
            if (text.trim() === 'success') {
                showToast('Schedule deleted successfully!', true);
                shouldRefresh = true;
            } else {
                showToast('Error: ' + text, false);
            }
        } catch (err) {
            showToast('Network error. Please try again.', false);
            console.error(err);
        } finally {
            LoadingUI.setButton(btn, false);
            btn.disabled = false;
            btn.textContent = 'Delete';
            scheduleToDelete = null;
            const deleteModalEl = document.getElementById('deleteScheduleModal');
            const modal = bootstrap.Modal.getInstance(deleteModalEl);
            if (shouldRefresh) {
                deleteModalEl.addEventListener('hidden.bs.modal', refreshPage, { once: true });
            }
            if (modal) modal.hide();
            else if (shouldRefresh) refreshPage();
        }
    });
})();
</script>

<?php if (!$scheduleReadOnly) include __DIR__ . '/_add-schedule-modal.php'; ?>
