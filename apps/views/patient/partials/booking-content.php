<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/clinicModel.php';
require_once __DIR__ . '/../../../models/scheduleModel.php';
require_once __DIR__ . '/../../../models/serviceModel.php';
require_once __DIR__ . '/../../../helpers/patientEligibility.php';
require_once __DIR__ . '/../../../helpers/bookingPolicy.php';
$appointmentRules = require __DIR__ . '/../../../../config/appointment.php';
$maxServicesPerVisit = max(1, (int) ($appointmentRules['max_services_per_visit'] ?? 5));

$db = new Database();
$conn = $db->connect();
$patient = (new Patient($conn))->getPatientByUserId($_SESSION['user_id']);
$minimumPatientAge = PatientEligibility::minimumAge($conn);
$bookingEligibility = PatientEligibility::assess($patient['birthdate'] ?? '', $minimumPatientAge);
$minimumBookingLeadDays = BookingPolicy::minimumLeadDays($conn);
$earliestBookableDate = BookingPolicy::earliestBookableDate($minimumBookingLeadDays);
$earliestBookableLabel = date('F j, Y', strtotime($earliestBookableDate));
$bookingNoticeSummary = $minimumBookingLeadDays === 0
    ? 'Same-day requests are allowed while a future clinic window is available.'
    : 'Appointments require at least ' . $minimumBookingLeadDays . ' calendar '
        . ($minimumBookingLeadDays === 1 ? 'day' : 'days') . ' of advance notice.';
$eligibleOnLabel = !empty($bookingEligibility['eligible_on'])
    ? date('F j, Y', strtotime($bookingEligibility['eligible_on']))
    : '';
$clinics = (new Clinic($conn))->getAllClinics();
$scheduleModel = new Schedule($conn);
$serviceRows = (new ServiceModel($conn))->getHomepageServices();

$schedulesByClinic = [];
foreach ($clinics as $clinic) {
    $schedulesByClinic[(int) $clinic['clinic_id']] = $scheduleModel->getAvailableSchedulesByClinic(
        $clinic['clinic_id'],
        $earliestBookableDate
    );
}

$serviceCategories = [];
foreach ($serviceRows as $row) {
    if (empty($row['service_id'])) continue;
    $categoryId = (int) $row['category_id'];
    if (!isset($serviceCategories[$categoryId])) {
        $serviceCategories[$categoryId] = [
            'name' => $row['category_name'],
            'services' => [],
        ];
    }
    $serviceCategories[$categoryId]['services'][] = $row;
}

$calculatedAge = $patient['age'] ?? '';
if (!empty($patient['birthdate'])) {
    $birthdate = DateTimeImmutable::createFromFormat('Y-m-d', $patient['birthdate']);
    $today = new DateTimeImmutable('today');
    if ($birthdate && $birthdate->format('Y-m-d') === $patient['birthdate'] && $birthdate <= $today) {
        $calculatedAge = $birthdate->diff($today)->y;
    }
}

$profileFields = [
    'Name' => trim(($patient['firstname'] ?? '') . ' ' . ($patient['middlename'] ?? '') . ' ' . ($patient['lastname'] ?? '')),
    'Birthdate' => !empty($patient['birthdate']) ? date('F j, Y', strtotime($patient['birthdate'])) : '',
    'Age' => $calculatedAge,
    'Gender' => $patient['gender'] ?? '',
    'Phone' => $patient['phone_number'] ?? '',
    'Email' => $patient['email'] ?? '',
];
$missingProfile = array_keys(array_filter($profileFields, static fn($value) => trim((string) $value) === ''));
$bookingSteps = ['Clinic', 'Schedule', 'Services & review'];
?>

