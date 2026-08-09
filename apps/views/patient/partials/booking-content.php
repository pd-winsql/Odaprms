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

$db = new Database();
$conn = $db->connect();
$patient = (new Patient($conn))->getPatientByUserId($_SESSION['user_id']);
$clinics = (new Clinic($conn))->getAllClinics();
$scheduleModel = new Schedule($conn);
$serviceRows = (new ServiceModel($conn))->getHomepageServices();

$schedulesByClinic = [];
foreach ($clinics as $clinic) {
    $schedulesByClinic[(int) $clinic['clinic_id']] = $scheduleModel->getAvailableSchedulesByClinic($clinic['clinic_id']);
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
?>

<div class="d-flex flex-column gap-4 vd-booking-content">
    <div>
        <div class="vd-welcome-greet">BOOK AN APPOINTMENT</div>
        <div class="vd-welcome-name">Choose an available schedule</div>
        <p class="text-muted small mb-0 mt-2">Select a clinic, choose an open date, then pick one or more services.</p>
    </div>

    <div class="vd-clinic-switch" role="tablist" aria-label="Clinic">
        <?php foreach ($clinics as $index => $clinic): ?>
        <button type="button" class="vd-clinic-switch-btn <?= $index === 0 ? 'active' : '' ?>"
                data-clinic-id="<?= (int) $clinic['clinic_id'] ?>"
                data-clinic-name="<?= htmlspecialchars($clinic['clinic_name'], ENT_QUOTES) ?>">
            <i class="ti ti-building-hospital"></i>
            <?= htmlspecialchars($clinic['clinic_name']) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Available Schedules</span>
            <span class="vd-topbar-date" id="bookingClinicLabel"></span>
        </div>
        <div class="vd-booking-schedule-grid" id="bookingScheduleGrid"></div>
        <div class="vd-empty-state d-none" id="bookingScheduleEmpty">No available schedules for this clinic right now.</div>
    </div>

    <div class="vd-dash-card d-none" id="bookingDetailsCard">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Complete Your Booking</span>
            <span class="vd-booking-selected-date" id="bookingSelectedDate"></span>
        </div>
        <div class="vd-booking-form-body">
            <?php if (!empty($missingProfile)): ?>
            <div class="alert alert-warning small">
                Your profile is missing: <?= htmlspecialchars(implode(', ', $missingProfile)) ?>.
                You can still request this appointment, but please <a href="#profile-content.php" class="alert-link" data-open-profile>complete your profile</a> for faster processing.
            </div>
            <?php endif; ?>

            <p class="vd-section-label">Patient Information</p>
            <div class="vd-booking-profile-grid mb-4">
                <?php foreach ($profileFields as $label => $value): ?>
                <div class="vd-booking-profile-item">
                    <span><?= htmlspecialchars($label) ?></span>
                    <strong><?= trim((string) $value) !== '' ? htmlspecialchars($value) : 'Not provided' ?></strong>
                </div>
                <?php endforeach; ?>
            </div>

            <form id="dashboardBookingForm">
                <input type="hidden" name="action" value="book">
                <?php $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)); ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="clinic_id" id="dashboardClinicInput">
                <input type="hidden" name="schedule_id" id="dashboardScheduleInput">

                <p class="vd-section-label mb-1">Services</p>
                <p class="text-muted small">Select one or more services for this visit.</p>
                <div class="vd-booking-service-list">
                    <?php foreach ($serviceCategories as $category): ?>
                    <div class="vd-booking-service-category">
                        <div class="vd-booking-service-category-name"><?= htmlspecialchars($category['name']) ?></div>
                        <div class="vd-booking-service-options">
                            <?php foreach ($category['services'] as $service): ?>
                            <label class="vd-booking-service-option">
                                <input type="checkbox" name="service_ids[]" value="<?= (int) $service['service_id'] ?>">
                                <span class="vd-booking-service-card">
                                    <i class="ti ti-tooth"></i>
                                    <span class="vd-booking-service-copy">
                                        <strong><?= htmlspecialchars($service['service_name']) ?></strong>
                                        <small><?= htmlspecialchars($service['service_description'] ?: 'Contact the clinic for more information about this service.') ?></small>
                                    </span>
                                    <i class="ti ti-check vd-booking-service-check" aria-hidden="true"></i>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="dashboardBookingError" class="alert alert-danger d-none mt-3"></div>
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn vd-btn-gold px-4" id="dashboardBookingSubmit">
                        Request Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const schedulesByClinic = <?= json_encode($schedulesByClinic, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const clinicButtons = Array.from(document.querySelectorAll('.vd-clinic-switch-btn'));
    const grid = document.getElementById('bookingScheduleGrid');
    const empty = document.getElementById('bookingScheduleEmpty');
    const details = document.getElementById('bookingDetailsCard');
    const clinicInput = document.getElementById('dashboardClinicInput');
    const scheduleInput = document.getElementById('dashboardScheduleInput');
    const clinicLabel = document.getElementById('bookingClinicLabel');
    const selectedDate = document.getElementById('bookingSelectedDate');
    const errorBox = document.getElementById('dashboardBookingError');

    function parseLocalDate(dateString) {
        const [year, month, day] = dateString.split('-').map(Number);
        return new Date(year, month - 1, day);
    }

    function chooseSchedule(card, clinicId, schedule) {
        grid.querySelectorAll('.vd-booking-schedule-card').forEach(item => item.classList.remove('selected'));
        card.classList.add('selected');
        clinicInput.value = clinicId;
        scheduleInput.value = schedule.schedule_id;
        selectedDate.textContent = parseLocalDate(schedule.sched_date).toLocaleDateString('en-PH', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        details.classList.remove('d-none');
        details.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderSchedules(button) {
        const clinicId = button.dataset.clinicId;
        const schedules = schedulesByClinic[clinicId] || [];
        clinicButtons.forEach(item => item.classList.toggle('active', item === button));
        clinicLabel.textContent = button.dataset.clinicName;
        grid.innerHTML = '';
        details.classList.add('d-none');
        scheduleInput.value = '';
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
            card.innerHTML = `
                <span class="vd-booking-schedule-weekday">${date.toLocaleDateString('en-PH', { weekday: 'short' })}</span>
                <strong>${String(date.getDate()).padStart(2, '0')}</strong>
                <span>${date.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' })}</span>
                <small>${isFull ? 'Fully booked' : remaining + ' slot' + (remaining === 1 ? '' : 's') + ' left'}</small>`;
            if (!isFull) card.addEventListener('click', () => chooseSchedule(card, clinicId, schedule));
            grid.appendChild(card);
        });
    }

    clinicButtons.forEach(button => button.addEventListener('click', () => renderSchedules(button)));
    if (clinicButtons[0]) renderSchedules(clinicButtons[0]);

    document.querySelector('[data-open-profile]')?.addEventListener('click', function (event) {
        event.preventDefault();
        document.querySelector('[data-page="profile-content.php"]')?.click();
    });

    document.getElementById('dashboardBookingForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        errorBox.classList.add('d-none');
        if (!scheduleInput.value || !this.querySelector('input[name="service_ids[]"]:checked')) {
            errorBox.textContent = 'Please select a schedule and at least one service.';
            errorBox.classList.remove('d-none');
            return;
        }

        const submitButton = document.getElementById('dashboardBookingSubmit');
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
        LoadingUI.setButton(submitButton, true, 'Submitting…');
        try {
            const response = await fetch('../../controllers/appointmentController.php', {
                method: 'POST',
                body: new FormData(this)
            });
            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'Booking failed.');
            window.showToast('Appointment request submitted for clinic review.', true);
            document.querySelector('[data-page="home-content.php"]')?.click();
        } catch (error) {
            errorBox.textContent = error.message || 'Unable to submit your appointment. Please try again.';
            errorBox.classList.remove('d-none');
            LoadingUI.setButton(submitButton, false);
            submitButton.disabled = false;
            submitButton.textContent = 'Request Appointment';
        }
    });
})();
</script>
