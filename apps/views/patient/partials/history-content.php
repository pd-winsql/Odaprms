<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/appointmentModel.php';

$db   = new Database();
$conn = $db->connect();

$patientModel     = new Patient($conn);
$appointmentModel = new Appointment($conn);

$patient  = $patientModel->getPatientByUserId($_SESSION['user_id']);
$past     = $appointmentModel->getPatientPastAppointments($patient['patient_id']);
$servicesByAppointment = $appointmentModel->getServiceDetailsForAppointments(array_column($past, 'appointment_id'));

function statusClass($s) {
    return 'vd-status vd-status-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s));
}

function patientHistoryPayload(array $appointment, array $services): string {
    return htmlspecialchars(json_encode([
        'appointmentId' => (int) $appointment['appointment_id'],
        'appointmentCode' => $appointment['appointment_code'] ?? '',
        'date' => $appointment['date'] ?? '',
        'startTime' => $appointment['start_time'] ?? '',
        'endTime' => $appointment['end_time'] ?? '',
        'clinic' => $appointment['clinic_name'] ?? '',
        'status' => $appointment['status'] ?? '',
        'services' => array_map(static fn(array $service): array => [
            'name' => $service['service_name'] ?? '',
            'description' => $service['service_description'] ?? '',
            'category' => $service['category_name'] ?? 'Dental service',
            'icon' => $service['service_icon'] ?? 'fa-solid fa-tooth',
        ], $services),
        'billing' => !empty($appointment['billing_id']) ? [
            'id' => (int) $appointment['billing_id'],
            'actualCharge' => (float) ($appointment['actual_service_amount'] ?? 0),
            'depositApplied' => (float) ($appointment['deposit_applied'] ?? 0),
            'amountDue' => (float) ($appointment['remaining_balance'] ?? 0),
            'cashTendered' => (float) ($appointment['cash_received'] ?? 0),
            'status' => $appointment['payment_status'] ?? '',
            'recordedAt' => $appointment['billing_recorded_at'] ?? '',
            'notes' => $appointment['billing_notes'] ?? '',
        ] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}
?>

<div class="d-flex flex-column gap-4">

    <div class="vd-pat-welcome">
        <div class="vd-welcome-greet">APPOINTMENT HISTORY</div>
        <div class="vd-welcome-name">Your previous visits</div>
        <p class="text-muted small mb-0 mt-2">
            Review your past appointment dates, clinic locations, services, and final appointment statuses.
        </p>
    </div>

    <!-- Past -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Past Appointments</span>
        <span class="vd-topbar-date"><?= count($past) ?> total</span>
        </div>
        <div class="vd-dash-card-body" style="padding:0;">
        <?php if (empty($past)): ?>
            <div class="vd-empty-state">No past appointments.</div>
        <?php else: ?>
            <?php foreach ($past as $appt): ?>
            <div class="vd-pat-appt-row">
            <div class="vd-appt-date-box">
                <span class="vd-appt-day"><?= date('d', strtotime($appt['date'])) ?></span>
                <span class="vd-appt-mon"><?= date('M', strtotime($appt['date'])) ?></span>
            </div>
            <div class="vd-appt-info">
                <div class="vd-appt-name"><?= htmlspecialchars($appt['service_name']) ?></div>
                <div class="vd-appt-meta">
                <?= htmlspecialchars($appt['clinic_name'] ?? $appt['clinic'] ?? '—') ?> · <?= date('g:i A', strtotime($appt['start_time'])) ?>–<?= date('g:i A', strtotime($appt['end_time'])) ?>
                </div>
            </div>
            <div class="vd-history-row-actions">
                <span class="<?= statusClass($appt['status']) ?>">
                    <?= htmlspecialchars($appt['status']) ?>
                </span>
                <button type="button" class="btn vd-btn-outline btn-sm vd-history-details-btn"
                    data-history-details="<?= patientHistoryPayload($appt, $servicesByAppointment[(int) $appt['appointment_id']] ?? []) ?>">
                    <i class="ti ti-eye" aria-hidden="true"></i><span>View details</span>
                </button>
            </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade vd-appointment-details-modal vd-patient-history-modal" id="patientHistoryDetailsModal" tabindex="-1"
    aria-labelledby="patientHistoryDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-appointment-details-kicker">Past appointment</div>
                    <h5 class="modal-title vd-modal-title" id="patientHistoryDetailsTitle">Visit details</h5>
                    <p class="vd-appointment-details-subtitle mb-0" id="patientHistoryDetailsSubtitle"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <section aria-labelledby="patientHistoryVisitHeading">
                    <h6 class="vd-appointment-details-section-title" id="patientHistoryVisitHeading">Appointment information</h6>
                    <div class="vd-appointment-detail-grid" id="patientHistoryVisitGrid"></div>
                </section>
                <section class="vd-appointment-services-section" aria-labelledby="patientHistoryServicesHeading">
                    <div class="vd-appointment-section-heading">
                        <h6 class="vd-appointment-details-section-title mb-0" id="patientHistoryServicesHeading">Services received</h6>
                        <span class="vd-appointment-service-count" id="patientHistoryServiceCount"></span>
                    </div>
                    <div class="vd-appointment-service-list" id="patientHistoryServiceList"></div>
                </section>
                <section class="vd-appointment-payment-section d-none" id="patientHistoryPaymentSection" aria-labelledby="patientHistoryPaymentHeading">
                    <div class="vd-appointment-section-heading">
                        <h6 class="vd-appointment-details-section-title mb-0" id="patientHistoryPaymentHeading">Payment summary</h6>
                        <span class="vd-status" id="patientHistoryPaymentStatus"></span>
                    </div>
                    <div class="vd-final-billing-summary" id="patientHistoryPaymentSummary"></div>
                    <div class="vd-appointment-payment-note d-none" id="patientHistoryPaymentNotes"></div>
                </section>
            </div>
            <div class="modal-footer"><button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script>
(function () {
    const modalElement = document.getElementById('patientHistoryDetailsModal');
    if (!modalElement) return;
    const money = value => Number(value || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
    const formatDate = value => {
        if (!value) return 'Not recorded';
        const date = new Date(`${value}T00:00:00`);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString([], { year: 'numeric', month: 'long', day: 'numeric' });
    };
    const formatDateTime = value => {
        if (!value) return 'Not recorded';
        const date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString([], { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    };
    const formatTime = value => value
        ? new Date(`1970-01-01T${value}`).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
        : 'Not recorded';
    const addDetail = (container, label, value) => {
        const item = document.createElement('div');
        item.className = 'vd-appointment-detail-item';
        const term = document.createElement('span');
        term.textContent = label;
        const detail = document.createElement('strong');
        detail.textContent = value || 'Not provided';
        item.append(term, detail);
        container.appendChild(item);
    };
    const addSummaryRow = (container, label, value, emphasized = false) => {
        const row = document.createElement('div');
        if (emphasized) row.className = 'vd-final-billing-total';
        const term = document.createElement('span');
        term.textContent = label;
        const amount = document.createElement('strong');
        amount.textContent = value;
        row.append(term, amount);
        container.appendChild(row);
    };

    document.querySelectorAll('[data-history-details]').forEach(button => button.addEventListener('click', () => {
        const appointment = JSON.parse(button.dataset.historyDetails);
        document.getElementById('patientHistoryDetailsTitle').textContent = `Visit on ${formatDate(appointment.date)}`;
        document.getElementById('patientHistoryDetailsSubtitle').textContent = appointment.clinic || 'Clinic not listed';

        const visitGrid = document.getElementById('patientHistoryVisitGrid');
        visitGrid.replaceChildren();
        addDetail(visitGrid, 'Appointment number', `#${appointment.appointmentId}`);
        addDetail(visitGrid, 'Appointment code', appointment.appointmentCode || 'Not issued');
        addDetail(visitGrid, 'Visit date', formatDate(appointment.date));
        addDetail(visitGrid, 'Clinic window', `${formatTime(appointment.startTime)}–${formatTime(appointment.endTime)}`);
        addDetail(visitGrid, 'Final status', appointment.status);

        const serviceList = document.getElementById('patientHistoryServiceList');
        serviceList.replaceChildren();
        document.getElementById('patientHistoryServiceCount').textContent = `${appointment.services.length} service${appointment.services.length === 1 ? '' : 's'}`;
        appointment.services.forEach(service => {
            const card = document.createElement('article');
            card.className = 'vd-appointment-service-card';
            const icon = document.createElement('span');
            icon.className = 'vd-appointment-service-icon';
            const iconGlyph = document.createElement('i');
            iconGlyph.className = service.icon || 'fa-solid fa-tooth';
            icon.appendChild(iconGlyph);
            const copy = document.createElement('div');
            copy.className = 'vd-appointment-service-copy';
            const category = document.createElement('span');
            category.className = 'vd-appointment-service-category';
            category.textContent = service.category || 'Dental service';
            const name = document.createElement('strong');
            name.textContent = service.name || 'Service';
            const description = document.createElement('small');
            description.textContent = service.description || 'No service description available.';
            copy.append(category, name, description);
            const included = document.createElement('span');
            included.className = 'vd-appointment-service-included';
            included.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i><span>Availed</span>';
            card.append(icon, copy, included);
            serviceList.appendChild(card);
        });
        if (!appointment.services.length) {
            const empty = document.createElement('div');
            empty.className = 'vd-empty-state';
            empty.textContent = 'No services are attached to this appointment.';
            serviceList.appendChild(empty);
        }

        const paymentSection = document.getElementById('patientHistoryPaymentSection');
        paymentSection.classList.toggle('d-none', !appointment.billing);
        if (appointment.billing) {
            const billing = appointment.billing;
            const status = document.getElementById('patientHistoryPaymentStatus');
            status.className = `vd-status vd-status-${String(billing.status).toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
            status.textContent = billing.status || 'Recorded';
            const summary = document.getElementById('patientHistoryPaymentSummary');
            summary.replaceChildren();
            addSummaryRow(summary, 'Treatment total', money(billing.actualCharge));
            addSummaryRow(summary, 'Deposit applied', `−${money(billing.depositApplied)}`);
            addSummaryRow(summary, 'Amount due', money(billing.amountDue), true);
            addSummaryRow(summary, 'Cash tendered', money(billing.cashTendered));
            addSummaryRow(summary, 'Recorded', formatDateTime(billing.recordedAt));
            const notes = document.getElementById('patientHistoryPaymentNotes');
            notes.textContent = billing.notes || '';
            notes.classList.toggle('d-none', !billing.notes);
        }

        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }));
})();
</script>
