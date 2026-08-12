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
$upcoming = $appointmentModel->getAllUpcomingWithStatus();
$clinics = (new Clinic($conn))->getAllClinics();
$todayLogbook = $logbookModel->getToday();
$arrivedCount = count(array_filter($todayLogbook, static fn($row) => !empty($row['arrived_at'])));
$readyCount = count(array_filter($todayLogbook, static fn($row) => $row['checkin_status'] === 'Ready'));
$reviewCount = $depositModel->getPendingReviewCount();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

function dashboardStatusClass($status) {
    return 'vd-status vd-status-' . strtolower(str_replace(' ', '-', $status));
}

function dashboardBillingPayload(array $entry): string {
    $amountDue = max(0, (float) ($entry['remaining_balance'] ?? 0));
    $cash = (float) ($entry['cash_received'] ?? 0);
    return htmlspecialchars(json_encode([
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
        'recordedAt' => $entry['billing_recorded_at'] ?? '',
        'notes' => $entry['billing_notes'] ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}
?>

<div class="d-flex flex-column gap-4">
    <div class="vd-stat-grid">
        <div class="vd-stat-card"><div class="vd-stat-label">Today's Appointments</div><div class="vd-stat-value"><?= count($todayLogbook) ?></div><div class="vd-stat-sub">Across <?= count($clinics) ?> clinics</div></div>
        <div class="vd-stat-card"><div class="vd-stat-label">Arrived</div><div class="vd-stat-value"><?= $arrivedCount ?></div><div class="vd-stat-sub"><?= $readyCount ?> ready</div></div>
        <div class="vd-stat-card"><div class="vd-stat-label">Upcoming</div><div class="vd-stat-value"><?= count($upcoming) ?></div><div class="vd-stat-sub">All active requests</div></div>
        <button type="button" class="vd-stat-card text-start" id="openPaymentsAwaitingReview"><div class="vd-stat-label">Payments Awaiting Review</div><div class="vd-stat-value"><?= $reviewCount ?></div><div class="vd-stat-sub">Open in Appointments</div></button>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header"><span class="vd-dash-card-title">Today's Logbook</span><span class="vd-topbar-date"><?= date('F j, Y') ?></span></div>
        <div class="vd-dash-card-body">
        <div class="d-flex flex-wrap gap-2 mb-4">
            <input type="text" class="form-control vd-input flex-grow-1" id="checkinLookup"
                placeholder="Enter appointment code (AVC-XXXXXX)" aria-label="Appointment code"
                autocomplete="off" autocapitalize="characters">
            <button type="button" class="btn vd-btn-gold" id="findCheckinAppointment">Find Appointment</button>
        </div>
        <?php if (!$todayLogbook): ?>
            <div class="vd-empty-state">No confirmed appointments scheduled for today.</div>
        <?php else: ?>
            <div class="vd-appt-table-wrap">
                <table class="vd-appt-table w-100">
                    <thead><tr><th>Patient</th><th>Services</th><th>Clinic</th><th>Arrival</th><th>Profile</th><th>Check-in</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($todayLogbook as $entry): ?>
                        <tr>
                            <td><div class="vd-appt-name"><?= htmlspecialchars($entry['lastname'] . ', ' . $entry['firstname']) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($entry['email']) ?></div></td>
                            <td class="vd-appt-meta"><?= htmlspecialchars($entry['service_name'] ?: '—') ?></td>
                            <td class="vd-appt-meta"><?= htmlspecialchars($entry['clinic_name']) ?></td>
                            <td class="vd-appt-meta"><?= $entry['arrived_at'] ? date('g:i A', strtotime($entry['arrived_at'])) : 'Not arrived' ?></td>
                            <td><span class="<?= dashboardStatusClass($entry['profile_completed_at'] ? 'Complete' : 'Incomplete') ?>"><?= $entry['profile_completed_at'] ? 'Complete' : 'Incomplete' ?></span></td>
                            <td>
                                <?php if ($entry['checkin_status']): ?><span class="<?= dashboardStatusClass($entry['checkin_status']) ?>"><?= htmlspecialchars($entry['checkin_status']) ?></span>
                                <?php else: ?><span class="vd-status vd-status-pending">Not Arrived</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$entry['checkin_id'] && $entry['appointment_status'] === 'Confirmed'): ?>
                                    <span class="text-muted small">Awaiting patient code</span>
                                <?php elseif ($entry['checkin_status'] === 'Profile Required'): ?>
                                    <button type="button" class="btn vd-btn-outline btn-sm" data-complete-profile="<?= (int) $entry['patient_id'] ?>">Complete Patient Form</button>
                                <?php elseif ($entry['appointment_status'] === 'Checked In' && $entry['checkin_status'] === 'Ready'): ?>
                                    <button type="button" class="btn vd-btn-gold btn-sm" data-visit-status="In Progress" data-appointment-id="<?= (int) $entry['appointment_id'] ?>">
                                        <i class="ti ti-player-play me-1"></i>Start Treatment
                                    </button>
                                <?php elseif ($entry['appointment_status'] === 'In Progress'): ?>
                                    <button type="button" class="btn vd-btn-gold btn-sm" data-complete-with-billing
                                        data-appointment-id="<?= (int) $entry['appointment_id'] ?>"
                                        data-patient="<?= htmlspecialchars(trim($entry['firstname'] . ' ' . $entry['lastname'])) ?>"
                                        data-services="<?= htmlspecialchars($entry['service_name'] ?: 'Service not listed') ?>"
                                        data-clinic="<?= htmlspecialchars($entry['clinic_name']) ?>"
                                        data-deposit="<?= htmlspecialchars((string) ((float) $entry['verified_deposit'])) ?>">
                                        <i class="ti ti-check me-1"></i>Complete Visit
                                    </button>
                                <?php elseif ($entry['appointment_status'] === 'Completed'): ?>
                                    <?php if (!empty($entry['billing_id'])): ?>
                                        <button type="button" class="btn vd-btn-outline btn-sm" data-view-logbook-billing="<?= dashboardBillingPayload($entry) ?>">
                                            <i class="ti ti-receipt me-1"></i>View Billing
                                        </button>
                                    <?php else: ?>
                                        <span class="vd-status vd-status-warning">Billing Missing</span>
                                    <?php endif; ?>
                                <?php else: ?><span class="text-muted small"><?= htmlspecialchars($entry['checked_in_by'] ?: '—') ?></span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header"><span class="vd-dash-card-title">Upcoming Appointments</span><span class="vd-topbar-date"><?= count($upcoming) ?> total</span></div>
        <div class="vd-dash-card-body">
            <?php if (!$upcoming): ?><div class="vd-empty-state">No upcoming appointment requests.</div>
            <?php else: foreach (array_slice($upcoming, 0, 5) as $appt): ?>
                <div class="vd-appt-row">
                    <div class="vd-appt-date-box"><div class="vd-appt-day"><?= date('d', strtotime($appt['date'])) ?></div><div class="vd-appt-mon"><?= date('M', strtotime($appt['date'])) ?></div></div>
                    <div class="vd-appt-info"><div class="vd-appt-name"><?= htmlspecialchars($appt['lastname'] . ', ' . $appt['firstname']) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($appt['service_name']) ?> · <?= htmlspecialchars($appt['clinic_name']) ?></div></div>
                    <span class="<?= dashboardStatusClass($appt['status']) ?>"><?= htmlspecialchars($appt['status']) ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<div class="modal fade vd-final-billing-modal" id="finalBillingModal" tabindex="-1" aria-labelledby="finalBillingTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div><div class="vd-action-modal-kicker">Complete transaction</div><h5 class="modal-title vd-modal-title" id="finalBillingTitle">Final Billing</h5><p class="text-muted small mb-0" id="finalBillingSubtitle"></p></div>
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

<div class="modal fade" id="logbookBillingDetailsModal" tabindex="-1" aria-labelledby="logbookBillingDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content vd-modal-content">
        <div class="modal-header"><div><div class="vd-action-modal-kicker">Completed transaction</div><h5 class="modal-title vd-modal-title" id="logbookBillingDetailsTitle">Billing Details</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="vd-appointment-detail-grid" id="logbookBillingDetailsGrid"></div><div class="vd-appointment-payment-note d-none" id="logbookBillingNotes"></div></div>
        <div class="modal-footer"><button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<script>
(function () {
    const money = value => Number(value || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
    const addBillingDetail = (container, label, value) => {
        const item = document.createElement('div'); item.className = 'vd-appointment-detail-item';
        const term = document.createElement('span'); term.textContent = label;
        const detail = document.createElement('strong'); detail.textContent = value || 'Not provided';
        item.append(term, detail); container.appendChild(item);
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
            error.classList.add('d-none'); error.textContent = '';
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
        const details = document.getElementById('finalBillingVisitDetails'); details.replaceChildren();
        addBillingDetail(details, 'Patient', activeBillingAppointment.patient);
        addBillingDetail(details, 'Services', activeBillingAppointment.services);
        addBillingDetail(details, 'Clinic', activeBillingAppointment.clinic);
        addBillingDetail(details, 'Verified deposit', money(activeBillingAppointment.deposit));
        serviceAmountInput.value = ''; cashTenderedInput.value = '0'; document.getElementById('finalBillingNotes').value = '';
        updateFinalBillingSummary();
        finalBillingModal = bootstrap.Modal.getOrCreateInstance(finalBillingModalElement);
        finalBillingModal.show();
    }));
    [serviceAmountInput, cashTenderedInput].forEach(input => input?.addEventListener('input', updateFinalBillingSummary));

    completeBillingButton?.addEventListener('click', async function () {
        if (!activeBillingAppointment) return;
        const confirmation = await window.showActionModal({
            title: 'Confirm Final Payment', kicker: 'Complete transaction',
            message: 'This records the cash payment and completes the visit. The transaction cannot be edited from Today’s Logbook afterward.',
            confirmText: 'Confirm & Complete', icon: 'ti-cash-check', tone: 'success',
            details: [
                { label: 'Patient', value: activeBillingAppointment.patient },
                { label: 'Amount due', value: document.getElementById('finalAmountDueDisplay').textContent },
                { label: 'Cash tendered', value: document.getElementById('finalCashDisplay').textContent },
                { label: 'Change', value: document.getElementById('finalChangeDisplay').textContent }
            ]
        });
        if (!confirmation.confirmed) return;
        LoadingUI.setButton(this, true, 'Completing...');
        const body = new FormData();
        body.append('action', 'settleAndComplete'); body.append('csrf_token', csrfToken);
        body.append('appointment_id', activeBillingAppointment.id);
        body.append('service_amount', serviceAmountInput.value);
        body.append('cash_received', cashTenderedInput.value);
        body.append('notes', document.getElementById('finalBillingNotes').value);
        try {
            const response = await fetch('../../controllers/billingController.php', { method: 'POST', body });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to complete the transaction.');
            finalBillingModal.hide(); window.showToast(result.message, true);
            document.querySelector('[data-page="dashboard-content.php"]')?.click();
        } catch (error) {
            LoadingUI.setButton(this, false); window.showToast(error.message, false);
        }
    });

    finalBillingModalElement?.addEventListener('hidden.bs.modal', () => {
        activeBillingAppointment = null; LoadingUI.setButton(completeBillingButton, false);
    });

    document.querySelectorAll('[data-view-logbook-billing]').forEach(button => button.addEventListener('click', () => {
        const billing = JSON.parse(button.dataset.viewLogbookBilling);
        document.getElementById('logbookBillingDetailsTitle').textContent = `Billing · ${billing.patient}`;
        const grid = document.getElementById('logbookBillingDetailsGrid'); grid.replaceChildren();
        addBillingDetail(grid, 'Appointment', `#${billing.appointmentId}`);
        addBillingDetail(grid, 'Services', billing.services);
        addBillingDetail(grid, 'Actual charge', money(billing.serviceAmount));
        addBillingDetail(grid, 'Deposit applied', money(billing.depositApplied));
        addBillingDetail(grid, 'Amount due', money(billing.amountDue));
        addBillingDetail(grid, 'Cash tendered', money(billing.cashTendered));
        addBillingDetail(grid, 'Change', money(billing.change));
        addBillingDetail(grid, 'Payment status', billing.status);
        const note = document.getElementById('logbookBillingNotes');
        note.textContent = billing.notes || ''; note.classList.toggle('d-none', !billing.notes);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('logbookBillingDetailsModal')).show();
    }));
    document.getElementById('findCheckinAppointment')?.addEventListener('click', async () => {
        const term = document.getElementById('checkinLookup').value.trim();
        if (!/^AVC-[A-Z0-9]+$/i.test(term)) { window.showToast('Enter a valid appointment code, such as AVC-XXXXXX.', false); return; }
        const lookupBody = new FormData();
        lookupBody.append('action', 'lookup');
        lookupBody.append('term', term);
        lookupBody.append('csrf_token', csrfToken);
        try {
            const lookupResponse = await fetch('../../controllers/logbookController.php', { method: 'POST', body: lookupBody });
            const lookup = await lookupResponse.json();
            if (!lookup.success || !lookup.matches?.length) throw new Error('No confirmed appointment for today matches that code.');
            const match = lookup.matches[0];
            const confirmation = await window.showActionModal({
                title: 'Confirm Patient Arrival',
                kicker: 'Logbook check-in',
                message: 'Verify the appointment details before recording this patient as arrived.',
                confirmText: 'Check In Patient',
                icon: 'ti-login-2',
                tone: 'success',
                details: [
                    { label: 'Patient', value: `${match.firstname} ${match.lastname}` },
                    { label: 'Service', value: match.service_name || 'Service not listed' },
                    { label: 'Clinic', value: match.clinic_name }
                ]
            });
            if (!confirmation.confirmed) return;
            const body = new FormData();
            body.append('action', 'checkIn');
            body.append('appointment_id', match.appointment_id);
            body.append('lookup_method', match.lookup_method);
            body.append('csrf_token', csrfToken);
            const response = await fetch('../../controllers/logbookController.php', { method: 'POST', body });
            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'Check-in failed.');
            window.showToast(result.message, true);
            document.querySelector('[data-page="dashboard-content.php"]')?.click();
        } catch (error) { window.showToast(error.message, false); }
    });

    document.querySelectorAll('[data-complete-profile]').forEach(button => {
        button.addEventListener('click', async () => {
            const content = document.querySelector('.vd-dash-content');
            LoadingUI.showContent(content, { label: 'Loading patient form…' });
            try {
                const response = await fetch(`partials/_patient-form.php?id=${button.dataset.completeProfile}`);
                if (!response.ok) throw new Error('Unable to load patient form.');
                content.innerHTML = await response.text();
                content.querySelectorAll('script').forEach(oldScript => {
                    const script = document.createElement('script');
                    script.textContent = oldScript.textContent;
                    document.body.appendChild(script);
                    oldScript.remove();
                });
            } catch (error) { content.innerHTML = `<div class="vd-empty-state">${error.message}</div>`; }
            finally { LoadingUI.finishContent(content); }
        });
    });

    document.querySelectorAll('[data-visit-status]').forEach(button => {
        button.addEventListener('click', async () => {
            const status = button.dataset.visitStatus;
            const isStarting = status === 'In Progress';
            const confirmation = await window.showActionModal({
                title: isStarting ? 'Start Treatment' : 'Complete Visit',
                kicker: "Today's logbook",
                message: isStarting
                    ? 'Confirm that the patient is ready and treatment is beginning.'
                    : 'Confirm that treatment for this visit has been completed.',
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
                const response = await fetch('../../controllers/logbookController.php', { method: 'POST', body });
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
