<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    return;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/siteSettingsModel.php';
require_once __DIR__ . '/../../../models/clinicModel.php';
require_once __DIR__ . '/../../../models/scheduleModel.php';

$db   = new Database();
$conn = $db->connect();
$settingsModel = new SiteSettingsModel($conn);

$settings = $settingsModel->getSettings();
$clinics = (new Clinic($conn))->getAllClinics();
$isAdmin = ($_SESSION['user_role'] ?? '') === 'Admin';
$transitionMinutes = max(
    Schedule::MIN_TRANSITION_MINUTES,
    min(Schedule::MAX_TRANSITION_MINUTES, (int) ($settings['clinic_transition_minutes'] ?? 90))
);
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

function sv($settings, $key)
{
    return htmlspecialchars($settings[$key] ?? '');
}
?>

<div class="d-flex flex-column gap-4">

    <?php if ($isAdmin): ?>
    <div class="vd-empty-state" style="background: var(--gold-pale); border: 1px solid var(--border); color: var(--mid); text-align: left; padding: 14px 18px;">
        <i class="ti ti-info-circle me-1"></i>
        Changes here update clinic operations and public-facing content. Each section saves independently.
    </div>
    <?php endif; ?>

    <?php if (!$isAdmin): ?>
    <div class="vd-dash-card vd-schedule-defaults-card">
        <button class="vd-schedule-defaults-toggle collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#clinicScheduleDefaultsBody" aria-expanded="false" aria-controls="clinicScheduleDefaultsBody">
            <span class="vd-schedule-defaults-heading">
                <span class="vd-dash-card-title">Clinic Schedule Defaults</span>
                <span class="vd-schedule-defaults-summary" id="clinicDefaultsSummary">
                    <?php foreach ($clinics as $index => $clinic): ?><?= $index ? ' · ' : '' ?><?= htmlspecialchars($clinic['clinic_name']) ?>: <?= htmlspecialchars(Schedule::formatTimeRange($clinic['default_start_time'] ?? '08:00:00', $clinic['default_end_time'] ?? '17:00:00')) ?><?php endforeach; ?>
                    · <span id="clinicTransitionSummary"><?= $transitionMinutes > 0 ? $transitionMinutes . '-minute separation' : 'No buffer (overlap blocked)' ?></span>
                </span>
            </span>
            <span class="vd-schedule-defaults-action"><span>Edit defaults</span><i class="ti ti-chevron-down" aria-hidden="true"></i></span>
        </button>
        <div class="collapse" id="clinicScheduleDefaultsBody">
        <div class="vd-dash-card-body vd-schedule-defaults-body">
            <p class="vd-schedule-defaults-intro mb-3">These values prefill new schedules. Individual dates can still be adjusted in five-minute increments, while existing schedules keep their saved windows.</p>
            <div class="vd-clinic-hours-list">
                <?php foreach ($clinics as $clinic): ?>
                <div class="vd-clinic-hours-row" data-clinic-hours-row data-clinic-id="<?= (int) $clinic['clinic_id'] ?>">
                    <div class="vd-clinic-hours-name">
                        <i class="ti ti-building-hospital" aria-hidden="true"></i>
                        <span><strong><?= htmlspecialchars($clinic['clinic_name']) ?></strong><small>Default availability window</small></span>
                    </div>
                    <div>
                        <label class="vd-label form-label" for="clinicStart<?= (int) $clinic['clinic_id'] ?>">Opens</label>
                        <clock-timepicker class="vd-clock-timepicker" format="HH:mm" precision="00:05" minimum="08:00" maximum="17:25" required vibrate="false" data-default-start-picker>
                            <input type="text" id="clinicStart<?= (int) $clinic['clinic_id'] ?>" class="form-control vd-input vd-schedule-time-input" data-default-start value="<?= htmlspecialchars(substr($clinic['default_start_time'] ?? '08:00:00', 0, 5)) ?>" autocomplete="off" inputmode="numeric" required>
                        </clock-timepicker>
                    </div>
                    <div>
                        <label class="vd-label form-label" for="clinicEnd<?= (int) $clinic['clinic_id'] ?>">Closes</label>
                        <clock-timepicker class="vd-clock-timepicker" format="HH:mm" precision="00:05" minimum="08:05" maximum="17:30" required vibrate="false" data-default-end-picker>
                            <input type="text" id="clinicEnd<?= (int) $clinic['clinic_id'] ?>" class="form-control vd-input vd-schedule-time-input" data-default-end value="<?= htmlspecialchars(substr($clinic['default_end_time'] ?? '17:00:00', 0, 5)) ?>" autocomplete="off" inputmode="numeric" required>
                        </clock-timepicker>
                    </div>
                    <button type="button" class="btn vd-btn-gold vd-save-clinic-hours"><i class="ti ti-check" aria-hidden="true"></i><span>Save</span></button>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="vd-schedule-policy-editor">
                <div class="vd-schedule-policy-copy">
                    <i class="ti ti-route" aria-hidden="true"></i>
                    <span><strong>Clinic time separation</strong><small>Minimum buffer between different clinics operating on the same date. Set 0 to allow adjacent, non-overlapping windows.</small></span>
                </div>
                <div class="vd-schedule-policy-control">
                    <label class="vd-label form-label" for="clinicTransitionMinutes">Minutes</label>
                    <div class="input-group">
                        <input type="number" class="form-control vd-input" id="clinicTransitionMinutes" min="0" max="240" step="5" value="<?= $transitionMinutes ?>" inputmode="numeric" required>
                        <span class="input-group-text">min</span>
                    </div>
                </div>
                <button type="button" class="btn vd-btn-gold" id="saveClinicTransition"><i class="ti ti-check" aria-hidden="true"></i><span>Save policy</span></button>
            </div>
            <div class="vd-schedule-policy-note mt-3"><i class="ti ti-info-circle" aria-hidden="true"></i><span>Clinic hours are limited to 8:00 AM–5:30 PM. A larger separation can only be saved when all upcoming clinic windows already meet it.</span></div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>

    <!-- ── BRAND & LOGO ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Brand & Logo</span>
        </div>
        <div class="vd-dash-card-body">
            <p class="vd-appt-meta mb-3">
                The stylized "VEN✚URA" wordmark is used as a fallback. Uploading a logo image replaces it across
                the homepage, dashboards, account pages, reports, and supported email clients.
            </p>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="vd-label form-label">Small text above wordmark</label>
                    <input type="text" class="form-control vd-input vd-field" data-field="brand_name_top"
                        maxlength="50" value="<?= sv($settings, 'brand_name_top') ?>">
                </div>
                <div class="col-md-6">
                    <label class="vd-label form-label">Small text below wordmark</label>
                    <input type="text" class="form-control vd-input vd-field" data-field="brand_name_sub"
                        maxlength="50" value="<?= sv($settings, 'brand_name_sub') ?>">
                </div>
            </div>
            <div class="d-flex justify-content-end mb-4">
                <button class="btn vd-btn-gold btn-sm vd-save-group-btn" data-group="brand">Save Brand Text</button>
            </div>

            <hr style="border-color: var(--border);">

            <div class="mt-3">
                <label class="vd-label form-label">Site Logo (optional)</label>
                <?php if (!empty($settings['site_logo'])): ?>
                    <div class="mb-2">
                        <img src="../../../public/assets/<?= htmlspecialchars($settings['site_logo']) ?>"
                            alt="Current logo" style="height:32px; border-radius:6px; border:1px solid var(--border);">
                        <span class="vd-appt-meta ms-2">Currently in use</span>
                    </div>
                <?php else: ?>
                    <div class="vd-appt-meta mb-2">No logo uploaded yet — showing the text wordmark.</div>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="file" id="logoInput" class="form-control form-control-sm" style="max-width: 280px;" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                    <button class="btn vd-btn-outline btn-sm" id="uploadLogoBtn">Upload Logo</button>
                    <?php if (!empty($settings['site_logo'])): ?>
                        <button class="btn vd-btn-outline btn-sm text-danger" id="removeLogoBtn">Remove Logo</button>
                    <?php endif; ?>
                </div>
                <div class="vd-appt-meta mt-2">PNG or JPG is recommended for the widest email-client compatibility. WEBP and SVG use the text fallback in email.</div>
            </div>
        </div>
    </div>

    <!-- ── HERO SECTION ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Hero Section</span>
        </div>
        <div class="vd-dash-card-body">
            <p class="vd-appt-meta mb-3">The clinic name is shown automatically above the hero message to keep the homepage clearly branded.</p>
            <div class="mb-3">
                <label class="vd-label form-label">Eyebrow <span class="vd-appt-meta">(small gold caps line)</span></label>
                <input type="text" class="form-control vd-input vd-field" data-field="hero_eyebrow"
                    maxlength="150" value="<?= sv($settings, 'hero_eyebrow') ?>">
            </div>
            <div class="mb-3">
                <label class="vd-label form-label">Headline</label>
                <input type="text" class="form-control vd-input vd-field" data-field="hero_title"
                    maxlength="255" value="<?= sv($settings, 'hero_title') ?>">
            </div>
            <div class="mb-3">
                <label class="vd-label form-label">Subtext</label>
                <textarea class="form-control vd-input vd-field" data-field="hero_subtext" rows="2" maxlength="500"><?= sv($settings, 'hero_subtext') ?></textarea>
            </div>
            <div class="d-flex justify-content-end">
                <button class="btn vd-btn-gold btn-sm vd-save-group-btn" data-group="hero">Save Hero Section</button>
            </div>
        </div>
    </div>

    <!-- ── ABOUT SECTION ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">About Section</span>
        </div>
        <div class="vd-dash-card-body">
            <div class="mb-4">
                <label class="vd-label form-label">Intro Paragraph</label>
                <textarea class="form-control vd-input vd-field" data-field="about_intro" rows="4"><?= sv($settings, 'about_intro') ?></textarea>
            </div>

            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="row g-3 mb-3 align-items-start">
                    <div class="col-md-4">
                        <label class="vd-label form-label">Pillar <?= $i ?> Title</label>
                        <input type="text" class="form-control vd-input vd-field" data-field="pillar<?= $i ?>_title"
                            maxlength="100" value="<?= sv($settings, "pillar{$i}_title") ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="vd-label form-label">Pillar <?= $i ?> Description</label>
                        <input type="text" class="form-control vd-input vd-field" data-field="pillar<?= $i ?>_desc"
                            maxlength="255" value="<?= sv($settings, "pillar{$i}_desc") ?>">
                    </div>
                </div>
            <?php endfor; ?>

            <div class="d-flex justify-content-end">
                <button class="btn vd-btn-gold btn-sm vd-save-group-btn" data-group="about">Save About Section</button>
            </div>
        </div>
    </div>

    <!-- ── CONTACT INFO ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Contact Information</span>
        </div>
        <div class="vd-dash-card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="vd-label form-label">Address</label>
                    <input type="text" class="form-control vd-input vd-field" data-field="contact_address"
                        maxlength="255" value="<?= sv($settings, 'contact_address') ?>">
                </div>
                <div class="col-md-4">
                    <label class="vd-label form-label">Phone Numbers</label>
                    <textarea class="form-control vd-input vd-field" data-field="contact_phone" rows="3"
                        placeholder="One phone number per line"><?= sv($settings, 'contact_phone') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="vd-label form-label">Email</label>
                    <input type="email" class="form-control vd-input vd-field" data-field="contact_email"
                        maxlength="100" value="<?= sv($settings, 'contact_email') ?>">
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button class="btn vd-btn-gold btn-sm vd-save-group-btn" data-group="contact">Save Contact Info</button>
            </div>
        </div>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header"><span class="vd-dash-card-title">GCash Deposit Settings</span></div>
        <div class="vd-dash-card-body">
            <div class="alert alert-info small">These values apply when a new deposit deadline is created, including payment resubmissions and extensions. Existing recorded amounts and deadlines are not changed.</div>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><label class="vd-label form-label">Deposit Amount (₱)</label><input type="number" class="form-control vd-input vd-field" data-field="deposit_amount" value="<?= sv($settings, 'deposit_amount') ?>" min="0.01" max="99999999.99" step="0.01" required></div>
                <div class="col-md-3"><label class="vd-label form-label">Deadline (minutes)</label><input type="number" class="form-control vd-input vd-field" data-field="payment_deadline_minutes" value="<?= sv($settings, 'payment_deadline_minutes') ?>" min="1" max="65535" step="1" required></div>
                <div class="col-md-3"><label class="vd-label form-label">GCash Account Name</label><input class="form-control vd-input vd-field" data-field="gcash_account_name" value="<?= sv($settings, 'gcash_account_name') ?>" maxlength="100"></div>
                <div class="col-md-3"><label class="vd-label form-label">GCash Number</label><input class="form-control vd-input vd-field" data-field="gcash_account_number" value="<?= sv($settings, 'gcash_account_number') ?>" maxlength="30"></div>
            </div>
            <div class="d-flex justify-content-end mb-4"><button class="btn vd-btn-gold btn-sm vd-save-group-btn" data-group="payment">Save Deposit Settings</button></div>
            <hr style="border-color: var(--border);">
            <label class="vd-label form-label mt-2">GCash QR Code</label>
            <?php if (!empty($settings['gcash_qr_path'])): ?><div class="mb-3"><img src="../../../public/assets/<?= htmlspecialchars($settings['gcash_qr_path']) ?>" alt="Current GCash QR" style="max-height:180px" class="img-thumbnail"></div><?php endif; ?>
            <div class="d-flex flex-wrap gap-2 align-items-center"><input type="file" id="gcashQrInput" class="form-control form-control-sm" style="max-width:280px" accept="image/jpeg,image/png"><button type="button" class="btn vd-btn-outline btn-sm" id="uploadGcashQrBtn">Upload QR Code</button></div>
        </div>
    </div>

    <?php endif; ?>

