<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../support/PatientProfilePolicy.php';

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
$today = date('Y-m-d');
$profileAge = PatientProfilePolicy::ageFromBirthdate($patient['birthdate'] ?? null);
$profileIsMinor = $profileAge !== null && $profileAge < PatientProfilePolicy::AGE_OF_MAJORITY;
$consentOptions = PatientProfilePolicy::consentOptions();
$storedConsentFor = PatientProfilePolicy::normalizeConsentFor($patient['consent_for'] ?? '');
$profileSteps = ['Personal', 'Care contacts', 'Dental history', 'Health', 'Conditions', 'Consent'];
?>

<section class="vd-profile-overview" id="patientProfileOverview" aria-labelledby="profileOverviewTitle">
    <header class="vd-profile-overview-header">
        <div>
            <span class="vd-profile-wizard-eyebrow">Your patient record</span>
            <h2 id="profileOverviewTitle" tabindex="-1">My profile</h2>
            <p>Review the information saved with the clinic.</p>
        </div>
        <button type="button" class="btn vd-btn-gold" id="editPatientProfile">
            <i class="ti ti-pencil" aria-hidden="true"></i>
            Edit profile
        </button>
    </header>
    <p class="vd-profile-overview-note"><i class="ti ti-clock-check" aria-hidden="true"></i> Clinic staff will review and confirm your information during check-in.</p>
    <div class="vd-profile-overview-sections" id="patientProfileSummary"></div>
</section>

