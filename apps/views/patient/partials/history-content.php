<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/appointmentModel.php';
require_once __DIR__ . '/../../../models/reviewModel.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

$db   = new Database();
$conn = $db->connect();

$patientModel     = new Patient($conn);
$appointmentModel = new Appointment($conn);
$reviewModel      = new ReviewModel($conn);

$patient  = $patientModel->getPatientByUserId($_SESSION['user_id']);
$past     = $appointmentModel->getPatientPastAppointments($patient['patient_id']);
$servicesByAppointment = $appointmentModel->getServiceDetailsForAppointments(array_column($past, 'appointment_id'));
$reviewsByAppointment = $reviewModel->getForAppointments(array_column($past, 'appointment_id'));

function statusClass($s) {
    return 'vd-status vd-status-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s));
}

function patientHistoryPayload(array $appointment, array $services, ?array $review): string {
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
        'review' => $review ? [
            'rating' => (int) $review['rating'],
            'feedback' => $review['feedback'] ?? '',
            'createdAt' => $review['created_at'] ?? '',
        ] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}
?>

<div class="vd-patient-history-page d-flex flex-column gap-4">

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
        <div class="vd-dash-card-body vd-history-list">
        <?php if (empty($past)): ?>
            <div class="vd-empty-state">No past appointments.</div>
        <?php else: ?>
            <div class="vd-history-column-head" aria-hidden="true">
                <span class="vd-history-column-visit">Visit</span>
                <span>Status</span>
                <span>Review &amp; actions</span>
            </div>
            <?php foreach ($past as $appt): ?>
            <?php $review = $reviewsByAppointment[(int) $appt['appointment_id']] ?? null; ?>
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
            <div class="vd-history-status">
                <span class="<?= statusClass($appt['status']) ?>">
                    <?= htmlspecialchars($appt['status']) ?>
                </span>
            </div>
            <div class="vd-history-row-actions">
                <div class="vd-history-review-slot">
                    <?php if ($review): ?>
                        <div class="vd-review-submitted-state">
                            <div class="vd-review-submitted-rating" aria-label="Rated <?= (int) $review['rating'] ?> out of 5 stars">
                                <span class="vd-review-submitted-stars" aria-hidden="true">
                                    <?php for ($star = 1; $star <= 5; $star++): ?><span class="<?= $star <= (int) $review['rating'] ? 'is-filled' : '' ?>">★</span><?php endfor; ?>
                                </span>
                                <strong><?= (int) $review['rating'] ?>/5</strong>
                            </div>
                            <span><?= trim((string) ($review['feedback'] ?? '')) !== '' ? 'Feedback submitted' : 'Rating submitted' ?></span>
                        </div>
                    <?php elseif (($appt['status'] ?? '') === 'Completed'): ?>
                        <button type="button" class="btn vd-btn-gold btn-sm vd-rate-visit-btn"
                            data-rate-appointment="<?= (int) $appt['appointment_id'] ?>"
                            data-rate-date="<?= htmlspecialchars(date('F j, Y', strtotime($appt['date'])), ENT_QUOTES, 'UTF-8') ?>"
                            data-rate-clinic="<?= htmlspecialchars($appt['clinic_name'] ?? $appt['clinic'] ?? 'Dental clinic', ENT_QUOTES, 'UTF-8') ?>">
                            <i class="ti ti-star" aria-hidden="true"></i><span>Rate visit</span>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="vd-history-details-slot">
                    <button type="button" class="btn vd-btn-outline btn-sm vd-history-details-btn"
                        aria-label="View details for <?= htmlspecialchars($appt['service_name'] ?? 'appointment', ENT_QUOTES, 'UTF-8') ?> on <?= htmlspecialchars(date('F j, Y', strtotime($appt['date'])), ENT_QUOTES, 'UTF-8') ?>"
                        data-history-details="<?= patientHistoryPayload($appt, $servicesByAppointment[(int) $appt['appointment_id']] ?? [], $review) ?>">
                        <i class="ti ti-eye" aria-hidden="true"></i><span>View details</span>
                    </button>
                </div>
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
                <section class="vd-appointment-review-section d-none" id="patientHistoryReviewSection" aria-labelledby="patientHistoryReviewHeading">
                    <div class="vd-appointment-section-heading">
                        <h6 class="vd-appointment-details-section-title mb-0" id="patientHistoryReviewHeading">Your visit feedback</h6>
                        <span class="vd-review-date" id="patientHistoryReviewDate"></span>
                    </div>
                    <div class="vd-review-display-stars" id="patientHistoryReviewStars" aria-label=""></div>
                    <blockquote class="vd-review-display-feedback d-none" id="patientHistoryReviewFeedback"></blockquote>
                    <p class="vd-review-display-empty d-none" id="patientHistoryReviewEmpty">You submitted a star rating without written feedback.</p>
                </section>
            </div>
            <div class="modal-footer"><button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<div class="modal fade vd-rating-modal" id="visitRatingModal" tabindex="-1"
    aria-labelledby="visitRatingTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content">
            <div class="modal-header vd-rating-modal-header">
                <div>
                    <div class="vd-appointment-details-kicker">Completed visit</div>
                    <h5 class="modal-title vd-modal-title" id="visitRatingTitle">How was your experience?</h5>
                    <p class="vd-appointment-details-subtitle mb-0" id="visitRatingContext"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="visitRatingForm" data-endpoint="../../../apps/controllers/reviewController.php">
                <div class="modal-body vd-rating-modal-body">
                    <input type="hidden" name="action" value="submit">
                    <input type="hidden" name="appointment_id" id="visitRatingAppointmentId">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <fieldset class="vd-rating-fieldset">
                        <legend>Overall visit rating <span aria-hidden="true">*</span></legend>
                        <div class="vd-rating-picker" id="visitRatingPicker">
                            <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                                <input class="visually-hidden" type="radio" name="rating" id="visitRating<?= $rating ?>" value="<?= $rating ?>" required>
                                <label for="visitRating<?= $rating ?>" data-rating="<?= $rating ?>" aria-label="<?= $rating ?> <?= $rating === 1 ? 'star' : 'stars' ?>">
                                    <span aria-hidden="true">☆</span>
                                </label>
                            <?php endfor; ?>
                        </div>
                        <div class="vd-rating-meaning" id="visitRatingMeaning" aria-live="polite">Select the number of stars that best reflects your visit.</div>
                    </fieldset>

                    <div class="vd-rating-feedback-field">
                        <div class="vd-rating-label-row">
                            <label class="vd-label form-label mb-0" for="visitFeedback">Tell us about your experience <span>Optional</span></label>
                            <span id="visitFeedbackCount">0 / 1,000</span>
                        </div>
                        <textarea class="form-control vd-input" id="visitFeedback" name="feedback" maxlength="1000" rows="4"
                            placeholder="What went well, or what could make your next visit better?"></textarea>
                        <small>Your feedback is visible to the clinic administrator and helps improve patient care.</small>
                    </div>

                    <div class="vd-review-form-alert d-none" id="visitRatingAlert" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Maybe later</button>
                    <button type="submit" class="btn vd-btn-gold" id="visitRatingSubmit" disabled>
                        <i class="ti ti-send" aria-hidden="true"></i><span>Submit feedback</span>
                    </button>
                </div>
            </form>
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

        const reviewSection = document.getElementById('patientHistoryReviewSection');
        reviewSection.classList.toggle('d-none', !appointment.review);
        if (appointment.review) {
            const review = appointment.review;
            const stars = document.getElementById('patientHistoryReviewStars');
            stars.replaceChildren();
            stars.setAttribute('aria-label', `${review.rating} out of 5 stars`);
            for (let index = 1; index <= 5; index += 1) {
                const star = document.createElement('span');
                star.textContent = index <= review.rating ? '★' : '☆';
                star.setAttribute('aria-hidden', 'true');
                stars.appendChild(star);
            }
            const feedback = document.getElementById('patientHistoryReviewFeedback');
            feedback.textContent = review.feedback || '';
            feedback.classList.toggle('d-none', !review.feedback);
            document.getElementById('patientHistoryReviewEmpty').classList.toggle('d-none', Boolean(review.feedback));
            document.getElementById('patientHistoryReviewDate').textContent = `Submitted ${formatDateTime(review.createdAt)}`;
        }

        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }));

    const ratingModalElement = document.getElementById('visitRatingModal');
    const ratingForm = document.getElementById('visitRatingForm');
    if (!ratingModalElement || !ratingForm) return;

    const ratingModal = bootstrap.Modal.getOrCreateInstance(ratingModalElement);
    const ratingInputs = Array.from(ratingForm.querySelectorAll('input[name="rating"]'));
    const ratingLabels = Array.from(ratingForm.querySelectorAll('[data-rating]'));
    const ratingMeaning = document.getElementById('visitRatingMeaning');
    const feedbackInput = document.getElementById('visitFeedback');
    const feedbackCount = document.getElementById('visitFeedbackCount');
    const submitButton = document.getElementById('visitRatingSubmit');
    const alertElement = document.getElementById('visitRatingAlert');
    const ratingMeanings = {
        1: 'Very dissatisfied',
        2: 'Dissatisfied',
        3: 'Satisfied',
        4: 'Very satisfied',
        5: 'Excellent'
    };

    const paintRating = value => {
        ratingLabels.forEach(label => {
            const active = Number(label.dataset.rating) <= value;
            label.classList.toggle('is-selected', active);
            label.querySelector('span').textContent = active ? '★' : '☆';
        });
        ratingMeaning.textContent = value
            ? `${value} out of 5 — ${ratingMeanings[value]}`
            : 'Select the number of stars that best reflects your visit.';
        submitButton.disabled = !value;
    };

    const showFormAlert = message => {
        alertElement.textContent = message;
        alertElement.classList.toggle('d-none', !message);
    };

    document.querySelectorAll('[data-rate-appointment]').forEach(button => button.addEventListener('click', () => {
        ratingForm.reset();
        paintRating(0);
        showFormAlert('');
        feedbackCount.textContent = '0 / 1,000';
        document.getElementById('visitRatingAppointmentId').value = button.dataset.rateAppointment;
        document.getElementById('visitRatingContext').textContent = `${button.dataset.rateClinic} · ${button.dataset.rateDate}`;
        ratingModal.show();
    }));

    ratingInputs.forEach(input => input.addEventListener('change', () => paintRating(Number(input.value))));
    feedbackInput.addEventListener('input', () => {
        feedbackCount.textContent = `${feedbackInput.value.length.toLocaleString()} / 1,000`;
    });

    ratingForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!ratingForm.reportValidity()) return;

        showFormAlert('');
        submitButton.disabled = true;
        submitButton.classList.add('is-loading');
        submitButton.querySelector('span').textContent = 'Submitting…';

        try {
            const response = await fetch(ratingForm.dataset.endpoint, {
                method: 'POST',
                body: new FormData(ratingForm),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to submit your feedback.');
            }

            ratingModalElement.addEventListener('hidden.bs.modal', () => {
                document.querySelector('.vd-nav-item[data-page="history-content.php"]')?.click();
            }, { once: true });
            ratingModal.hide();
            window.showToast?.(result.message || 'Your feedback has been submitted.', true);
        } catch (error) {
            showFormAlert(error.message || 'Unable to submit your feedback. Please try again.');
            submitButton.disabled = !ratingForm.querySelector('input[name="rating"]:checked');
        } finally {
            submitButton.classList.remove('is-loading');
            submitButton.querySelector('span').textContent = 'Submit feedback';
        }
    });
})();
</script>