</div>


<!-- Save confirmation modal -->
<div class="modal fade" id="settingsConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content vd-confirm-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title vd-modal-title">Confirm Save</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="settingsConfirmMessage">Are you sure you want to save these changes?</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="vd-btn-outline btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="vd-btn-gold btn" id="settingsConfirmBtn">Confirm &amp; Save</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const CONTROLLER = '../../../apps/controllers/siteSettingsController.php';
        const settingsCsrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
        const clinicTimePickers = new WeakMap();

        const groupLabels = {
            brand: 'Brand Text',
            hero: 'Hero Section',
            about: 'About Section',
            contact: 'Contact Information',
            payment: 'GCash Deposit Settings',
        };

        function showToast(msg, success) {
            if (typeof window.showToast === 'function') {
                window.showToast(msg, success);
                return;
            }
            console.warn('showToast not available:', msg);
        }

        function refreshPage() {
            window.location.reload();
        }

        function pickerTime(picker, fallbackInput) {
            return String(picker?.value || fallbackInput.value || '').trim();
        }

        const scheduleDefaultsBody = document.getElementById('clinicScheduleDefaultsBody');

        function expandScheduleDefaults() {
            if (!scheduleDefaultsBody) return;
            bootstrap.Collapse.getOrCreateInstance(scheduleDefaultsBody, { toggle: false }).show();
        }

        try {
            if (scheduleDefaultsBody && sessionStorage.getItem('vdScheduleDefaultsOpen') === '1') {
                sessionStorage.removeItem('vdScheduleDefaultsOpen');
                expandScheduleDefaults();
            }
        } catch (error) {
            // Storage can be unavailable in hardened browser modes; the panel
            // still works normally through Bootstrap's collapse control.
        }

        function keepScheduleDefaultsOpenAfterRefresh() {
            try {
                sessionStorage.setItem('vdScheduleDefaultsOpen', '1');
            } catch (error) {}
        }

        document.querySelectorAll('[data-clinic-hours-row]').forEach(row => {
            const pickers = {
                start: row.querySelector('[data-default-start-picker]'),
                end: row.querySelector('[data-default-end-picker]'),
            };
            clinicTimePickers.set(row, pickers);
        });

        const confirmModalEl = document.getElementById('settingsConfirmModal');
        const confirmMessageEl = document.getElementById('settingsConfirmMessage');
        const confirmBtn = document.getElementById('settingsConfirmBtn');
        const confirmModal = new bootstrap.Modal(confirmModalEl);
        let pendingConfirmAction = null;

        function askForSaveConfirmation(message, onConfirm) {
            pendingConfirmAction = onConfirm;
            confirmMessageEl.textContent = message;
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirm & Save';
            confirmModal.show();
        }

        confirmBtn.addEventListener('click', async function() {
            if (!pendingConfirmAction) return;

            this.disabled = true;
            this.textContent = 'Saving…';

            LoadingUI.setButton(this, true, 'Saving…');
            try {
                const shouldRefresh = await pendingConfirmAction();
                if (shouldRefresh) {
                    confirmModalEl.addEventListener('hidden.bs.modal', refreshPage, {
                        once: true
                    });
                }
                confirmModal.hide();
            } catch (err) {
                console.error(err);
                this.disabled = false;
                this.textContent = 'Confirm & Save';
            } finally {
                LoadingUI.setButton(this, false);
                this.disabled = false;
                this.textContent = 'Confirm & Save';
                pendingConfirmAction = null;
            }
        });

        document.querySelectorAll('.vd-save-clinic-hours').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('[data-clinic-hours-row]');
                const startInput = row.querySelector('[data-default-start]');
                const endInput = row.querySelector('[data-default-end]');
                const pickers = clinicTimePickers.get(row);
                const startTime = pickerTime(pickers?.start, startInput);
                const endTime = pickerTime(pickers?.end, endInput);
                const usesFiveMinuteSteps = [startTime, endTime].every(value => /^\d{2}:\d{2}$/.test(value) && Number(value.slice(3, 5)) % 5 === 0);
                if (!startTime || !endTime || startTime >= endTime) {
                    showToast('Default closing time must be later than opening time.', false);
                    return;
                }
                if (!usesFiveMinuteSteps) {
                    expandScheduleDefaults();
                    showToast('Default clinic hours must use five-minute increments.', false);
                    return;
                }
                if (startTime < '08:00' || endTime > '17:30') {
                    expandScheduleDefaults();
                    showToast('Default clinic hours must stay between 8:00 AM and 5:30 PM.', false);
                    return;
                }
                const clinicName = row.querySelector('.vd-clinic-hours-name strong').textContent.trim();
                const saveButton = this;
                askForSaveConfirmation(`Save the default schedule hours for ${clinicName}?`, async function() {
                    const formData = new FormData();
                    formData.append('action', 'updateClinicHours');
                    formData.append('csrf_token', settingsCsrfToken);
                    formData.append('clinic_id', row.dataset.clinicId);
                    formData.append('default_start_time', startTime);
                    formData.append('default_end_time', endTime);
                    LoadingUI.setButton(saveButton, true, 'Saving…');
                    try {
                        const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                        const result = await response.json();
                        showToast(result.message || 'Unable to save clinic hours.', result.success);
                        if (!result.success) {
                            expandScheduleDefaults();
                            return false;
                        }
                        keepScheduleDefaultsOpenAfterRefresh();
                        return true;
                    } catch (error) {
                        expandScheduleDefaults();
                        showToast('Network error. Please try again.', false);
                        return false;
                    } finally {
                        LoadingUI.setButton(saveButton, false);
                    }
                });
            });
        });

        const transitionInput = document.getElementById('clinicTransitionMinutes');
        const saveTransitionButton = document.getElementById('saveClinicTransition');
        if (transitionInput && saveTransitionButton) {
            saveTransitionButton.addEventListener('click', function() {
                expandScheduleDefaults();
                const rawMinutes = transitionInput.value.trim();
                const minutes = Number(rawMinutes);
                if (!/^\d+$/.test(rawMinutes) || minutes < 0 || minutes > 240 || minutes % 5 !== 0) {
                    transitionInput.setCustomValidity('Use a value from 0 to 240 in five-minute increments.');
                    transitionInput.reportValidity();
                    transitionInput.setCustomValidity('');
                    return;
                }

                const saveButton = this;
                const confirmationMessage = minutes > 0
                    ? `Use a ${minutes}-minute separation between clinic schedule windows?`
                    : 'Allow adjacent clinic schedule windows while continuing to block overlaps?';
                askForSaveConfirmation(
                    confirmationMessage,
                    async function() {
                        const formData = new FormData();
                        formData.append('action', 'updateClinicTransitionMinutes');
                        formData.append('csrf_token', settingsCsrfToken);
                        formData.append('clinic_transition_minutes', String(minutes));
                        LoadingUI.setButton(saveButton, true, 'Saving…');
                        try {
                            const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                            const result = await response.json();
                            showToast(result.message || 'Unable to save the clinic separation.', result.success);
                            if (!result.success) {
                                expandScheduleDefaults();
                                return false;
                            }
                            keepScheduleDefaultsOpenAfterRefresh();
                            return true;
                        } catch (error) {
                            expandScheduleDefaults();
                            showToast('Network error. Please try again.', false);
                            return false;
                        } finally {
                            LoadingUI.setButton(saveButton, false);
                        }
                    }
                );
            });
        }

        // ── Save a text/textarea group ──
        document.querySelectorAll('.vd-save-group-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const group = this.dataset.group;
                const label = groupLabels[group] || group;
                const saveButton = this;
                const card = saveButton.closest('.vd-dash-card');
                const invalidField = Array.from(card.querySelectorAll('.vd-field')).find(field => !field.checkValidity());
                if (invalidField) {
                    invalidField.reportValidity();
                    return;
                }

                askForSaveConfirmation(
                    `Save changes to the "${label}" section? The update will take effect across applicable parts of the system.`,
                    async function() {
                        const fields = card.querySelectorAll('.vd-field');

                        const formData = new FormData();
                        formData.append('action', 'updateGroup');
                        formData.append('csrf_token', settingsCsrfToken);
                        formData.append('group', group);
                        fields.forEach(field => {
                            formData.append(field.dataset.field, field.value.trim());
                        });

                        const originalText = saveButton.textContent;
                        saveButton.disabled = true;
                        saveButton.textContent = 'Saving…';

                        LoadingUI.setButton(saveButton, true, 'Saving…');
                        try {
                            const response = await fetch(CONTROLLER, {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            showToast(result.message || (result.success ? 'Saved.' : 'Failed to save.'), result.success);
                        } catch (err) {
                            showToast('Network error. Please try again.', false);
                            console.error(err);
                        } finally {
                            LoadingUI.setButton(saveButton, false);
                            saveButton.disabled = false;
                            saveButton.textContent = originalText;
                        }
                    }
                );
            });
        });

        // ── Upload logo ──
        const uploadLogoBtn = document.getElementById('uploadLogoBtn');
        const logoInput = document.getElementById('logoInput');

        if (uploadLogoBtn) {
            uploadLogoBtn.addEventListener('click', async function() {
                if (!logoInput.files[0]) {
                    showToast('Please choose an image first.', false);
                    return;
                }

                const uploadButton = this;
                askForSaveConfirmation(
                    'Upload this logo and replace the current wordmark across the system?',
                    async function() {
                        const formData = new FormData();
                        formData.append('action', 'updateLogo');
                        formData.append('csrf_token', settingsCsrfToken);
                        formData.append('logo', logoInput.files[0]);

                        const originalText = uploadButton.textContent;
                        uploadButton.disabled = true;
                        uploadButton.textContent = 'Uploading…';

                        LoadingUI.setButton(uploadButton, true, 'Uploading…');
                        try {
                            const response = await fetch(CONTROLLER, {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            showToast(result.message || (result.success ? 'Logo updated.' : 'Failed to upload.'), result.success);
                            return result.success;
                        } catch (err) {
                            showToast('Network error. Please try again.', false);
                            console.error(err);
                        } finally {
                            LoadingUI.setButton(uploadButton, false);
                            uploadButton.disabled = false;
                            uploadButton.textContent = originalText;
                        }
                    }
                );
            });
        }
        // ── Remove logo ──
        const removeLogoBtn = document.getElementById('removeLogoBtn');
        if (removeLogoBtn) {
            removeLogoBtn.addEventListener('click', function() {
                const btn = this;
                askForSaveConfirmation('Remove the current logo and revert to the text wordmark?', async function() {
                    const formData = new FormData();
                    formData.append('action', 'removeLogo');
                    formData.append('csrf_token', settingsCsrfToken);

                    const originalText = btn.textContent;
                    btn.disabled = true;
                    btn.textContent = 'Removing…';

                    LoadingUI.setButton(btn, true, 'Removing…');
                    try {
                        const response = await fetch(CONTROLLER, {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        showToast(result.message || (result.success ? 'Logo removed.' : 'Failed to remove.'), result.success);
                        return result.success;
                    } catch (err) {
                        showToast('Network error. Please try again.', false);
                        console.error(err);
                    } finally {
                        LoadingUI.setButton(btn, false);
                        btn.disabled = false;
                        btn.textContent = originalText;
                    }
                });
            });
        }

        const uploadGcashQrBtn = document.getElementById('uploadGcashQrBtn');
        const gcashQrInput = document.getElementById('gcashQrInput');
        uploadGcashQrBtn?.addEventListener('click', function() {
            if (!gcashQrInput.files[0]) {
                showToast('Choose a QR image first.', false);
                return;
            }
            const button = this;
            askForSaveConfirmation('Upload this GCash QR code for patient deposit payments?', async function() {
                const formData = new FormData();
                formData.append('action', 'updateGcashQr');
                formData.append('csrf_token', settingsCsrfToken);
                formData.append('gcash_qr', gcashQrInput.files[0]);
                LoadingUI.setButton(button, true, 'Uploading…');
                try {
                    const response = await fetch(CONTROLLER, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    showToast(result.message, result.success);
                    return result.success;
                } catch (error) {
                    showToast('Unable to upload the QR code.', false);
                } finally {
                    LoadingUI.setButton(button, false);
                }
            });
        });
    })();
</script>
