<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

$db   = new Database();
$conn = $db->connect();
$patientModel = new Patient($conn);

$patient = $patientModel->getPatientFull(
    $patientModel->getPatientByUserId($_SESSION['user_id'])['patient_id'] ?? 0
);

if (!$patient) {
    echo '<div class="vd-empty-state">Profile not found.</div>';
    exit;
}

$conditions = $patient['patient_conditions']
    ? explode(', ', $patient['patient_conditions'])
    : [];

$conditionGroups = require __DIR__ . '/../../../../config/medicalConditions.php';
$allConditions = array_merge(...array_values($conditionGroups));
$csrfToken = get_csrf_token();
?>

<section class="vd-profile-review-note" aria-labelledby="profileReviewTitle">
    <span class="vd-profile-review-icon" aria-hidden="true"><i class="ti ti-clipboard-check"></i></span>
    <div class="vd-profile-review-copy">
        <span class="vd-profile-review-eyebrow">Editable patient profile</span>
        <h2 id="profileReviewTitle">Keep your information current</h2>
        <p>Save your changes before your visit. Clinic staff will still review and confirm the information with you during check-in.</p>
    </div>
    <span class="vd-profile-review-badge"><i class="ti ti-clock-check" aria-hidden="true"></i> Staff review required</span>
</section>

<div id="patientProfileSaveAlert" class="alert d-none" role="status" aria-live="polite"></div>

