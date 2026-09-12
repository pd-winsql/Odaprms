<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/appointmentModel.php';
require_once __DIR__ . '/../../../models/rescheduleModel.php';
require_once __DIR__ . '/../../../models/clinicModel.php';
require_once __DIR__ . '/../../../models/scheduleModel.php';
require_once __DIR__ . '/../../../helpers/bookingPolicy.php';

date_default_timezone_set('Asia/Manila');
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$db   = new Database();
$conn = $db->connect(); 

$patientModel     = new Patient($conn);
$appointmentModel = new Appointment($conn);
$rescheduleModel  = new RescheduleModel($conn);

$patient  = $patientModel->getPatientByUserId($_SESSION['user_id']);
$upcoming = $appointmentModel->getPatientUpcomingAppointments($patient['patient_id'] ?? '');
$next     = $upcoming[0] ?? null;
$otherUpcoming = array_slice($upcoming, 1);
$rescheduleRequests = $rescheduleModel->getPatientRequests((int) $_SESSION['user_id']);
$latestRescheduleByAppointment = [];
foreach ($rescheduleRequests as $request) {
    $appointmentId = (int) $request['appointment_id'];
    $latestRescheduleByAppointment[$appointmentId] ??= $request;
}
$rescheduleLeadDays = BookingPolicy::minimumRescheduleLeadDays($conn);
$earliestRescheduleDate = BookingPolicy::earliestBookableDate($rescheduleLeadDays);
$rescheduleSchedules = [];
foreach ((new Clinic($conn))->getAllClinics() as $clinic) {
    foreach ((new Schedule($conn))->getAvailableSchedulesByClinic((int) $clinic['clinic_id'], $earliestRescheduleDate) as $schedule) {
        if ((int) ($schedule['available_slots'] ?? 0) < 1) continue;
        $rescheduleSchedules[] = $schedule + ['clinic_name' => $clinic['clinic_name']];
    }
}

$hour     = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstname = $patient['firstname'] ?? $_SESSION['display_name'] ?? 'Patient';