<div class="vd-booking-content">
    <p class="text-muted small mb-0">
        <?= $bookingEligibility['eligible']
            ? 'Select a clinic, choose an open date, then pick one or more services. ' . $bookingNoticeSummary
            : 'Online appointment requests become available after the patient eligibility requirement is met.' ?>
    </p>

    <?php if (!$bookingEligibility['eligible']): ?>
    <section class="vd-booking-eligibility-panel" aria-labelledby="bookingEligibilityTitle">
        <span class="vd-booking-eligibility-icon" aria-hidden="true"><i class="ti ti-calendar-exclamation"></i></span>
        <div class="vd-booking-eligibility-copy">
            <p class="vd-section-label mb-1">Patient eligibility</p>
            <h2 id="bookingEligibilityTitle"><?= $bookingEligibility['valid'] ? 'Booking is not available yet' : 'Confirm your birthdate' ?></h2>
            <p><?= htmlspecialchars($bookingEligibility['message']) ?></p>
            <?php if ($bookingEligibility['valid'] && $eligibleOnLabel !== ''): ?>
            <p class="vd-booking-eligibility-detail">Based on the birthdate in your profile, online booking becomes available on <strong><?= htmlspecialchars($eligibleOnLabel) ?></strong>.</p>
            <?php else: ?>
            <p class="vd-booking-eligibility-detail">Review your patient profile and enter a valid birthdate before requesting an appointment.</p>
            <?php endif; ?>
            <a href="#profile-content.php" class="btn vd-btn-outline btn-sm"><i class="ti ti-user-edit" aria-hidden="true"></i> Review Profile</a>
        </div>
    </section>
    <?php else: ?>
    <section class="vd-booking-wizard" aria-label="Book an appointment">
    <nav class="vd-booking-progress" aria-label="Booking steps">
        <div class="vd-booking-progress-summary">
            <span id="bookingStepCounter">Step 1 of <?= count($bookingSteps) ?></span>
            <strong id="bookingStepTitle"><?= htmlspecialchars($bookingSteps[0]) ?></strong>
        </div>
        <div class="vd-booking-progress-track" id="bookingProgress" role="progressbar"
            aria-label="Booking progress" aria-valuemin="1"
            aria-valuemax="<?= count($bookingSteps) ?>" aria-valuenow="1">
            <span id="bookingProgressBar"></span>
        </div>
        <ol class="vd-booking-step-list">
            <?php foreach ($bookingSteps as $index => $step): ?>
            <li>
                <button type="button" class="vd-booking-step-button" data-booking-step-target="<?= $index ?>"
                    aria-controls="bookingStepPanel<?= $index ?>"
                    <?= $index > 0 ? 'disabled' : '' ?>
                    <?= $index === 0 ? 'aria-current="step"' : '' ?>>
                    <span><?= $index + 1 ?></span>
                    <?= htmlspecialchars($step) ?>
                </button>
            </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <div class="vd-booking-steps">
    <section class="vd-booking-step" id="bookingStepPanel0" data-booking-step="0" data-step-title="Choose a clinic">
        <header class="vd-booking-step-header">
            <div>
                <span class="vd-section-label">Step 1</span>
                <h2 id="bookingClinicTitle">Choose a clinic</h2>
                <p>Select the branch where you want to receive care.</p>
            </div>
            <span aria-hidden="true">01</span>
        </header>
    <div class="vd-clinic-switch" role="group" aria-labelledby="bookingClinicTitle">
        <?php foreach ($clinics as $index => $clinic): ?>
        <button type="button" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                class="vd-clinic-switch-btn <?= $index === 0 ? 'active' : '' ?>"
                data-clinic-id="<?= (int) $clinic['clinic_id'] ?>"
                data-clinic-name="<?= htmlspecialchars($clinic['clinic_name'], ENT_QUOTES) ?>">
            <i class="ti ti-building-hospital" aria-hidden="true"></i>
            <?= htmlspecialchars($clinic['clinic_name']) ?>
        </button>
        <?php endforeach; ?>
    </div>
    </section>

    <section class="vd-booking-step" id="bookingStepPanel1" data-booking-step="1" data-step-title="Choose a schedule" hidden>
        <header class="vd-booking-step-header">
            <div>
                <span class="vd-section-label">Step 2</span>
                <h2 id="bookingScheduleTitle">Choose a schedule</h2>
                <p>Available appointment dates begin <?= htmlspecialchars($earliestBookableLabel) ?> under the clinic’s current notice policy.</p>
            </div>
            <span class="vd-topbar-date" id="bookingClinicLabel"></span>
        </header>
        <div class="vd-booking-arrival-policy"><i class="ti ti-user-clock" aria-hidden="true"></i><span><strong>Arrive by the opening time or earlier.</strong> Patients are served first come, first served during the clinic window.</span></div>
        <div class="vd-booking-schedule-grid" id="bookingScheduleGrid"></div>
        <div class="vd-empty-state d-none" id="bookingScheduleEmpty">No schedules are available for this clinic on or after <?= htmlspecialchars($earliestBookableLabel) ?>.</div>
    </section>

    <section class="vd-booking-step" id="bookingStepPanel2" data-booking-step="2" data-step-title="Services and review" hidden>
        <header class="vd-booking-step-header">
            <div>
                <span class="vd-section-label">Step 3</span>
                <h2 id="bookingServicesTitle">Services &amp; Review</h2>
                <p>Select the care you need, then review and send your request.</p>
            </div>
            <span class="vd-booking-selected-date" id="bookingSelectedDate"></span>
        </header>
        <div class="vd-booking-form-body">
            <?php if (!empty($missingProfile)): ?>
            <div class="alert alert-warning small">
                Your profile is missing: <?= htmlspecialchars(implode(', ', $missingProfile)) ?>.
                You can still request this appointment. Clinic staff will complete the missing information during check-in. You may <a href="#profile-content.php" class="alert-link" data-open-profile>view your profile</a> now.
            </div>
            <?php endif; ?>

            <details class="vd-booking-patient-review">
                <summary>
                    <span>
                        <small>Booking for</small>
                        <strong><?= htmlspecialchars($profileFields['Name'] ?: 'Patient profile') ?></strong>
                    </span>
                    <span class="vd-booking-patient-review-action">View details <i class="ti ti-chevron-down" aria-hidden="true"></i></span>
                </summary>
            <div class="vd-booking-profile-grid">
                <?php foreach ($profileFields as $label => $value): ?>
                <div class="vd-booking-profile-item">
                    <span><?= htmlspecialchars($label) ?></span>
                    <strong><?= trim((string) $value) !== '' ? htmlspecialchars($value) : 'Not provided' ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
            </details>

            <form id="dashboardBookingForm">
                <input type="hidden" name="action" value="book">
                <?php $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)); ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="clinic_id" id="dashboardClinicInput">
                <input type="hidden" name="schedule_id" id="dashboardScheduleInput">

                <div class="vd-booking-services-intro">
                    <p class="vd-section-label mb-1">Services</p>
                    <p>Select up to <?= $maxServicesPerVisit ?> services for this visit.</p>
                </div>
                <div class="vd-booking-service-groups">
                    <?php foreach ($serviceCategories as $categoryId => $category): ?>
                    <section class="vd-booking-service-group" aria-labelledby="bookingServiceCategory<?= (int) $categoryId ?>">
                        <header>
                            <h3 id="bookingServiceCategory<?= (int) $categoryId ?>"><?= htmlspecialchars($category['name']) ?></h3>
                            <span><?= count($category['services']) ?> service<?= count($category['services']) === 1 ? '' : 's' ?></span>
                        </header>
                        <div class="vd-booking-service-options">
                        <?php foreach ($category['services'] as $service): ?>
                        <label class="vd-booking-service-option">
                            <input type="checkbox" name="service_ids[]" value="<?= (int) $service['service_id'] ?>">
                            <span class="vd-booking-service-card">
                                <span class="vd-booking-service-icon"><i class="<?= htmlspecialchars($service['service_icon'] ?: 'fa-solid fa-tooth', ENT_QUOTES) ?>" aria-hidden="true"></i></span>
                                <span class="vd-booking-service-copy">
                                    <strong><?= htmlspecialchars($service['service_name']) ?></strong>
                                    <small><?= htmlspecialchars($service['service_description'] ?: 'Contact the clinic for more information about this service.') ?></small>
                                </span>
                                <span class="vd-booking-service-check" aria-hidden="true"><i class="ti ti-check"></i></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>
                </div>

                <div id="dashboardBookingError" class="alert alert-danger d-none mt-3" role="alert" aria-live="polite"></div>
            </form>
        </div>
    </section>
    </div>

    <footer class="vd-booking-wizard-actions" aria-label="Booking navigation and request status">
        <p class="vd-booking-selection-summary" id="bookingSelectionSummary" aria-live="polite">
            <strong>Choose a clinic</strong>
            Select the branch where you want to receive care.
        </p>
        <div class="vd-booking-action-buttons">
            <button type="button" class="btn vd-booking-back-button" id="bookingBack" disabled>
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back
            </button>
            <button type="button" class="btn vd-btn-gold" id="bookingNext">
                Continue
                <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </button>
            <button type="submit" class="btn vd-btn-gold" id="dashboardBookingSubmit"
                form="dashboardBookingForm" hidden disabled>
                Request Appointment
            </button>
        </div>
    </footer>
    </section>
    <?php endif; ?>