<div class="d-flex flex-column gap-4 vd-profile-sections">

    <!-- ── PERSONAL INFORMATION ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Personal Information</span>
        </div>
        <div class="vd-profile-body">
        <form id="personalForm" class="vd-patient-profile-form" aria-label="Personal information">
            <div class="vd-profile-grid">
            <div class="vd-profile-field">
                <label class="vd-profile-label">First Name</label>
                <input type="text" name="firstname" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['firstname'] ?? '') ?>" required>
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Middle Name</label>
                <input type="text" name="middlename" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['middlename'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Last Name</label>
                <input type="text" name="lastname" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['lastname'] ?? '') ?>" required>
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Birthdate</label>
                <input type="date" name="birthdate" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['birthdate'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Age</label>
                <input type="number" name="age" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['age'] ?? '') ?>" min="0" max="120" readonly>
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Gender</label>
                <select name="gender" class="form-select vd-input">
                <option value="" disabled <?= empty($patient['gender']) ? 'selected' : '' ?>>— Select —</option>
                <?php foreach (['Male','Female','Prefer not to say'] as $g): ?>
                    <option value="<?= $g ?>" <?= ($patient['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Civil Status</label>
                <select name="civil_status" class="form-select vd-input">
                <option value="" disabled <?= empty($patient['civil_status']) ? 'selected' : '' ?>>— Select —</option>
                <?php foreach (['Single','Married','Widowed','Separated'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($patient['civil_status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Phone Number</label>
                <input type="tel" name="phone_number" class="form-control vd-input" id="phoneNumber" inputmode="numeric" 
                maxlength="11" value="<?= htmlspecialchars($patient['phone_number'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Email Address</label>
                <input type="email" name="email" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
            </div>
            <div class="vd-profile-field vd-profile-field-full">
                <label class="vd-profile-label">Home Address</label>
                <input type="text" name="home_address" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['home_address'] ?? '') ?>">
            </div>
            <div class="vd-profile-field vd-profile-field-full">
                <label class="vd-profile-label">Work Address</label>
                <input type="text" name="work_address" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['work_address'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Occupation</label>
                <input type="text" name="occupation" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['occupation'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Office Contact</label>
                <input type="tel" name="office_contact" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['office_contact'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">FB Account</label>
                <input type="text" name="fb_account" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['fb_account'] ?? '') ?>">
            </div>
            </div>
        </form>
        </div>
    </div>

    <!-- ── FOR MINORS ── -->
    <div class="vd-dash-card" id="minorsCard">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Guardian / Physician</span>
        </div>
        <div class="vd-profile-body">
        <form id="minorsForm" class="vd-patient-profile-form" aria-label="Guardian and physician information">
            <div class="vd-profile-grid">
            <div class="vd-profile-field">
                <label class="vd-profile-label">Guardian Name</label>
                <input type="text" name="guardian_name" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['guardian_name'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Guardian Contact</label>
                <input type="tel" name="guardian_contact" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['guardian_contact'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Physician Name</label>
                <input type="text" name="physician_name" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['physician_name'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Physician Contact</label>
                <input type="tel" name="physician_contact" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['physician_contact'] ?? '') ?>">
            </div>
            <div class="vd-profile-field vd-profile-field-full">
                <label class="vd-profile-label">Physician Address</label>
                <input type="text" name="physician_address" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['physician_address'] ?? '') ?>">
            </div>
            </div>
        </form>
        </div>
    </div>

    <!-- ── DENTAL HISTORY ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Dental History</span>
        </div>
        <div class="vd-profile-body">
        <form id="dentalForm" class="vd-patient-profile-form" aria-label="Dental history">
            <div class="vd-profile-grid">
            <div class="vd-profile-field">
                <label class="vd-profile-label">Previous Dentist</label>
                <input type="text" name="previous_dentist" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['previous_dentist'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Last Dental Visit</label>
                <input type="date" name="last_dental_visit" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['last_dental_visit'] ?? '') ?>">
            </div>
            <div class="vd-profile-field vd-profile-field-full">
                <label class="vd-profile-label">Treatment Done</label>
                <input type="text" name="treatment_done" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['treatment_done'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Reason for Visit</label>
                <input type="text" name="reason_for_visit" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['reason_for_visit'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Referred By</label>
                <input type="text" name="referred_by" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['referred_by'] ?? '') ?>">
            </div>
            </div>
        </form>
        </div>
    </div>

    <!-- ── HEALTH QUESTIONNAIRE ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Health Questionnaire</span>
        </div>
        <div class="vd-profile-body">
        <form id="healthForm" class="vd-patient-profile-form" aria-label="Health questionnaire">
            <?php
            $yesnoFields = [
                'good_health'        => 'Are you in good health?',
                'medical_condition'  => 'Under medical condition?',
                'serious_illness'    => 'Serious illness or surgical operation?',
                'hospitalized'       => 'Ever been hospitalized?',
                'medication'         => 'Taking any medication?',
                'smoke'              => 'Do you smoke?',
                'alcohol'            => 'Do you use alcohol?',
                'drugs'              => 'Do you use drugs?',
                'allergy'            => 'Allergic to any substance?',
            ];
            $detailFields = [
                'medical_condition'  => 'medical_condition_detail',
                'serious_illness'    => 'serious_illness_detail',
                'hospitalized'       => 'hospitalized_detail',
                'medication'         => 'medication_detail',
                'allergy'            => 'allergy_detail',
            ];
            ?>
            <table class="vd-profile-hq-table w-100 mb-3">
            <thead>
                <tr>
                <th>Question</th>
                <th class="text-center" style="width:60px;">Yes</th>
                <th class="text-center" style="width:60px;">No</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($yesnoFields as $field => $label): ?>
                <tr>
                <td id="health-question-<?= htmlspecialchars($field) ?>">
                    <?= $label ?>
                    <?php if (isset($detailFields[$field])): ?>
                    <br>
                    <input type="text" name="<?= $detailFields[$field] ?>"
                        class="form-control vd-input mt-1"
                        aria-label="Details for <?= htmlspecialchars($label) ?>"
                        placeholder="If yes, specify…"
                        value="<?= htmlspecialchars($patient[$detailFields[$field]] ?? '') ?>"
                        <?= ($patient[$field] ?? null) ? '' : 'disabled' ?>>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <input type="radio" name="<?= $field ?>" value="1" class="form-check-input vd-radio"
                    aria-label="<?= htmlspecialchars($label) ?> Yes"
                    <?= ($patient[$field] ?? null) == 1 ? 'checked' : '' ?>>
                </td>
                <td class="text-center">
                    <input type="radio" name="<?= $field ?>" value="0" class="form-check-input vd-radio"
                    aria-label="<?= htmlspecialchars($label) ?> No"
                    <?= isset($patient[$field]) && $patient[$field] == 0 ? 'checked' : '' ?>>
                </td>
                </tr>
                <?php endforeach; ?>

                <tr class="vd-hq-section-row" data-women-health>
                <td colspan="3">For Women Only</td>
                </tr>
                <?php foreach (['pregnant' => 'Pregnant?', 'nursing' => 'Nursing?', 'birth_control' => 'Taking birth control pills?'] as $field => $label): ?>
                <tr data-women-health>
                <td><?= $label ?></td>
                <td class="text-center">
                    <input type="radio" name="<?= $field ?>" value="1" class="form-check-input vd-radio"
                    aria-label="<?= htmlspecialchars($label) ?> Yes"
                    <?= ($patient[$field] ?? null) == 1 ? 'checked' : '' ?>>
                </td>
                <td class="text-center">
                    <input type="radio" name="<?= $field ?>" value="0" class="form-check-input vd-radio"
                    aria-label="<?= htmlspecialchars($label) ?> No"
                    <?= isset($patient[$field]) && $patient[$field] == 0 ? 'checked' : '' ?>>
                </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            </table>

            <!-- Blood info -->
            <div class="vd-profile-grid">
            <div class="vd-profile-field">
                <label class="vd-profile-label">Blood Type</label>
                <input type="text" name="blood_type" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['blood_type'] ?? '') ?>" placeholder="e.g. A+">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Blood Pressure</label>
                <input type="text" name="blood_pressure" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['blood_pressure'] ?? '') ?>" placeholder="e.g. 120/80">
            </div>
            </div>
        </form>
        </div>
    </div>

    <!-- ── CONDITIONS ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Medical Conditions</span>
        </div>
        <div class="vd-profile-body">
        <form id="conditionsForm" class="vd-patient-profile-form" aria-label="Medical conditions">
            <label class="vd-no-condition-option mb-3">
                <input type="checkbox" class="form-check-input" id="patientNoKnownConditions" name="no_known_conditions" value="1"
                    <?= !empty($patient['no_known_conditions']) ? 'checked' : '' ?>>
                <span>No known medical conditions</span>
            </label>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-1 mb-3">
            <?php foreach ($allConditions as $cond): ?>
            <div class="col">
                <label class="vd-check-item d-flex align-items-center gap-2 py-2 px-1">
                <input type="checkbox" name="conditions[]" class="form-check-input vd-checkbox m-0"
                    value="<?= htmlspecialchars($cond) ?>"
                    <?= in_array($cond, $conditions) ? 'checked' : '' ?>>
                <span class="small"><?= htmlspecialchars($cond) ?></span>
                </label>
            </div>
            <?php endforeach; ?>
            </div>
            <div class="vd-profile-field" style="max-width:300px;">
            <label class="vd-profile-label">Others</label>
            <input type="text" name="cond_others" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['cond_others'] ?? '') ?>"
                placeholder="Specify…">
            </div>
        </form>
        </div>
    </div>

    <!-- ── CONSENT ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Consent</span>
        </div>
        <div class="vd-profile-body">
        <form id="consentForm" class="vd-patient-profile-form" aria-label="Consent information">
            <div class="vd-profile-grid">
            <div class="vd-profile-field">
                <label class="vd-profile-label">Consent Name</label>
                <input type="text" name="consent_name" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['consent_name'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Consent For</label>
                <select name="consent_for" class="form-select vd-input">
                <option value="" disabled <?= empty($patient['consent_for']) ? 'selected' : '' ?>>— Select —</option>
                <?php foreach (['myself','spouse','son','daughter','others'] as $cf): ?>
                    <option value="<?= $cf ?>" <?= ($patient['consent_for'] ?? '') === $cf ? 'selected' : '' ?>>
                    <?= ucfirst($cf) ?>
                    </option>
                <?php endforeach; ?>
                </select>
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Date</label>
                <input type="date" name="consent_date" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['consent_date'] ?? '') ?>">
            </div>
            </div>
        </form>
        </div>
    </div>

</div>

<div class="vd-profile-savebar" aria-label="Save profile changes">
    <div>
        <strong>Ready to save?</strong>
        <span>Your profile will remain pending until clinic staff review it at check-in.</span>
    </div>
    <button type="button" class="btn vd-btn-gold" id="savePatientProfile">
        <i class="ti ti-device-floppy" aria-hidden="true"></i>
        Save profile changes
    </button>
</div>

<script>
(function () {
    const root = document.querySelector('.vd-profile-sections');
    if (!root) return;

    root.querySelectorAll('.vd-patient-profile-form').forEach((form) => {
        form.addEventListener('submit', event => event.preventDefault());

        form.querySelectorAll('.vd-profile-field').forEach((field, index) => {
            const label = field.querySelector(':scope > label');
            const control = field.querySelector(':scope > input, :scope > select, :scope > textarea');
            if (!label || !control) return;
            if (!control.id) control.id = `${form.id}-${control.name || index}`;
            label.htmlFor = control.id;
        });

    });

    const getField = name => root.querySelector(`[name="${name}"]`);
    const birthdate = getField('birthdate');
    const age = getField('age');
    const gender = getField('gender');
    const phone = getField('phone_number');
    const noKnownConditions = document.getElementById('patientNoKnownConditions');
    const conditionInputs = [...root.querySelectorAll('[name="conditions[]"]')];
    const otherCondition = getField('cond_others');

    function calculateAge() {
        if (!birthdate?.value) {
            if (age) age.value = '';
            return null;
        }
        const born = new Date(`${birthdate.value}T00:00:00`);
        const today = new Date();
        if (Number.isNaN(born.getTime()) || born > today) return null;
        let years = today.getFullYear() - born.getFullYear();
        const monthDifference = today.getMonth() - born.getMonth();
        if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < born.getDate())) years--;
        if (age) age.value = years;
        return years;
    }

    function syncHealthDetails() {
        const womenQuestionsApply = gender?.value === 'Female';
        root.querySelectorAll('[data-women-health]').forEach(row => row.hidden = !womenQuestionsApply);
        ['pregnant', 'nursing', 'birth_control'].forEach(name => {
            root.querySelectorAll(`[name="${name}"]`).forEach(input => input.disabled = !womenQuestionsApply);
        });
        root.querySelectorAll('#healthForm input[type="radio"]').forEach(radio => {
            if (radio.value !== '1') return;
            const detail = getField(`${radio.name}_detail`);
            if (detail) detail.disabled = !radio.checked;
        });
    }

    function syncConditionChoice(source) {
        if (source === noKnownConditions && noKnownConditions.checked) {
            conditionInputs.forEach(input => input.checked = false);
            if (otherCondition) otherCondition.value = '';
        } else if (conditionInputs.some(input => input.checked) || otherCondition?.value.trim()) {
            noKnownConditions.checked = false;
        }
    }

    birthdate?.addEventListener('change', calculateAge);
    gender?.addEventListener('change', syncHealthDetails);
    phone?.addEventListener('input', () => {
        phone.value = phone.value.replace(/\D/g, '').slice(0, 11);
    });
    root.querySelectorAll('#healthForm input[type="radio"]').forEach(radio => radio.addEventListener('change', syncHealthDetails));
    noKnownConditions?.addEventListener('change', () => syncConditionChoice(noKnownConditions));
    conditionInputs.forEach(input => input.addEventListener('change', () => syncConditionChoice(input)));
    otherCondition?.addEventListener('input', () => syncConditionChoice(otherCondition));

    calculateAge();
    syncHealthDetails();
    syncConditionChoice(null);

    const mobileProfile = window.matchMedia('(max-width: 575px)').matches;
    root.querySelectorAll(':scope > .vd-dash-card').forEach((card, index) => {
        const header = card.querySelector(':scope > .vd-dash-card-header');
        const body = card.querySelector(':scope > .vd-profile-body');
        const title = header?.querySelector('.vd-dash-card-title')?.textContent.trim();
        if (!header || !body || !title) return;

        const bodyId = `profile-section-body-${index}`;
        body.id = bodyId;
        body.hidden = mobileProfile;

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'vd-profile-section-toggle';
        toggle.setAttribute('aria-controls', bodyId);
        toggle.setAttribute('aria-expanded', String(!mobileProfile));
        toggle.innerHTML = `<span>${title}</span><i class="ti ti-chevron-down" aria-hidden="true"></i>`;
        toggle.addEventListener('click', () => {
            const opening = body.hidden;
            body.hidden = !opening;
            toggle.setAttribute('aria-expanded', String(opening));
        });
        header.replaceChildren(toggle);
    });

    const saveButton = document.getElementById('savePatientProfile');
    const alertBox = document.getElementById('patientProfileSaveAlert');
    const forms = [...root.querySelectorAll('.vd-patient-profile-form')];

    function setSaving(saving) {
        saveButton.disabled = saving;
        saveButton.innerHTML = saving
            ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Saving changes…'
            : '<i class="ti ti-device-floppy" aria-hidden="true"></i> Save profile changes';
    }

    saveButton?.addEventListener('click', async () => {
        alertBox.classList.add('d-none');
        const invalidForm = forms.find(form => !form.checkValidity());
        if (invalidForm) {
            const invalidCard = invalidForm.closest('.vd-dash-card');
            const invalidBody = invalidCard?.querySelector(':scope > .vd-profile-body');
            const invalidToggle = invalidCard?.querySelector('.vd-profile-section-toggle');
            if (invalidBody?.hidden) {
                invalidBody.hidden = false;
                invalidToggle?.setAttribute('aria-expanded', 'true');
            }
            invalidCard?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            invalidForm.reportValidity();
            return;
        }

        const body = new FormData();
        forms.forEach(form => {
            new FormData(form).forEach((value, key) => body.append(key, value));
        });
        body.set('action', 'saveOwnProfile');
        body.set('csrf_token', <?= json_encode($csrfToken) ?>);

        setSaving(true);
        try {
            const response = await fetch('../../controllers/patientController.php', { method: 'POST', body });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save your profile.');
            alertBox.className = 'alert alert-success vd-profile-save-alert';
            alertBox.innerHTML = '<i class="ti ti-circle-check" aria-hidden="true"></i><span></span>';
            alertBox.querySelector('span').textContent = result.message;
            window.showToast?.(result.message, true);
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (error) {
            alertBox.className = 'alert alert-danger vd-profile-save-alert';
            alertBox.innerHTML = '<i class="ti ti-alert-circle" aria-hidden="true"></i><span></span>';
            alertBox.querySelector('span').textContent = error.message || 'Unable to save your profile.';
            window.showToast?.(error.message || 'Unable to save your profile.', false);
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } finally {
            setSaving(false);
        }
    });
})();
</script>