$profileRequirements = [
    'first name' => $patient['firstname'] ?? '',
    'last name' => $patient['lastname'] ?? '',
    'birthdate' => $patient['birthdate'] ?? '',
    'gender' => $patient['gender'] ?? '',
    'phone number' => $patient['phone_number'] ?? '',
    'email address' => $patient['email'] ?? '',
];
$missingProfileFields = array_keys(array_filter(
    $profileRequirements,
    static fn($value) => trim((string) $value) === ''
));
?>
<div class="d-flex flex-column gap-4">
    
    <!-- Welcome -->
    <div class="vd-pat-welcome">
        <div class="vd-welcome-greet"><?= $greeting ?>,</div>
        <div class="vd-welcome-name"><?= htmlspecialchars($firstname) ?></div>
        <p class="text-muted small mb-0 mt-2">Here is a quick overview of your next visit and account readiness.</p>
    </div>

    <?php if (!empty($missingProfileFields)): ?>
    <div class="vd-home-profile-notice">
        <div class="vd-home-profile-notice-icon"><i class="ti ti-user-exclamation"></i></div>
        <div class="vd-home-profile-notice-copy">
            <strong>Your patient profile needs review</strong>
            <span>Clinic staff will verify and complete the missing <?= htmlspecialchars(implode(', ', $missingProfileFields)) ?> during check-in.</span>
        </div>
        <button type="button" class="btn vd-btn-outline btn-sm" onclick="document.querySelector('[data-page=\'profile-content.php\']').click()">
            View Profile
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Next appointment -->
    <?php if ($next): ?>
    <div class="vd-next-appt-card">
        <div class="vd-next-appt-label">Next Appointment</div>
        <div class="vd-next-appt-service"><?= htmlspecialchars($next['service_name']) ?></div>
        <div class="vd-next-appt-meta">
        <span><i class="ti ti-building"></i> <?= htmlspecialchars($next['clinic_name'] ?? $next['clinic'] ?? '—') ?></span>
        <span><i class="ti ti-calendar"></i> <?= date('F d, Y', strtotime($next['date'])) ?></span>
        <span><i class="ti ti-clock"></i> <?= date('g:i A', strtotime($next['start_time'])) ?>–<?= date('g:i A', strtotime($next['end_time'])) ?></span>
        </div>
        <div class="vd-home-arrival-note"><i class="ti ti-user-clock"></i> Arrive by <?= date('g:i A', strtotime($next['start_time'])) ?> or earlier · First come, first served</div>
        <span class="vd-status vd-status-<?= htmlspecialchars(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $next['status']))) ?>">
        <?= htmlspecialchars($next['status']) ?>
        </span>
        <?php if (!empty($next['appointment_code'])): ?>
        <div class="alert alert-success mt-3 mb-0">
            <small class="d-block text-uppercase">Front-desk appointment code</small>
            <strong style="font-size:1.3rem;letter-spacing:.12em"><?= htmlspecialchars($next['appointment_code']) ?></strong>
        </div>
        <?php endif; ?>
        <?php $nextRequest = $latestRescheduleByAppointment[(int) $next['appointment_id']] ?? null; ?>
        <?php if (($nextRequest['status'] ?? '') === 'Pending'): ?>
        <div class="vd-reschedule-pending" role="status">
            <div><strong>Reschedule awaiting clinic review</strong><span><?= htmlspecialchars($nextRequest['target_clinic_name']) ?> · <?= date('M j, Y, g:i A', strtotime($nextRequest['target_date'] . ' ' . $nextRequest['target_start_time'])) ?></span></div>
            <button type="button" class="btn vd-btn-outline btn-sm" data-withdraw-reschedule="<?= (int) $nextRequest['request_id'] ?>">Withdraw</button>
        </div>
        <?php elseif (($next['status'] ?? '') === 'Confirmed'): ?>
        <button type="button" class="btn vd-home-next-cta" data-open-reschedule
            data-appointment-id="<?= (int) $next['appointment_id'] ?>"
            data-current-schedule-id="<?= (int) $next['schedule_id'] ?>"
            data-appointment-label="<?= htmlspecialchars(($next['clinic_name'] ?? 'Clinic') . ' · ' . date('M j, Y, g:i A', strtotime($next['date'] . ' ' . $next['start_time'])), ENT_QUOTES) ?>">
            <i class="ti ti-calendar-time me-1"></i> Request Reschedule
        </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($otherUpcoming)): ?>
    <section class="vd-other-appts" aria-labelledby="otherUpcomingTitle">
        <div class="vd-other-appts-heading">
            <div>
                <div class="vd-next-appt-label">Coming Up</div>
                <h2 id="otherUpcomingTitle">Other Upcoming Appointments</h2>
            </div>
            <span class="vd-other-appts-count"><?= count($otherUpcoming) ?></span>
        </div>

        <div class="vd-other-appts-list">
            <?php foreach ($otherUpcoming as $appointment): ?>
                <?php
                $appointmentStatus = $appointment['status'] ?? 'Pending';
                $statusClass = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $appointmentStatus));
                ?>
                <article class="vd-other-appt-item">
                    <div class="vd-other-appt-date" aria-label="<?= date('F d, Y', strtotime($appointment['date'])) ?>">
                        <span><?= date('M', strtotime($appointment['date'])) ?></span>
                        <strong><?= date('d', strtotime($appointment['date'])) ?></strong>
                    </div>
                    <div class="vd-other-appt-copy">
                        <h3><?= htmlspecialchars($appointment['service_name'] ?: 'Dental appointment') ?></h3>
                        <div class="vd-other-appt-meta">
                            <span><i class="ti ti-building"></i> <?= htmlspecialchars($appointment['clinic_name'] ?? $appointment['clinic'] ?? '—') ?></span>
                            <span><i class="ti ti-calendar"></i> <?= date('F d, Y', strtotime($appointment['date'])) ?></span>
                            <span><i class="ti ti-clock"></i> <?= date('g:i A', strtotime($appointment['start_time'])) ?>–<?= date('g:i A', strtotime($appointment['end_time'])) ?></span>
                        </div>
                    </div>
                    <span class="vd-status vd-status-<?= htmlspecialchars($statusClass) ?>">
                        <?= htmlspecialchars($appointmentStatus) ?>
                    </span>
                    <?php $otherRequest = $latestRescheduleByAppointment[(int) $appointment['appointment_id']] ?? null; ?>
                    <?php if (($otherRequest['status'] ?? '') === 'Pending'): ?>
                    <button type="button" class="btn vd-btn-outline btn-sm vd-other-appt-action" data-withdraw-reschedule="<?= (int) $otherRequest['request_id'] ?>">Withdraw request</button>
                    <?php elseif ($appointmentStatus === 'Confirmed'): ?>
                    <button type="button" class="btn vd-btn-outline btn-sm vd-other-appt-action" data-open-reschedule
                        data-appointment-id="<?= (int) $appointment['appointment_id'] ?>"
                        data-current-schedule-id="<?= (int) $appointment['schedule_id'] ?>"
                        data-appointment-label="<?= htmlspecialchars(($appointment['clinic_name'] ?? 'Clinic') . ' · ' . date('M j, Y, g:i A', strtotime($appointment['date'] . ' ' . $appointment['start_time'])), ENT_QUOTES) ?>">Reschedule</button>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <p class="vd-no-other-appts mb-0">No other upcoming appointments are scheduled.</p>
    <?php endif; ?>
    <?php else: ?>
    <div class="vd-next-appt-empty">
        <i class="ti ti-calendar-off" style="font-size:28px; color:var(--border);"></i>
        <div class="mt-2">You have no upcoming appointments.</div>
        <div class="text-muted small mt-1">Choose a clinic and view its available schedules when you are ready.</div>
        <button type="button" class="btn vd-btn-gold vd-home-empty-cta mt-3" onclick="document.querySelector('[data-page=\'booking-content.php\']').click()">
        View Available Schedules
        </button>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade vd-reschedule-modal" id="patientRescheduleModal" tabindex="-1" aria-labelledby="patientRescheduleTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="patientRescheduleForm">
            <div class="modal-header">
                <div>
                    <span class="vd-reschedule-kicker">Change appointment</span>
                    <h2 class="modal-title" id="patientRescheduleTitle">Choose a replacement schedule</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="vd-reschedule-current"><i class="ti ti-calendar-check"></i><span><small>Current appointment</small><strong id="patientRescheduleCurrent"></strong></span></div>
                <p class="vd-reschedule-note">Your current appointment stays confirmed while the clinic reviews this request. The selected slot is held for up to 24 hours.</p>
                <input type="hidden" name="appointment_id" id="patientRescheduleAppointmentId">
                <input type="hidden" name="target_schedule_id" id="patientRescheduleScheduleId">
                <fieldset>
                    <legend>Available schedules at both clinics</legend>
                    <div class="vd-reschedule-schedules" id="patientRescheduleSchedules">
                        <?php foreach ($rescheduleSchedules as $schedule): ?>
                        <button type="button" class="vd-reschedule-schedule" data-reschedule-schedule
                            data-schedule-id="<?= (int) $schedule['schedule_id'] ?>">
                            <span class="vd-reschedule-date"><strong><?= date('D', strtotime($schedule['sched_date'])) ?></strong><?= date('M j', strtotime($schedule['sched_date'])) ?></span>
                            <span class="vd-reschedule-schedule-copy"><strong><?= htmlspecialchars($schedule['clinic_name']) ?></strong><span><?= date('g:i A', strtotime($schedule['start_time'])) ?>–<?= date('g:i A', strtotime($schedule['end_time'])) ?> · <?= (int) $schedule['available_slots'] ?> slot<?= (int) $schedule['available_slots'] === 1 ? '' : 's' ?> left</span></span>
                            <i class="ti ti-circle-check" aria-hidden="true"></i>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <p class="vd-reschedule-empty" id="patientRescheduleEmpty" hidden>No other eligible schedules are currently available.</p>
                </fieldset>
                <label class="vd-label form-label" for="patientRescheduleReason">Reason for rescheduling</label>
                <textarea class="form-control vd-input" id="patientRescheduleReason" name="reason" rows="3" minlength="5" maxlength="500" required placeholder="Briefly tell the clinic why you need a different schedule."></textarea>
                <div class="form-text">The clinic will approve or reject your selected schedule within 24 hours.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Keep current appointment</button>
                <button type="submit" class="btn vd-btn-gold" id="patientRescheduleSubmit" disabled>Send request</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modalElement = document.getElementById('patientRescheduleModal');
    if (!modalElement) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const form = document.getElementById('patientRescheduleForm');
    const appointmentInput = document.getElementById('patientRescheduleAppointmentId');
    const scheduleInput = document.getElementById('patientRescheduleScheduleId');
    const currentLabel = document.getElementById('patientRescheduleCurrent');
    const submitButton = document.getElementById('patientRescheduleSubmit');
    const emptyState = document.getElementById('patientRescheduleEmpty');
    const token = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const controller = '../../controllers/rescheduleController.php';

    function refreshHome() {
        document.querySelector('.vd-nav-item[data-page="home-content.php"]')?.click();
    }

    document.querySelectorAll('[data-open-reschedule]').forEach(button => {
        button.addEventListener('click', () => {
            appointmentInput.value = button.dataset.appointmentId;
            currentLabel.textContent = button.dataset.appointmentLabel;
            scheduleInput.value = '';
            submitButton.disabled = true;
            let visibleCount = 0;
            document.querySelectorAll('[data-reschedule-schedule]').forEach(option => {
                const isCurrent = option.dataset.scheduleId === button.dataset.currentScheduleId;
                option.hidden = isCurrent;
                option.classList.remove('is-selected');
                option.setAttribute('aria-pressed', 'false');
                if (!isCurrent) visibleCount++;
            });
            emptyState.hidden = visibleCount > 0;
            modal.show();
        });
    });

    document.querySelectorAll('[data-reschedule-schedule]').forEach(option => {
        option.addEventListener('click', () => {
            document.querySelectorAll('[data-reschedule-schedule]').forEach(item => {
                item.classList.toggle('is-selected', item === option);
                item.setAttribute('aria-pressed', item === option ? 'true' : 'false');
            });
            scheduleInput.value = option.dataset.scheduleId;
            submitButton.disabled = false;
        });
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!scheduleInput.value || !form.reportValidity()) return;
        const data = new FormData(form);
        data.append('action', 'submit');
        data.append('csrf_token', token);
        LoadingUI.setButton(submitButton, true, 'Sending…');
        try {
            const response = await fetch(controller, { method: 'POST', body: data });
            const result = await response.json();
            window.showToast(result.message || 'Unable to send request.', result.success);
            if (result.success) {
                modal.hide();
                refreshHome();
            }
        } catch (error) {
            window.showToast('Network error. Please try again.', false);
        } finally {
            LoadingUI.setButton(submitButton, false);
        }
    });

    document.querySelectorAll('[data-withdraw-reschedule]').forEach(button => {
        button.addEventListener('click', async () => {
            if (!window.confirm('Withdraw this pending reschedule request? Your original appointment will remain confirmed.')) return;
            const data = new FormData();
            data.append('action', 'withdraw');
            data.append('request_id', button.dataset.withdrawReschedule);
            data.append('csrf_token', token);
            LoadingUI.setButton(button, true, 'Withdrawing…');
            try {
                const response = await fetch(controller, { method: 'POST', body: data });
                const result = await response.json();
                window.showToast(result.message || 'Unable to withdraw request.', result.success);
                if (result.success) refreshHome();
            } catch (error) {
                window.showToast('Network error. Please try again.', false);
                LoadingUI.setButton(button, false);
            }
        });
    });
})();
</script>