</div>

<?php if ($bookingEligibility['eligible']): ?>
<div class="modal fade vd-booking-confirmation-modal" id="bookingConfirmationModal" tabindex="-1"
    aria-labelledby="bookingConfirmationTitle" aria-describedby="bookingConfirmationDescription" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="vd-booking-confirmation-kicker">Final review</span>
                    <h2 class="modal-title" id="bookingConfirmationTitle">Review appointment request</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close confirmation"></button>
            </div>
            <div class="modal-body">
                <p class="vd-booking-confirmation-intro" id="bookingConfirmationDescription">
                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                    <span>This sends a request to the clinic. Your appointment is not confirmed yet. Please wait for the clinic’s confirmation email before treating this schedule as confirmed.</span>
                </p>

                <dl class="vd-booking-confirmation-details">
                    <div>
                        <dt>Patient</dt>
                        <dd><?= htmlspecialchars($profileFields['Name'] ?: 'Patient profile') ?></dd>
                    </div>
                    <div>
                        <dt>Clinic</dt>
                        <dd id="bookingConfirmationClinic">—</dd>
                    </div>
                    <div class="vd-booking-confirmation-wide">
                        <dt>Schedule</dt>
                        <dd id="bookingConfirmationSchedule">—</dd>
                    </div>
                    <div class="vd-booking-confirmation-wide">
                        <dt>Arrival</dt>
                        <dd id="bookingConfirmationArrival">—</dd>
                    </div>
                </dl>

                <section class="vd-booking-confirmation-services" aria-labelledby="bookingConfirmationServicesTitle">
                    <header>
                        <h3 id="bookingConfirmationServicesTitle">Selected services</h3>
                        <span id="bookingConfirmationServiceCount"></span>
                    </header>
                    <ul id="bookingConfirmationServiceList"></ul>
                </section>

                <div class="alert alert-danger d-none vd-booking-confirmation-error" id="bookingConfirmationError"
                    role="alert" aria-live="polite"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Back to edit</button>
                <button type="button" class="btn vd-btn-gold" id="bookingConfirmRequest">
                    <i class="ti ti-calendar-check" aria-hidden="true"></i>
                    Confirm request
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const schedulesByClinic = <?= json_encode($schedulesByClinic, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const clinicButtons = Array.from(document.querySelectorAll('.vd-clinic-switch-btn'));
    const bookingSteps = Array.from(document.querySelectorAll('[data-booking-step]'));
    const bookingStepButtons = Array.from(document.querySelectorAll('[data-booking-step-target]'));
    const grid = document.getElementById('bookingScheduleGrid');
    const empty = document.getElementById('bookingScheduleEmpty');
    const clinicInput = document.getElementById('dashboardClinicInput');
    const scheduleInput = document.getElementById('dashboardScheduleInput');
    const clinicLabel = document.getElementById('bookingClinicLabel');
    const selectedDate = document.getElementById('bookingSelectedDate');
    const errorBox = document.getElementById('dashboardBookingError');
    const selectionSummary = document.getElementById('bookingSelectionSummary');
    const serviceCheckboxes = Array.from(document.querySelectorAll('input[name="service_ids[]"]'));
    const bookingStepCounter = document.getElementById('bookingStepCounter');
    const bookingStepTitle = document.getElementById('bookingStepTitle');
    const bookingProgress = document.getElementById('bookingProgress');
    const bookingProgressBar = document.getElementById('bookingProgressBar');
    const backButton = document.getElementById('bookingBack');
    const nextButton = document.getElementById('bookingNext');
    const submitButton = document.getElementById('dashboardBookingSubmit');
    const bookingForm = document.getElementById('dashboardBookingForm');
    const confirmationModalElement = document.getElementById('bookingConfirmationModal');
    const confirmationModal = bootstrap.Modal.getOrCreateInstance(confirmationModalElement);
    const confirmationClinic = document.getElementById('bookingConfirmationClinic');
    const confirmationSchedule = document.getElementById('bookingConfirmationSchedule');
    const confirmationArrival = document.getElementById('bookingConfirmationArrival');
    const confirmationServiceCount = document.getElementById('bookingConfirmationServiceCount');
    const confirmationServiceList = document.getElementById('bookingConfirmationServiceList');
    const confirmationError = document.getElementById('bookingConfirmationError');
    const confirmRequestButton = document.getElementById('bookingConfirmRequest');
    const confirmationDismissButtons = Array.from(confirmationModalElement.querySelectorAll('[data-bs-dismiss="modal"]'));
    const maxServicesPerVisit = <?= $maxServicesPerVisit ?>;
    let currentStep = 0;
    let furthestStep = 0;
    let selectedSchedule = null;
    let isSubmitting = false;

    confirmationModalElement.addEventListener('hide.bs.modal', event => {
        if (isSubmitting) event.preventDefault();
    });

    function setSelectionSummary(title, detail) {
        const strong = document.createElement('strong');
        strong.textContent = title;
        selectionSummary.replaceChildren(strong, document.createTextNode(detail));
    }

    function updateSelectionSummary() {
        const selectedCount = serviceCheckboxes.filter(input => input.checked).length;
        serviceCheckboxes.forEach(input => {
            input.disabled = selectedCount >= maxServicesPerVisit && !input.checked;
        });
        submitButton.disabled = selectedCount === 0;
        if (currentStep !== 2) return;
        setSelectionSummary(
            selectedCount ? selectedCount + ' of ' + maxServicesPerVisit + ' services selected' : 'No services selected',
            selectedCount >= maxServicesPerVisit
                ? 'Service limit reached. Remove one to choose another.'
                : selectedCount
                    ? 'Review your choices, then request the appointment.'
                    : 'Choose at least one and up to ' + maxServicesPerVisit + ' services.'
        );
    }

    serviceCheckboxes.forEach(input => input.addEventListener('change', updateSelectionSummary));

    function updateActionSummary() {
        if (currentStep === 0) {
            const selectedClinic = clinicButtons.find(button => button.classList.contains('active'));
            setSelectionSummary(
                selectedClinic ? selectedClinic.dataset.clinicName : 'Choose a clinic',
                selectedClinic ? 'Continue to view this clinic’s schedules.' : 'Select the branch where you want to receive care.'
            );
            return;
        }
        if (currentStep === 1) {
            setSelectionSummary(
                scheduleInput.value ? 'Schedule selected' : 'Choose a schedule',
                scheduleInput.value ? selectedDate.textContent : 'Select an available clinic window to continue.'
            );
            return;
        }
        updateSelectionSummary();
    }

    function showBookingStep(index, focusHeading = false) {
        currentStep = Math.max(0, Math.min(index, bookingSteps.length - 1));
        bookingSteps.forEach((step, stepIndex) => {
            step.hidden = stepIndex !== currentStep;
        });
        bookingStepButtons.forEach((button, stepIndex) => {
            button.disabled = stepIndex > furthestStep;
            button.classList.toggle('is-complete', stepIndex < currentStep);
            if (stepIndex === currentStep) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
        });

        bookingStepCounter.textContent = 'Step ' + (currentStep + 1) + ' of ' + bookingSteps.length;
        bookingStepTitle.textContent = bookingSteps[currentStep].dataset.stepTitle;
        bookingProgress.setAttribute('aria-valuenow', String(currentStep + 1));
        bookingProgressBar.style.width = ((currentStep + 1) / bookingSteps.length * 100) + '%';
        backButton.disabled = currentStep === 0;
        nextButton.hidden = currentStep === bookingSteps.length - 1;
        nextButton.disabled = currentStep === 0 ? clinicButtons.length === 0 : !scheduleInput.value;
        submitButton.hidden = currentStep !== bookingSteps.length - 1;
        updateActionSummary();

        if (focusHeading) {
            const heading = bookingSteps[currentStep].querySelector('h2');
            if (heading) {
                heading.tabIndex = -1;
                heading.focus({ preventScroll: true });
            }
            document.querySelector('.vd-booking-progress').scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                block: 'start'
            });
        }
    }

    function parseLocalDate(dateString) {
        const [year, month, day] = dateString.split('-').map(Number);
        return new Date(year, month - 1, day);
    }

    function formatTime(timeString) {
        const [hours, minutes] = timeString.split(':').map(Number);
        const suffix = hours >= 12 ? 'PM' : 'AM';
        const hour = hours % 12 || 12;
        return `${hour}:${String(minutes).padStart(2, '0')} ${suffix}`;
    }

    function formatWindow(schedule) {
        return `${formatTime(schedule.start_time)}–${formatTime(schedule.end_time)}`;
    }

    function chooseSchedule(card, clinicId, schedule) {
        grid.querySelectorAll('.vd-booking-schedule-card').forEach(item => {
            const isSelected = item === card;
            item.classList.toggle('selected', isSelected);
            item.setAttribute('aria-pressed', String(isSelected));
        });
        clinicInput.value = clinicId;
        scheduleInput.value = schedule.schedule_id;
        selectedSchedule = schedule;
        selectedDate.textContent = parseLocalDate(schedule.sched_date).toLocaleDateString('en-PH', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        }) + ` · ${formatWindow(schedule)} · Arrive by ${formatTime(schedule.start_time)}`;
        furthestStep = Math.max(furthestStep, 2);
        nextButton.disabled = false;
        updateActionSummary();
    }

    function renderSchedules(button) {
        const clinicId = button.dataset.clinicId;
        const schedules = schedulesByClinic[clinicId] || [];
        clinicButtons.forEach(item => {
            const isSelected = item === button;
            item.classList.toggle('active', isSelected);
            item.setAttribute('aria-pressed', String(isSelected));
        });
        clinicLabel.textContent = button.dataset.clinicName;
        grid.innerHTML = '';
        scheduleInput.value = '';
        selectedSchedule = null;
        selectedDate.textContent = '';
        furthestStep = Math.min(furthestStep, 1);
        empty.classList.toggle('d-none', schedules.length > 0);
        grid.classList.toggle('d-none', schedules.length === 0);

        schedules.forEach(schedule => {
            const remaining = Number(schedule.available_slots);
            const isFull = remaining <= 0;
            const date = parseLocalDate(schedule.sched_date);
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'vd-booking-schedule-card' + (isFull ? ' full' : '');
            card.disabled = isFull;
            card.setAttribute('aria-pressed', 'false');
            card.innerHTML = `
                <span class="vd-booking-schedule-date">
                    <span class="vd-booking-schedule-weekday">${date.toLocaleDateString('en-PH', { weekday: 'short' })}</span>
                    <strong>${String(date.getDate()).padStart(2, '0')}</strong>
                    <span class="vd-booking-schedule-month">${date.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' })}</span>
                </span>
                <span class="vd-booking-schedule-info">
                    <span class="vd-booking-schedule-window"><i class="ti ti-clock" aria-hidden="true"></i>${formatWindow(schedule)}</span>
                    <small class="vd-booking-arrive-by">Arrive by ${formatTime(schedule.start_time)} or earlier</small>
                    <small class="vd-booking-slots">${isFull ? 'Fully booked' : remaining + ' slot' + (remaining === 1 ? '' : 's') + ' left'}</small>
                </span>
                <span class="vd-booking-schedule-check" aria-hidden="true"><i class="ti ti-check"></i></span>`;
            if (!isFull) card.addEventListener('click', () => chooseSchedule(card, clinicId, schedule));
            grid.appendChild(card);
        });
        updateActionSummary();
    }

    clinicButtons.forEach(button => button.addEventListener('click', () => {
        renderSchedules(button);
        showBookingStep(0);
    }));
    if (clinicButtons[0]) renderSchedules(clinicButtons[0]);
    updateSelectionSummary();
    showBookingStep(0);

    backButton.addEventListener('click', () => showBookingStep(currentStep - 1, true));
    nextButton.addEventListener('click', () => {
        if (currentStep === 1 && !scheduleInput.value) return;
        furthestStep = Math.max(furthestStep, currentStep + 1);
        showBookingStep(currentStep + 1, true);
    });
    bookingStepButtons.forEach(button => {
        button.addEventListener('click', () => {
            const target = Number(button.dataset.bookingStepTarget);
            if (!Number.isInteger(target) || target > furthestStep) return;
            showBookingStep(target, true);
        });
    });

    document.querySelector('[data-open-profile]')?.addEventListener('click', function (event) {
        event.preventDefault();
        document.querySelector('[data-page="profile-content.php"]')?.click();
    });

    function selectedServiceNames() {
        return serviceCheckboxes
            .filter(input => input.checked)
            .map(input => input.closest('.vd-booking-service-option')?.querySelector('.vd-booking-service-copy strong')?.textContent.trim())
            .filter(Boolean);
    }

    function populateConfirmationPreview() {
        const selectedClinic = clinicButtons.find(button => button.classList.contains('active'));
        const serviceNames = selectedServiceNames();
        confirmationClinic.textContent = selectedClinic?.dataset.clinicName || '—';
        confirmationSchedule.textContent = selectedSchedule
            ? parseLocalDate(selectedSchedule.sched_date).toLocaleDateString('en-PH', {
                weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
            }) + ' · ' + formatWindow(selectedSchedule)
            : '—';
        confirmationArrival.textContent = selectedSchedule
            ? 'By ' + formatTime(selectedSchedule.start_time) + ' or earlier'
            : '—';
        confirmationServiceCount.textContent = serviceNames.length + (serviceNames.length === 1 ? ' service' : ' services');
        confirmationServiceList.replaceChildren(...serviceNames.map(name => {
            const item = document.createElement('li');
            item.textContent = name;
            return item;
        }));
        confirmationError.classList.add('d-none');
        confirmationError.textContent = '';
    }

    bookingForm.addEventListener('submit', function (event) {
        event.preventDefault();
        errorBox.classList.add('d-none');
        if (!scheduleInput.value || !bookingForm.querySelector('input[name="service_ids[]"]:checked')) {
            errorBox.textContent = 'Please select a schedule and at least one service.';
            errorBox.classList.remove('d-none');
            return;
        }
        const selectedServiceCount = bookingForm.querySelectorAll('input[name="service_ids[]"]:checked').length;
        if (selectedServiceCount > maxServicesPerVisit) {
            errorBox.textContent = `You can select up to ${maxServicesPerVisit} services per visit.`;
            errorBox.classList.remove('d-none');
            return;
        }

        populateConfirmationPreview();
        confirmationModal.show();
    });

    confirmRequestButton.addEventListener('click', async function () {
        if (isSubmitting) return;
        isSubmitting = true;
        confirmationError.classList.add('d-none');
        confirmationDismissButtons.forEach(button => button.disabled = true);
        LoadingUI.setButton(confirmRequestButton, true, 'Sending request…');

        try {
            const response = await fetch('../../controllers/appointmentController.php', {
                method: 'POST',
                body: new FormData(bookingForm)
            });
            const responseBody = await response.text();
            let result;
            try {
                result = JSON.parse(responseBody);
            } catch (parseError) {
                // Do not expose a raw JSON parser message or encourage an
                // immediate duplicate booking when PHP returned unexpected text.
                console.error('Unexpected booking response:', responseBody, parseError);
                throw new Error('The server response could not be read. Check Home or History before submitting again.');
            }
            if (!result.success) throw new Error(result.message || 'Booking failed.');
            confirmationModalElement.addEventListener('hidden.bs.modal', () => {
                window.showToast('Appointment request submitted for clinic review.', true);
                document.querySelector('[data-page="home-content.php"]')?.click();
            }, { once: true });
            isSubmitting = false;
            confirmationModal.hide();
        } catch (error) {
            confirmationError.textContent = error.message || 'Unable to submit your appointment. Please try again.';
            confirmationError.classList.remove('d-none');
            isSubmitting = false;
            confirmationDismissButtons.forEach(button => button.disabled = false);
            LoadingUI.setButton(confirmRequestButton, false);
            confirmRequestButton.focus();
        }
    });
})();
</script>
<?php endif; ?>
