<?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Location: login.php?next=booking');
    exit;
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    require_once '../../config/conn.php';
    require_once '../models/clinicModel.php';
    require_once '../models/serviceModel.php';

    $db = new Database();
    $conn = $db->connect();
    $clinicModel = new Clinic($conn);
    $clinics = $clinicModel->getAllClinics();

    $serviceModel = new ServiceModel($conn);
    $serviceRows = $serviceModel->getHomepageServices();

    $serviceCategories = [];
    foreach ($serviceRows as $row) {
        $catId = $row['category_id'];

        if (!isset($serviceCategories[$catId])) {
            $serviceCategories[$catId] = [
                'category_name' => $row['category_name'],
                'services'      => [],
            ];
        }

        if ($row['service_id']) {
            $serviceCategories[$catId]['services'][] = [
                'service_id'          => $row['service_id'],
                'service_name'        => $row['service_name'],
                'service_description' => $row['service_description'],
                'service_icon'        => $row['service_icon'],
            ];
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment | Dr. Aprille Ventura Clinica Dental</title>
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link rel="stylesheet" href="../../public/css/loading.css">
    <script src="../../public/js/loading.js" defer></script>
</head>
<body class="vd-form-page py-5">

    <div class="container">
        <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card vd-page-card border p-4 p-md-5">

            <div class="mb-3">
                <a href="../../index.php" class="btn vd-btn-outline btn-sm">
                    &larr; Back to Home
                </a>
            </div>

            <!-- HEADER -->
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start gap-3 pb-4 mb-4" style="border-bottom:1px solid #d9c9a8;">
                <div>
                <div class="vd-logo-name">Dr. Aprille</div>
                <div class="vd-logo-ventura">VEN<span class="vd-cross">✚</span>URA</div>
                <div class="vd-logo-sub">Clinica Dental</div>
                </div>
                <div class="text-sm-end vd-clinic-meta">
                <strong>DR. APRILLE CABAYU VENTURA</strong><br>
                Alcala Branch – Zone 4, Tupang, Alcala, Cagayan<br>
                Tuguegarao Branch – Bartolome St., Tuguegarao City<br>
                📞 09157631034 &nbsp;|&nbsp; Mon–Sat, 10am–4pm
                </div>
            </div>

            <!-- BOOKING FORM -->
            <div id="formView">
                <h1 class="vd-page-title mb-1">Book an Appointment</h1>
                <p class="text-muted small mb-4">Choose your appointment, then submit the ₱400 GCash deposit within 30 minutes for clinic verification.</p>

                <!-- WIZARD STEPPER -->
                <div class="vd-wizard-steps mb-4">
                    <div class="vd-wizard-step active" data-step="1">
                        <div class="vd-wizard-step-circle">1</div>
                        <div class="vd-wizard-step-label">Details</div>
                    </div>
                    <div class="vd-wizard-step-line"></div>
                    <div class="vd-wizard-step" data-step="2">
                        <div class="vd-wizard-step-circle">2</div>
                        <div class="vd-wizard-step-label">Service</div>
                    </div>
                    <div class="vd-wizard-step-line"></div>
                    <div class="vd-wizard-step" data-step="3">
                        <div class="vd-wizard-step-circle">3</div>
                        <div class="vd-wizard-step-label">Clinic &amp; Schedule</div>
                    </div>
                </div>

                <form id="bookingForm" novalidate>

                <div id="bookingFormError" class="alert alert-danger d-none" role="alert"></div>

                <!-- STEP 1: PATIENT DETAILS -->
                <div class="vd-wizard-panel" data-panel="1">
                    <p class="vd-section-label">Patient Details</p>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                        <label class="vd-label form-label">Last Name</label>
                        <input type="text" name="lastname" class="form-control vd-input" placeholder="Dela Cruz" required>
                        </div>
                        <div class="col-12 col-md-4">
                        <label class="vd-label form-label">First Name</label>
                        <input type="text" name="firstname" class="form-control vd-input" placeholder="Juan" required>
                        </div>
                        <div class="col-12 col-md-4">
                        <label class="vd-label form-label">Middle Name</label>
                        <input type="text" name="middlename" class="form-control vd-input" placeholder="Santos">
                        </div>
                        <div class="col-6 col-md-4">
                        <label class="vd-label form-label">Birthdate</label>
                        <input type="date" name="birthdate" id="birthdate" class="form-control vd-input" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6 col-md-4">
                        <label class="vd-label form-label">Age</label>
                        <input type="number" name="age" id="age" class="form-control vd-input" min="0" max="120" placeholder="Calculated automatically" readonly required>
                        </div>
                        <div class="col-6 col-md-4">
                        <label class="vd-label form-label">Gender</label>
                        <select name="gender" class="form-select vd-input" required>
                            <option value="" disabled selected>— Select —</option>
                            <option>Male</option>
                            <option>Female</option>
                            <option>Prefer not to say</option>
                        </select>
                        </div>
                        <div class="col-12 col-md-6">
                        <label class="vd-label form-label">Phone Number</label>
                        <input type="tel" name="phone_number" id="phoneNumber" class="form-control vd-input" placeholder="09XX XXX XXXX"
                        inputmode="numeric" maxlength="11" title="Please enter your 11-digit phone number, dashes, space, or none are allowed" required>
                        </div>
                        <div class="col-12 col-md-6">
                        <label class="vd-label form-label">Email Address</label>
                        <input type="email" name="email" class="form-control vd-input" placeholder="email@example.com" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-4" style="border-top:1px solid #d9c9a8;">
                        <button type="button" class="btn vd-btn-gold px-4" onclick="goToStep(2)">
                            Next: Select Service <i class="fa-solid fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div><!-- /panel 1 -->

                <!-- STEP 2: SELECT SERVICE -->
                <div class="vd-wizard-panel d-none" data-panel="2">
                    <p class="vd-section-label">Select One or More Dental Services</p>
                    <p class="text-muted small mb-3">Choose every service you would like to discuss during this appointment.</p>

                    <?php if (empty($serviceCategories)): ?>
                    <div class="vd-schedule-empty">
                        No services are available to book at the moment. Please contact the clinic directly.
                    </div>
                    <?php else: ?>
                    <div id="serviceCategories">
                        <?php foreach (array_values($serviceCategories) as $ci => $cat): ?>
                        <?php if (empty($cat['services'])) continue; ?>
                        <div class="vd-svc-category">
                            <div class="vd-svc-category-head" id="svcHead<?= $ci ?>" onclick="toggleServiceCategory(<?= $ci ?>)">
                                <span><?= htmlspecialchars($cat['category_name']) ?></span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="vd-svc-grid" id="svcGrid<?= $ci ?>">
                                <?php foreach ($cat['services'] as $svc): ?>
                                <div class="vd-svc-card" onclick="toggleServiceCard(this)">
                                    <input type="checkbox" class="d-none vd-service-checkbox" name="service_ids[]" value="<?= (int)$svc['service_id'] ?>">
                                    <i class="fa-solid <?= htmlspecialchars($svc['service_icon'] ?: 'fa-tooth') ?>"></i>
                                    <div class="vd-svc-card-name"><?= htmlspecialchars($svc['service_name']) ?></div>
                                    <div class="vd-svc-card-desc"><?= htmlspecialchars($svc['service_description'] ?? '') ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between pt-4" style="border-top:1px solid #d9c9a8;">
                        <button type="button" class="btn vd-btn-outline px-4" onclick="goToStep(1)">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back
                        </button>
                        <button type="button" class="btn vd-btn-gold px-4" onclick="goToStep(3)">
                            Next: Clinic &amp; Schedule <i class="fa-solid fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div><!-- /panel 2 -->

                <!-- STEP 3: CLINIC & SCHEDULE -->
                <div class="vd-wizard-panel d-none" data-panel="3">

                    <!-- SELECT CLINIC -->
                    <p class="vd-section-label">Select Clinic</p>
                    <div class="row g-3 mb-4">
                        <?php foreach ($clinics as $clinic): ?>
                        <div class="col-12 col-sm-6">
                            <label class="vd-clinic-card w-100">
                            <input type="radio" name="clinic_id" value="<?= $clinic['clinic_id'] ?>" class="d-none vd-clinic-radio" required>
                            <div class="vd-clinic-card-inner p-3 rounded">
                                <div class="vd-clinic-tag"><?= htmlspecialchars($clinic['clinic_name']) ?></div>
                                <div class="vd-clinic-address"><?= htmlspecialchars($clinic['clinic_address']) ?></div>
                                <div class="vd-clinic-address">📞 <?= htmlspecialchars($clinic['clinic_contact']) ?></div>
                            </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- DATE & TIME -->
                    <p class="vd-section-label">Preferred Schedule</p>

                    <!-- Prompt: shown before clinic is selected -->
                    <div id="schedulePrompt" class="vd-schedule-prompt">
                        <span>📅</span>
                        <span>Select a clinic above to view available dates.</span>
                    </div>

                    <!-- Loading -->
                    <div id="scheduleLoading" class="vd-schedule-loading d-none">
                        <span class="vd-spinner" aria-hidden="true"></span>
                        Loading available dates&hellip;
                    </div>

                    <!-- Empty -->
                    <div id="scheduleEmpty" class="vd-schedule-empty d-none">
                        No available schedules for this clinic at the moment. Please check back later.
                    </div>

                    <!-- Schedule cards rendered by JS -->
                    <div id="scheduleGrid" class="vd-schedule-grid d-none"></div>

                    <!-- Hidden field carries selected schedule_id -->
                    <input type="hidden" id="scheduleInput" name="schedule_id">

                    <!-- NOTICE -->
                    <div class="vd-notice d-flex gap-2 p-3 mb-4 mt-4 rounded">
                        <span class="vd-notice-icon">ℹ</span>
                        <span class="small">All appointments are <strong>strictly by appointment only</strong> and subject to confirmation. A member of our team will reach out via email within 24 hours to confirm your slot.</span>
                    </div>

                    <input type="hidden" name="action" value="book">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <!-- ACTIONS -->
                    <div class="d-flex justify-content-between pt-4" style="border-top:1px solid #d9c9a8;">
                        <button type="button" class="btn vd-btn-outline px-4" onclick="goToStep(2)">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back
                        </button>
                        <button type="button" class="btn vd-btn-gold px-5" onclick="openReviewModal()">Review &amp; Book Appointment</button>
                    </div>

                </div><!-- /panel 3 -->

                </form>
            </div><!-- /formView -->

            <!-- SUCCESS STATE -->
            <div id="successView" class="d-none text-center py-5">
                <div class="vd-success-icon mx-auto mb-4">✓</div>
                <h2 class="vd-page-title mb-2">Appointment Requested</h2>
                <p class="text-muted small mb-4">Thank you! Your request has been submitted. We'll confirm your schedule within 24 hours.</p>
                <br>
                <button class="btn vd-btn-gold px-4" onclick="resetForm()">Book Another</button>
            </div>

            </div><!-- /card -->
        </div>
        </div>
    </div>

    <!-- REVIEW & CONFIRM MODAL -->
    <div id="reviewModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-bs p-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="vd-modal-title mb-0">Confirm Your Appointment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <p class="text-muted small mb-3">Please review your details before submitting.</p>
            <div id="reviewSummary"></div>
            <div class="d-flex justify-content-end gap-2 mt-4">
            <button class="btn vd-btn-outline" data-bs-dismiss="modal">Edit Details</button>
            <button class="btn vd-btn-gold px-4" onclick="confirmReview(this)">Continue to Payment</button>
            </div>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // ---------------------------------------------------------------
        // WIZARD STEP NAVIGATION
        // ---------------------------------------------------------------

        function resetForm() {
            window.location.reload();
        }

        let activeStep = 1;

        function showStep(step) {
            document.querySelectorAll('.vd-wizard-panel').forEach(p => p.classList.add('d-none'));
            document.querySelector(`.vd-wizard-panel[data-panel="${step}"]`).classList.remove('d-none');

            document.querySelectorAll('.vd-wizard-step').forEach(s => {
                const n = Number(s.dataset.step);
                s.classList.toggle('active', n === step);
                s.classList.toggle('done', n < step);
            });

            activeStep = step;
            bookingError.classList.add('d-none');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function validateStep1() {
            const missing = [];
            const fields = [
                { name: 'lastname',     label: 'Last Name' },
                { name: 'firstname',    label: 'First Name' },
                { name: 'birthdate',    label: 'Birthdate' },
                { name: 'age',          label: 'Age' },
                { name: 'gender',       label: 'Gender' },
                { name: 'phone_number', label: 'Phone Number' },
                { name: 'email',        label: 'Email Address' },
            ];
            fields.forEach(f => {
                const el = bookingForm.elements[f.name];
                if (!el || !el.value || !el.value.trim()) missing.push(f.label);
            });
            return missing;
        }

        function validateStep2() {
            const missing = [];
            if (!document.querySelector('.vd-service-checkbox:checked')) missing.push('Dental Service');
            return missing;
        }

        function validateStep3() {
            const missing = [];
            if (!document.querySelector('input[name="clinic_id"]:checked')) missing.push('Clinic');
            if (!scheduleInput.value) missing.push('Preferred Schedule');
            return missing;
        }

        function goToStep(step) {
            if (step > activeStep) {
                let missing = [];
                if (activeStep === 1) missing = validateStep1();
                if (activeStep === 2) missing = validateStep2();
                if (activeStep === 3) missing = validateStep3();

                if (missing.length > 0) {
                    bookingError.textContent = 'Please complete the following before continuing: ' + missing.join(', ') + '.';
                    bookingError.classList.remove('d-none');
                    bookingError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
            }
            showStep(step);
        }

        // ---------------------------------------------------------------
        // STEP 2: SERVICE SELECTION (server-rendered cards)
        // ---------------------------------------------------------------

        function toggleServiceCard(card) {
            const checkbox = card.querySelector('.vd-service-checkbox');
            checkbox.checked = !checkbox.checked;
            card.classList.toggle('selected', checkbox.checked);
        }

        function toggleServiceCategory(index) {
            document.getElementById('svcHead' + index).classList.toggle('collapsed');
            document.getElementById('svcGrid' + index).classList.toggle('d-none');
        }

        // ---------------------------------------------------------------
        // STEP 3: CLINIC SELECTION + SCHEDULE (existing logic, unchanged)
        // ---------------------------------------------------------------

        document.querySelectorAll('.vd-clinic-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.vd-clinic-card-inner').forEach(c => c.classList.remove('selected'));
            this.closest('.vd-clinic-card').querySelector('.vd-clinic-card-inner').classList.add('selected');
        });
        });

        const reviewModalEl  = document.getElementById('reviewModal');
        const reviewModal    = new bootstrap.Modal(reviewModalEl);
        const bookingForm    = document.getElementById('bookingForm');
        const bookingError   = document.getElementById('bookingFormError');

        function openReviewModal() {
            const missing = validateStep3();
            if (missing.length > 0) {
                bookingError.textContent = 'Please complete the following before booking: ' + missing.join(', ') + '.';
                bookingError.classList.remove('d-none');
                bookingError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            bookingError.classList.add('d-none');

            const clinicRadio = document.querySelector('input[name="clinic_id"]:checked');
            const clinicName  = clinicRadio
                ? clinicRadio.closest('.vd-clinic-card').querySelector('.vd-clinic-tag').textContent
                : '—';

            const serviceNames = Array.from(document.querySelectorAll('.vd-svc-card.selected .vd-svc-card-name'))
                .map(el => el.textContent.trim());

            const selectedSchedCard = document.querySelector('.vd-schedule-card.selected');
            const scheduleLabel = selectedSchedCard
                ? selectedSchedCard.querySelector('.vd-schedule-dayname').textContent + ' ' +
                  selectedSchedCard.querySelector('.vd-schedule-daynum').textContent + ' ' +
                  selectedSchedCard.querySelector('.vd-schedule-month').textContent
                : '—';

            document.getElementById('reviewSummary').innerHTML = `
                <div class="vd-summary-row"><span class="vd-summary-lbl">Patient</span><span class="vd-summary-val">${bookingForm.elements['firstname'].value} ${bookingForm.elements['lastname'].value}</span></div>
                <div class="vd-summary-row"><span class="vd-summary-lbl">Age / Gender</span><span class="vd-summary-val">${bookingForm.elements['age'].value} / ${bookingForm.elements['gender'].value}</span></div>
                <div class="vd-summary-row"><span class="vd-summary-lbl">Phone</span><span class="vd-summary-val">${bookingForm.elements['phone_number'].value}</span></div>
                <div class="vd-summary-row"><span class="vd-summary-lbl">Email</span><span class="vd-summary-val">${bookingForm.elements['email'].value || '—'}</span></div>
                <div class="vd-summary-row"><span class="vd-summary-lbl">Services</span><span class="vd-summary-val">${serviceNames.join(', ')}</span></div>
                <div class="vd-summary-row"><span class="vd-summary-lbl">Clinic</span><span class="vd-summary-val">${clinicName}</span></div>
                <div class="vd-summary-row"><span class="vd-summary-lbl">Schedule</span><span class="vd-summary-val">${scheduleLabel}</span></div>`;

            reviewModal.show();
        }

        function confirmReview(button) {
            document.activeElement.blur();
            reviewModal.hide();
            submitBooking(false, button);
        }

        async function submitBooking(redirect = false, button = null) {
        const formData = new FormData(document.getElementById('bookingForm'));
        LoadingUI.setButton(button, true, 'Booking…');

        try {
        const response = await fetch('../../apps/controllers/appointmentController.php', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();

        const result = JSON.parse(text);

        if (result.success) {
            const params = new URLSearchParams({
                appointment_id: result.appointment_id,
                token: result.payment_token
            });
            window.location.href = `payment.php?${params.toString()}`;
        } else {
            bookingError.textContent = result.message || 'Unable to submit your booking.';
            bookingError.classList.remove('d-none');
            bookingError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            LoadingUI.setButton(button, false);
        }
        } catch (error) {
            LoadingUI.setButton(button, false);
            bookingError.textContent = 'Unable to submit your booking. Please try again.';
            bookingError.classList.remove('d-none');
            bookingError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            console.error(error);
        }
        }

        const clinicRadios   = document.querySelectorAll('.vd-clinic-radio');
        const scheduleGrid   = document.getElementById('scheduleGrid');
        const scheduleInput  = document.getElementById('scheduleInput');
        const schedulePrompt = document.getElementById('schedulePrompt');
        const scheduleLoad   = document.getElementById('scheduleLoading');
        const scheduleEmpty  = document.getElementById('scheduleEmpty');

        function showScheduleState(state) {
        schedulePrompt.classList.add('d-none');
        scheduleLoad.classList.add('d-none');
        scheduleEmpty.classList.add('d-none');
        scheduleGrid.classList.add('d-none');
        if (state === 'prompt')  schedulePrompt.classList.remove('d-none');
        if (state === 'loading') scheduleLoad.classList.remove('d-none');
        if (state === 'empty')   scheduleEmpty.classList.remove('d-none');
        if (state === 'grid')    scheduleGrid.classList.remove('d-none');
        }

        function selectScheduleCard(card, scheduleId) {
        document.querySelectorAll('.vd-schedule-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        scheduleInput.value = scheduleId;
        }

        function renderScheduleCards(schedules) {
        scheduleGrid.innerHTML = '';
        scheduleInput.value = '';

        schedules.forEach(schedule => {
            const remaining = schedule.available_slots;
            const isFull    = remaining <= 0;
            const date      = new Date(schedule.sched_date);
            const day       = date.toLocaleDateString('en-PH', { weekday: 'short' });
            const dayNum    = date.getDate().toString().padStart(2, '0');
            const month     = date.toLocaleDateString('en-PH', { month: 'short' });
            const year      = date.getFullYear();

            const card = document.createElement('div');
            card.className = 'vd-schedule-card' + (isFull ? ' full' : '');
            card.innerHTML = `
            <div class="vd-schedule-date">
                <span class="vd-schedule-dayname">${day}</span>
                <span class="vd-schedule-daynum">${dayNum}</span>
                <span class="vd-schedule-month">${month} ${year}</span>
            </div>
            <div class="vd-schedule-slots ${isFull ? 'full' : remaining <= 3 ? 'low' : ''}">
                ${isFull ? 'Fully booked' : remaining + ' slot' + (remaining === 1 ? '' : 's') + ' left'}
            </div>`;

            if (!isFull) {
            card.addEventListener('click', () => selectScheduleCard(card, schedule.schedule_id));
            }

            scheduleGrid.appendChild(card);
        });

        showScheduleState('grid');
        }

        clinicRadios.forEach(radio => {
        radio.addEventListener('change', async function () {
            const clinicId = this.value;
            scheduleInput.value = '';
            showScheduleState('loading');

            try {
            const response  = await fetch(`../../apps/controllers/scheduleController.php?action=available&clinic_id=${clinicId}`);
            const schedules = await response.json();

            if (!schedules.length) {
                showScheduleState('empty');
            } else {
                renderScheduleCards(schedules);
            }
            } catch (error) {
            console.error('Error fetching schedules:', error);
            showScheduleState('empty');
            }
        });
        });

        const phoneInput = document.getElementById('phoneNumber');
        const birthdateInput = document.getElementById('birthdate');
        const ageInput = document.getElementById('age');

        function calculateAge() {
            if (!birthdateInput.value) {
                ageInput.value = '';
                return;
            }

            const [year, month, day] = birthdateInput.value.split('-').map(Number);
            const today = new Date();
            let age = today.getFullYear() - year;
            const monthDifference = (today.getMonth() + 1) - month;
            if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < day)) age--;
            ageInput.value = age >= 0 && age <= 120 ? age : '';
        }

        birthdateInput.addEventListener('change', calculateAge);

        if (phoneInput) {
            const allowedKeys = ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter',
                'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];

            phoneInput.addEventListener('keydown', function (e) {
                if (e.ctrlKey || e.metaKey) return;           // allow copy/paste/select-all
                if (allowedKeys.includes(e.key)) return;       // allow navigation keys
                if (!/^[0-9]$/.test(e.key)) {
                    e.preventDefault();                        // block anything that isn't a digit
                }
            });

            phoneInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 11); // cleanup fallback (e.g. pasted text)
            });
        }
    </script>
</body>
</html>