<section class="vd-profile-wizard" id="patientProfileEditor" aria-labelledby="profileWizardTitle" hidden>
    <header class="vd-profile-wizard-header">
        <div>
            <span class="vd-profile-wizard-eyebrow">Your patient record</span>
            <h2 id="profileWizardTitle" tabindex="-1">Edit your health profile</h2>
            <p>Complete one short section at a time. Your entries stay in place as you move between steps.</p>
        </div>
        <span class="vd-profile-review-badge">
            <i class="ti ti-clock-check" aria-hidden="true"></i>
            Staff review required
        </span>
    </header>

    <nav class="vd-profile-progress" aria-label="Patient profile steps">
        <div class="vd-profile-progress-summary">
            <span id="profileStepCounter">Step 1 of <?= count($profileSteps) ?></span>
            <strong id="profileStepTitle"><?= htmlspecialchars($profileSteps[0]) ?></strong>
        </div>
        <div class="vd-profile-progress-track" id="profileProgress" role="progressbar"
            aria-label="Profile completion progress" aria-valuemin="1"
            aria-valuemax="<?= count($profileSteps) ?>" aria-valuenow="1">
            <span id="profileProgressBar"></span>
        </div>
        <ol class="vd-profile-step-list">
            <?php foreach ($profileSteps as $index => $step): ?>
                <li>
                    <button type="button" class="vd-profile-step-button" data-step-target="<?= $index ?>"
                        aria-controls="profileStepPanel<?= $index ?>"
                        <?= $index > 0 ? 'disabled' : '' ?>
                        <?= $index === 0 ? 'aria-current="step"' : '' ?>>
                        <span><?= $index + 1 ?></span>
                        <?= htmlspecialchars($step) ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <div id="patientProfileSaveAlert" class="alert d-none" role="status" aria-live="polite"></div>

    <div class="vd-profile-sections">

    <!-- ── PERSONAL INFORMATION ── -->
    <div class="vd-dash-card vd-profile-step" id="profileStepPanel0" data-profile-step="0" data-step-title="Personal information">
        <div class="vd-dash-card-header vd-profile-step-header">
        <div>
        <span class="vd-dash-card-title" id="profilePersonalTitle">Personal Information</span>
        <p>Confirm the details the clinic uses to identify and contact you.</p>
        </div>
        <span class="vd-profile-step-number" aria-hidden="true">01</span>
        </div>
        <div class="vd-profile-body">
        <form id="personalForm" class="vd-patient-profile-form" aria-labelledby="profilePersonalTitle">
            <div class="vd-profile-grid">
            <div class="vd-profile-field">
                <label class="vd-profile-label">First Name</label>
                <input type="text" name="firstname" class="form-control vd-input" autocomplete="given-name"
                value="<?= htmlspecialchars($patient['firstname'] ?? '') ?>" required>
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Middle Name</label>
                <input type="text" name="middlename" class="form-control vd-input" autocomplete="additional-name"
                value="<?= htmlspecialchars($patient['middlename'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Last Name</label>
                <input type="text" name="lastname" class="form-control vd-input" autocomplete="family-name"
                value="<?= htmlspecialchars($patient['lastname'] ?? '') ?>" required>
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Birthdate</label>
                <input type="date" name="birthdate" class="form-control vd-input" autocomplete="bday" max="<?= $today ?>"
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
                autocomplete="tel" maxlength="11" value="<?= htmlspecialchars($patient['phone_number'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Email Address</label>
                <input type="email" name="email" class="form-control vd-input" autocomplete="email"
                value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
            </div>
            <div class="vd-profile-field vd-profile-field-full">
                <label class="vd-profile-label">Home Address</label>
                <input type="text" name="home_address" class="form-control vd-input" autocomplete="street-address"
                value="<?= htmlspecialchars($patient['home_address'] ?? '') ?>">
            </div>
            <div class="vd-profile-field vd-profile-field-full">
                <label class="vd-profile-label">Work Address</label>
                <input type="text" name="work_address" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['work_address'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Occupation</label>
                <input type="text" name="occupation" class="form-control vd-input" autocomplete="organization-title"
                value="<?= htmlspecialchars($patient['occupation'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Office Contact</label>
                <input type="tel" name="office_contact" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['office_contact'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Facebook Account</label>
                <input type="text" name="fb_account" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['fb_account'] ?? '') ?>">
            </div>
            </div>
        </form>
        </div>
    </div>

    <!-- ── FOR MINORS ── -->
    <div class="vd-dash-card vd-profile-step" id="profileStepPanel1" data-profile-step="1" data-step-title="Care contacts" hidden>
        <div class="vd-dash-card-header vd-profile-step-header">
        <div>
        <span class="vd-dash-card-title" id="profileContactsTitle">Care Contacts</span>
        <p>If you are under 18, provide a parent or guardian. You may also add a physician the clinic can contact when needed.</p>
        </div>
        <span class="vd-profile-step-number" aria-hidden="true">02</span>
        </div>
        <div class="vd-profile-body">
        <form id="minorsForm" class="vd-patient-profile-form" aria-labelledby="profileContactsTitle">
            <div class="vd-minor-care-notice <?= $profileIsMinor ? 'is-required' : '' ?>" data-minor-care-notice role="status" aria-live="polite">
                <i class="ti <?= $profileIsMinor ? 'ti-user-shield' : 'ti-info-circle' ?>" aria-hidden="true"></i>
                <div>
                    <strong data-minor-care-title><?= $profileIsMinor ? 'Parent or guardian information required' : 'Parent or guardian information' ?></strong>
                    <span data-minor-care-copy><?= $profileIsMinor
                        ? 'Please provide the name and contact number of the parent or guardian responsible for your care.'
                        : 'Only patients under 18 need to complete these fields. You may leave them blank.' ?></span>
                </div>
            </div>
            <div class="vd-profile-grid">
            <div class="vd-profile-field" data-guardian-field>
                <label class="vd-profile-label">Parent / Guardian Name <span data-minor-required-marker <?= $profileIsMinor ? '' : 'hidden' ?>>Required</span></label>
                <input type="text" name="guardian_name" class="form-control vd-input"
                autocomplete="name" value="<?= htmlspecialchars($patient['guardian_name'] ?? '') ?>"
                <?= $profileIsMinor ? 'required' : '' ?>>
            </div>
            <div class="vd-profile-field" data-guardian-field>
                <label class="vd-profile-label">Parent / Guardian Contact Number <span data-minor-required-marker <?= $profileIsMinor ? '' : 'hidden' ?>>Required</span></label>
                <input type="tel" name="guardian_contact" class="form-control vd-input" inputmode="numeric"
                autocomplete="tel" minlength="11" maxlength="11" pattern="[0-9]{11}"
                title="Enter an 11-digit contact number" value="<?= htmlspecialchars($patient['guardian_contact'] ?? '') ?>"
                <?= $profileIsMinor ? 'required' : '' ?>>
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Physician Name</label>
                <input type="text" name="physician_name" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['physician_name'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Physician Contact</label>
                <input type="tel" name="physician_contact" class="form-control vd-input" inputmode="tel"
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
    <div class="vd-dash-card vd-profile-step" id="profileStepPanel2" data-profile-step="2" data-step-title="Dental history" hidden>
        <div class="vd-dash-card-header vd-profile-step-header">
        <div>
        <span class="vd-dash-card-title" id="profileDentalTitle">Dental History</span>
        <p>Tell the clinic about your previous care and the reason for your next visit.</p>
        </div>
        <span class="vd-profile-step-number" aria-hidden="true">03</span>
        </div>
        <div class="vd-profile-body">
        <form id="dentalForm" class="vd-patient-profile-form" aria-labelledby="profileDentalTitle">
            <div class="vd-profile-grid">
            <div class="vd-profile-field">
                <label class="vd-profile-label">Previous Dentist</label>
                <input type="text" name="previous_dentist" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['previous_dentist'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Last Dental Visit</label>
                <input type="date" name="last_dental_visit" class="form-control vd-input" max="<?= $today ?>"
                value="<?= htmlspecialchars($patient['last_dental_visit'] ?? '') ?>">
            </div>
            <div class="vd-profile-field vd-profile-field-full">
                <label class="vd-profile-label">Treatment Done</label>
                <input type="text" name="treatment_done" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['treatment_done'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Reason for Dental Visit</label>
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
    <div class="vd-dash-card vd-profile-step" id="profileStepPanel3" data-profile-step="3" data-step-title="Health questionnaire" hidden>
        <div class="vd-dash-card-header vd-profile-step-header">
        <div>
        <span class="vd-dash-card-title" id="profileHealthTitle">Health Questionnaire</span>
        <p>Select an answer for each item you can confirm. Details appear only when needed.</p>
        </div>
        <span class="vd-profile-step-number" aria-hidden="true">04</span>
        </div>
        <div class="vd-profile-body">
        <form id="healthForm" class="vd-patient-profile-form" aria-labelledby="profileHealthTitle">
            <?php
            $yesnoFields = [
                'good_health'        => 'Are you in good health?',
                'medical_condition'  => 'Are you under a medical condition right now?',
                'serious_illness'    => 'Have you ever had a serious illness or surgical operation?',
                'hospitalized'       => 'Have you ever been hospitalized?',
                'medication'         => 'Are you taking any medication?',
                'smoke'              => 'Do you smoke?',
                'alcohol'            => 'Do you use alcohol?',
                'drugs'              => 'Do you use drugs?',
                'allergy'            => 'Are you allergic to local anesthetics, latex, penicillin, aspirin, or other substances?',
            ];
            $detailFields = [
                'medical_condition'  => 'medical_condition_detail',
                'serious_illness'    => 'serious_illness_detail',
                'hospitalized'       => 'hospitalized_detail',
                'medication'         => 'medication_detail',
                'allergy'            => 'allergy_detail',
            ];
            ?>
            <div class="vd-health-question-list">
                <?php foreach ($yesnoFields as $field => $label): ?>
                <fieldset class="vd-health-question">
                    <legend><?= htmlspecialchars($label) ?></legend>
                    <div class="vd-health-choice">
                        <label>
                            <input type="radio" name="<?= $field ?>" value="1"
                                <?= ($patient[$field] ?? null) == 1 ? 'checked' : '' ?>>
                            <span>Yes</span>
                        </label>
                        <label>
                            <input type="radio" name="<?= $field ?>" value="0"
                                <?= isset($patient[$field]) && $patient[$field] == 0 ? 'checked' : '' ?>>
                            <span>No</span>
                        </label>
                    </div>
                    <?php if (isset($detailFields[$field])): ?>
                    <div class="vd-health-detail" data-health-detail <?= ($patient[$field] ?? null) ? '' : 'hidden' ?>>
                        <label for="health-detail-<?= $field ?>">If yes, please specify</label>
                        <input type="text" id="health-detail-<?= $field ?>"
                            name="<?= $detailFields[$field] ?>" class="form-control vd-input"
                            value="<?= htmlspecialchars($patient[$detailFields[$field]] ?? '') ?>"
                            <?= ($patient[$field] ?? null) ? '' : 'disabled' ?>>
                    </div>
                    <?php endif; ?>
                </fieldset>
                <?php endforeach; ?>

                <div class="vd-health-section-heading" data-women-health>
                    <strong>For women only</strong>
                    <span>Shown when gender is set to Female.</span>
                </div>
                <?php foreach (['pregnant' => 'Are you pregnant?', 'nursing' => 'Are you nursing?', 'birth_control' => 'Are you taking birth control pills?'] as $field => $label): ?>
                <fieldset class="vd-health-question" data-women-health>
                    <legend><?= htmlspecialchars($label) ?></legend>
                    <div class="vd-health-choice">
                        <label>
                            <input type="radio" name="<?= $field ?>" value="1"
                                <?= ($patient[$field] ?? null) == 1 ? 'checked' : '' ?>>
                            <span>Yes</span>
                        </label>
                        <label>
                            <input type="radio" name="<?= $field ?>" value="0"
                                <?= isset($patient[$field]) && $patient[$field] == 0 ? 'checked' : '' ?>>
                            <span>No</span>
                        </label>
                    </div>
                </fieldset>
                <?php endforeach; ?>
            </div>

            <!-- Blood info -->
            <div class="vd-profile-grid vd-profile-blood-grid">
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
    <div class="vd-dash-card vd-profile-step" id="profileStepPanel4" data-profile-step="4" data-step-title="Medical conditions" hidden>
        <div class="vd-dash-card-header vd-profile-step-header">
        <div>
        <span class="vd-dash-card-title" id="profileConditionsTitle">Medical Conditions</span>
        <p>Select every condition that applies, or confirm that none are known.</p>
        </div>
        <span class="vd-profile-step-number" aria-hidden="true">05</span>
        </div>
        <div class="vd-profile-body">
        <form id="conditionsForm" class="vd-patient-profile-form" aria-labelledby="profileConditionsTitle">
            <label class="vd-no-condition-option">
                <input type="checkbox" class="form-check-input" id="patientNoKnownConditions" name="no_known_conditions" value="1"
                    <?= !empty($patient['no_known_conditions']) ? 'checked' : '' ?>>
                <span>
                    <strong>No known medical conditions</strong>
                    <small>Selecting this clears the conditions below.</small>
                </span>
            </label>
            <div class="vd-condition-grid">
            <?php foreach ($allConditions as $cond): ?>
                <label class="vd-check-item">
                <input type="checkbox" name="conditions[]" class="form-check-input vd-checkbox"
                    value="<?= htmlspecialchars($cond) ?>"
                    <?= in_array($cond, $conditions, true) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($cond) ?></span>
                </label>
            <?php endforeach; ?>
            </div>
            <div class="vd-profile-field vd-profile-other-condition">
            <label class="vd-profile-label">Other Condition</label>
            <input type="text" name="cond_others" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['cond_others'] ?? '') ?>"
                placeholder="Specify another condition">
            </div>
        </form>
        </div>
    </div>

    <!-- ── CONSENT ── -->
    <div class="vd-dash-card vd-profile-step" id="profileStepPanel5" data-profile-step="5" data-step-title="Consent and review" hidden>
        <div class="vd-dash-card-header vd-profile-step-header">
        <div>
        <span class="vd-dash-card-title" id="profileConsentTitle">Consent &amp; Review</span>
        <p>Read the clinic declaration, then confirm who is providing consent.</p>
        </div>
        <span class="vd-profile-step-number" aria-hidden="true">06</span>
        </div>
        <div class="vd-profile-body">
        <form id="consentForm" class="vd-patient-profile-form" aria-labelledby="profileConsentTitle">
            <div class="vd-consent-declaration" aria-labelledby="consentDeclarationTitle">
                <span class="vd-consent-declaration-label">Clinic consent declaration</span>
                <h3 id="consentDeclarationTitle">Consent for dental care</h3>
                <p>I, the person named below, do hereby consent to the performance upon myself, my spouse, son, daughter, or other person identified below of all dental procedures, operations, and/or treatment that may be considered necessary to restore oral and dental health.</p>
                <p>This consent is given voluntarily and whatever result of any intervention or treatment may be, I absolve my dentist from liability.</p>
                <p>Be it known further that I am willing to pay for all services rendered to me and/or my family.</p>
            </div>
            <div class="vd-profile-grid">
            <div class="vd-profile-field">
                <label class="vd-profile-label" data-consent-name-label><?= $profileIsMinor ? 'Parent / Guardian Providing Consent' : 'Name of Patient or Representative' ?></label>
                <input type="text" name="consent_name" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['consent_name'] ?? '') ?>" required>
            </div>
            <fieldset class="vd-profile-field vd-consent-for-field">
                <legend class="vd-profile-label">Consent Applies To</legend>
                <div class="vd-health-choice vd-consent-choice-grid">
                <?php foreach ($consentOptions as $cf => $label):
                    $disabledForMinor = $profileIsMinor && in_array($cf, ['myself', 'spouse'], true); ?>
                    <label>
                        <input type="radio" name="consent_for" value="<?= $cf ?>"
                            <?= $storedConsentFor === $cf ? 'checked' : '' ?>
                            <?= $disabledForMinor ? 'disabled' : '' ?> required>
                        <span><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
                </div>
                <small class="vd-consent-guidance" data-consent-guidance><?= $profileIsMinor
                    ? 'Choose how the minor is related to the representative named above.'
                    : 'Choose the person receiving the dental care.' ?></small>
            </fieldset>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Consent Date</label>
                <input type="date" name="consent_date" class="form-control vd-input" max="<?= $today ?>"
                value="<?= htmlspecialchars($patient['consent_date'] ?? '') ?>">
            </div>
            </div>
            <p class="vd-consent-review-note">
                <i class="ti ti-info-circle" aria-hidden="true"></i>
                Clinic staff will review and confirm this information with you during check-in.
            </p>
        </form>
        </div>
    </div>

</div>

<footer class="vd-profile-wizard-actions" aria-label="Profile navigation and save status">
    <p class="vd-profile-save-state" id="profileSaveState" aria-live="polite">
        <i class="ti ti-circle-check" aria-hidden="true"></i>
        <span>No unsaved changes</span>
    </p>
    <div class="vd-profile-action-buttons">
        <button type="button" class="btn vd-profile-back-button vd-profile-cancel-button" id="cancelPatientProfileEdit">
            Cancel editing
        </button>
        <button type="button" class="btn vd-profile-back-button" id="profileBack" disabled>
            <i class="ti ti-arrow-left" aria-hidden="true"></i>
            Back
        </button>
        <button type="button" class="btn vd-btn-gold" id="profileNext">
            Continue
            <i class="ti ti-arrow-right" aria-hidden="true"></i>
        </button>
        <button type="button" class="btn vd-btn-gold" id="savePatientProfile" hidden disabled>
            <i class="ti ti-device-floppy" aria-hidden="true"></i>
            Save profile changes
        </button>
    </div>
</footer>
</section>

<script>
(function () {
    const root = document.querySelector('.vd-profile-sections');
    if (!root) return;

    const forms = [...root.querySelectorAll('.vd-patient-profile-form')];
    const steps = [...root.querySelectorAll('[data-profile-step]')];
    const stepButtons = [...document.querySelectorAll('[data-step-target]')];
    const stepCounter = document.getElementById('profileStepCounter');
    const stepTitle = document.getElementById('profileStepTitle');
    const progress = document.getElementById('profileProgress');
    const progressBar = document.getElementById('profileProgressBar');
    const backButton = document.getElementById('profileBack');
    const nextButton = document.getElementById('profileNext');
    const saveButton = document.getElementById('savePatientProfile');
    const saveState = document.getElementById('profileSaveState');
    const alertBox = document.getElementById('patientProfileSaveAlert');
    const overview = document.getElementById('patientProfileOverview');
    const editor = document.getElementById('patientProfileEditor');
    const summary = document.getElementById('patientProfileSummary');
    const editButton = document.getElementById('editPatientProfile');
    const cancelButton = document.getElementById('cancelPatientProfileEdit');
    let currentStep = 0;
    let furthestStep = 0;
    let isDirty = false;
    let isSaving = false;

    forms.forEach((form) => {
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
    const guardianName = getField('guardian_name');
    const guardianContact = getField('guardian_contact');
    const consentNameLabel = root.querySelector('[data-consent-name-label]');
    const consentGuidance = root.querySelector('[data-consent-guidance]');
    const consentChoices = [...root.querySelectorAll('[name="consent_for"]')];
    const minorCareNotice = root.querySelector('[data-minor-care-notice]');
    const minorCareTitle = root.querySelector('[data-minor-care-title]');
    const minorCareCopy = root.querySelector('[data-minor-care-copy]');
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
            if (!detail) return;
            detail.disabled = !radio.checked;
            const detailContainer = detail.closest('[data-health-detail]');
            if (detailContainer) detailContainer.hidden = !radio.checked;
       });
    }

    function syncMinorPolicy() {
        const years = calculateAge();
        const isMinor = years !== null && years < <?= PatientProfilePolicy::AGE_OF_MAJORITY ?>;

        [guardianName, guardianContact].forEach(control => {
            if (control) control.required = isMinor;
        });
        root.querySelectorAll('[data-minor-required-marker]').forEach(marker => marker.hidden = !isMinor);

        if (minorCareNotice) minorCareNotice.classList.toggle('is-required', isMinor);
        if (minorCareTitle) minorCareTitle.textContent = isMinor ? 'Parent or guardian information required' : 'Parent or guardian information';
        if (minorCareCopy) {
            minorCareCopy.textContent = isMinor
                ? 'Please provide the name and contact number of the parent or guardian responsible for your care.'
                : 'Only patients under 18 need to complete these fields. You may leave them blank.';
        }
        const noticeIcon = minorCareNotice?.querySelector('i');
        if (noticeIcon) noticeIcon.className = `ti ${isMinor ? 'ti-user-shield' : 'ti-info-circle'}`;

        consentChoices.forEach(choice => {
            const unavailableForMinor = isMinor && ['myself', 'spouse'].includes(choice.value);
            choice.disabled = unavailableForMinor;
            if (unavailableForMinor && choice.checked) choice.checked = false;
        });
        if (consentNameLabel) {
            consentNameLabel.textContent = isMinor
                ? 'Parent / Guardian Providing Consent'
                : 'Name of Patient or Representative';
        }
        if (consentGuidance) {
            consentGuidance.textContent = isMinor
                ? 'Choose how the minor is related to the representative named above.'
                : 'Choose the person receiving the dental care.';
        }
    }

    function syncConditionChoice(source) {
        if (source === noKnownConditions && noKnownConditions.checked) {
            conditionInputs.forEach(input => input.checked = false);
            if (otherCondition) otherCondition.value = '';
        } else if (conditionInputs.some(input => input.checked) || otherCondition?.value.trim()) {
            noKnownConditions.checked = false;
        }
    }

    birthdate?.addEventListener('change', syncMinorPolicy);
    gender?.addEventListener('change', syncHealthDetails);
    phone?.addEventListener('input', () => {
        phone.value = phone.value.replace(/\D/g, '').slice(0, 11);
    });
    guardianContact?.addEventListener('input', () => {
        guardianContact.value = guardianContact.value.replace(/\D/g, '').slice(0, 11);
    });
    root.querySelectorAll('#healthForm input[type="radio"]').forEach(radio => radio.addEventListener('change', syncHealthDetails));
    noKnownConditions?.addEventListener('change', () => syncConditionChoice(noKnownConditions));
    conditionInputs.forEach(input => input.addEventListener('change', () => syncConditionChoice(input)));
    otherCondition?.addEventListener('input', () => syncConditionChoice(otherCondition));

    syncMinorPolicy();
    syncHealthDetails();
    syncConditionChoice(null);

    function updateSaveState(message, iconClass) {
        const icon = saveState.querySelector('i');
        const text = saveState.querySelector('span');
        icon.className = 'ti ' + iconClass;
        text.textContent = message;
    }

    function displayValue(control) {
        if (!control) return '';
        if (control.tagName === 'SELECT') {
            return control.value ? control.selectedOptions[0]?.textContent.trim() || control.value : '';
        }
        const value = control.value.trim();
        if (!value) return '';
        if (control.type === 'date') {
            const date = new Date(`${value}T00:00:00`);
            if (!Number.isNaN(date.getTime())) {
                return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
            }
        }
        return value;
    }

    function addSummaryItem(list, label, value) {
        const item = document.createElement('div');
        item.className = 'vd-profile-overview-item';
        const term = document.createElement('dt');
        term.textContent = label;
        const detail = document.createElement('dd');
        detail.textContent = value;
        if (!value) detail.classList.add('is-empty');
        item.append(term, detail);
        list.append(item);
    }

    function renderProfileSummary() {
        summary.replaceChildren();
        steps.forEach((step, index) => {
            const section = document.createElement('section');
            section.className = 'vd-profile-overview-section';
            const heading = document.createElement('div');
            heading.className = 'vd-profile-overview-section-heading';
            const number = document.createElement('span');
            number.textContent = String(index + 1).padStart(2, '0');
            number.setAttribute('aria-hidden', 'true');
            const title = document.createElement('h3');
            title.textContent = step.querySelector('.vd-dash-card-title')?.textContent.trim() || step.dataset.stepTitle;
            heading.append(number, title);
            const values = document.createElement('dl');
            values.className = 'vd-profile-overview-grid';

            if (index === 3) {
                step.querySelectorAll('.vd-health-question').forEach(question => {
                    if (question.hidden) return;
                    const selected = question.querySelector('input[type="radio"]:checked');
                    const answer = selected?.value === '1' ? 'Yes' : selected?.value === '0' ? 'No' : '';
                    const detail = question.querySelector('[data-health-detail] input');
                    const value = answer === 'Yes' && detail?.value.trim()
                        ? `Yes — ${detail.value.trim()}` : answer;
                    addSummaryItem(values, question.querySelector('legend')?.textContent.trim() || 'Health question', value);
                });
            }

            if (index === 4) {
                const selected = [...step.querySelectorAll('[name="conditions[]"]:checked')]
                    .map(input => input.closest('label')?.textContent.trim() || input.value);
                const noKnown = step.querySelector('[name="no_known_conditions"]')?.checked;
                addSummaryItem(values, 'Known conditions', noKnown
                    ? 'No known medical conditions'
                    : selected.length ? selected.join(', ') : '');
            }

            step.querySelectorAll('.vd-profile-field').forEach(field => {
                const label = field.querySelector(':scope > .vd-profile-label');
                if (!label) return;
                const name = label.textContent.trim().replace(/\s+Required$/, '');
                const control = field.querySelector(':scope > input, :scope > select, :scope > textarea');
                if (control) {
                    addSummaryItem(values, name, displayValue(control));
                    return;
                }
                const selected = field.querySelector('input[type="radio"]:checked');
                addSummaryItem(values, name, selected?.closest('label')?.textContent.trim() || '');
            });

            section.append(heading, values);
            summary.append(section);
        });
    }

    function showOverview() {
        editor.hidden = true;
        overview.hidden = false;
        overview.scrollIntoView({ block: 'start' });
        document.getElementById('profileOverviewTitle').focus({ preventScroll: true });
    }

    function rememberSavedValues() {
        forms.forEach(form => form.querySelectorAll('input, select, textarea').forEach(control => {
            if (control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type)) {
                control.defaultChecked = control.checked;
            } else if (control instanceof HTMLSelectElement) {
                [...control.options].forEach(option => option.defaultSelected = option.selected);
            } else {
                control.defaultValue = control.value;
            }
        }));
    }

    function markDirty() {
        if (isSaving) return;
        isDirty = true;
        saveButton.disabled = false;
        updateSaveState('Unsaved changes', 'ti-pencil');
    }

    function validateStep(index) {
        const form = steps[index]?.querySelector('form');
        if (!form || form.checkValidity()) return true;
        form.reportValidity();
        return false;
    }

    function showStep(index, focusHeading = false) {
        currentStep = Math.max(0, Math.min(index, steps.length - 1));
        steps.forEach((step, stepIndex) => {
            step.hidden = stepIndex !== currentStep;
        });
        stepButtons.forEach((button, stepIndex) => {
            button.disabled = stepIndex > furthestStep;
            button.classList.toggle('is-complete', stepIndex < currentStep);
            if (stepIndex === currentStep) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
        });

        stepCounter.textContent = `Step ${currentStep + 1} of ${steps.length}`;
        stepTitle.textContent = steps[currentStep].dataset.stepTitle;
        progress.setAttribute('aria-valuenow', String(currentStep + 1));
        progressBar.style.width = `${(currentStep + 1) / steps.length * 100}%`;
        backButton.disabled = currentStep === 0;
        nextButton.hidden = currentStep === steps.length - 1;
        saveButton.hidden = currentStep !== steps.length - 1;

        if (focusHeading) {
            const heading = steps[currentStep].querySelector('.vd-dash-card-title');
            if (heading) {
                heading.tabIndex = -1;
                heading.focus({ preventScroll: true });
            }
            document.querySelector('.vd-profile-progress').scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                block: 'start'
            });
        }
    }

    root.addEventListener('input', markDirty);
    root.addEventListener('change', markDirty);
    editButton.addEventListener('click', () => {
        overview.hidden = true;
        editor.hidden = false;
        showStep(0);
        editor.scrollIntoView({ block: 'start' });
        document.getElementById('profileWizardTitle').focus({ preventScroll: true });
    });
    cancelButton.addEventListener('click', () => {
        if (isDirty && !window.confirm('Discard your unsaved profile changes?')) return;
        forms.forEach(form => form.reset());
        syncMinorPolicy();
        syncHealthDetails();
        syncConditionChoice(null);
        isDirty = false;
        furthestStep = 0;
        saveButton.disabled = true;
        alertBox.classList.add('d-none');
        updateSaveState('No unsaved changes', 'ti-circle-check');
        showStep(0);
        showOverview();
    });
    backButton.addEventListener('click', () => showStep(currentStep - 1, true));
    nextButton.addEventListener('click', () => {
        if (!validateStep(currentStep)) return;
        furthestStep = Math.max(furthestStep, currentStep + 1);
        showStep(currentStep + 1, true);
    });
    stepButtons.forEach(button => {
        button.addEventListener('click', () => {
            const target = Number(button.dataset.stepTarget);
            if (!Number.isInteger(target) || target > furthestStep) return;
            if (target > currentStep) {
                for (let index = currentStep; index < target; index += 1) {
                    const form = steps[index]?.querySelector('form');
                    if (form && !form.checkValidity()) {
                        showStep(index, true);
                        form.reportValidity();
                        return;
                    }
                }
            }
            showStep(target, true);
        });
    });

    function setSaving(saving) {
        isSaving = saving;
        editor.inert = saving;
        editor.setAttribute('aria-busy', String(saving));
        saveButton.disabled = saving || !isDirty;
        saveButton.innerHTML = saving
            ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Saving…'
            : '<i class="ti ti-device-floppy" aria-hidden="true"></i> Save profile changes';
        if (saving) updateSaveState('Saving your profile…', 'ti-loader-2');
    }

    saveButton.addEventListener('click', async () => {
        alertBox.classList.add('d-none');
        const invalidIndex = forms.findIndex(form => !form.checkValidity());
        if (invalidIndex !== -1) {
            furthestStep = Math.max(furthestStep, invalidIndex);
            showStep(invalidIndex, true);
            forms[invalidIndex].reportValidity();
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
            const response = await fetch(window.vdAppUrl('apps/controllers/patientController.php'), { method: 'POST', body });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save your profile.');
            rememberSavedValues();
            isDirty = false;
            furthestStep = 0;
            renderProfileSummary();
            updateSaveState('All changes saved', 'ti-circle-check');
            window.showToast?.(result.message, true);
            showStep(0);
            showOverview();
        } catch (error) {
            alertBox.className = 'alert alert-danger vd-profile-save-alert';
            alertBox.innerHTML = '<i class="ti ti-alert-circle" aria-hidden="true"></i><span></span>';
            alertBox.querySelector('span').textContent = error.message || 'Unable to save your profile.';
            updateSaveState('Save failed — your entries are still here', 'ti-alert-circle');
            window.showToast?.(error.message || 'Unable to save your profile.', false);
        } finally {
            setSaving(false);
        }
    });

    renderProfileSummary();
    showStep(0);
})();
</script>
