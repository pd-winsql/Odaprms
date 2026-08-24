<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';

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

$allConditions = [
    'High Blood Pressure','Heart Disease','Anemia',
    'Low Blood Pressure','Heart Murmur','Angina',
    'Epilepsy/Convulsions','Hepatitis/Liver Diseases','Asthma',
    'AIDS or HIV Infection','Rheumatic Fever','Emphysema',
    'Sexually Transmitted Disease','Hay Fever/Allergies','Bleeding Problems',
    'Stomach Ulcers','Respiratory Problems','Blood Diseases',
    'Fainting/Seizures','Hepatitis/Jaundice','Head Injuries',
    'Rapid Weight Loss','Tuberculosis','Arthritis/Rheumatism',
    'Joint Replacement','Swollen Ankles','Stroke',
    'Heart Surgery','Kidney Disease','Cancer/Tumors',
    'Heart Attack','Diabetes','G6PD',
    'Thyroid Problem','Chest Pain'
];
?>

<div class="d-flex flex-column gap-4">

    <!-- ── PERSONAL INFORMATION ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Personal Information</span>
        </div>
        <div class="vd-profile-body">
        <form id="personalForm" inert>
            <div class="vd-profile-grid">
            <div class="vd-profile-field">
                <label class="vd-profile-label">First Name</label>
                <input type="text" name="firstname" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['firstname'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Middle Name</label>
                <input type="text" name="middlename" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['middlename'] ?? '') ?>">
            </div>
            <div class="vd-profile-field">
                <label class="vd-profile-label">Last Name</label>
                <input type="text" name="lastname" class="form-control vd-input"
                value="<?= htmlspecialchars($patient['lastname'] ?? '') ?>">
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
    <div class="vd-dash-card" id="minorsCard"
        style="<?= (empty($patient['guardian_name']) && (($patient['age'] ?? 99) >= 18)) ? 'display:none;' : '' ?>">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Guardian / Physician</span>
        </div>
        <div class="vd-profile-body">
        <form id="minorsForm" inert>
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
        <form id="dentalForm" inert>
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
        <form id="healthForm" inert>
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
                <td>
                    <?= $label ?>
                    <?php if (isset($detailFields[$field])): ?>
                    <br>
                    <input type="text" name="<?= $detailFields[$field] ?>"
                        class="form-control vd-input mt-1"
                        placeholder="If yes, specify…"
                        value="<?= htmlspecialchars($patient[$detailFields[$field]] ?? '') ?>"
                        <?= ($patient[$field] ?? null) ? '' : 'disabled' ?>>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <input type="radio" name="<?= $field ?>" value="1" class="form-check-input vd-radio"
                    <?= ($patient[$field] ?? null) == 1 ? 'checked' : '' ?>>
                </td>
                <td class="text-center">
                    <input type="radio" name="<?= $field ?>" value="0" class="form-check-input vd-radio"
                    <?= isset($patient[$field]) && $patient[$field] == 0 ? 'checked' : '' ?>>
                </td>
                </tr>
                <?php endforeach; ?>

                <tr class="vd-hq-section-row">
                <td colspan="3">For Women Only</td>
                </tr>
                <?php foreach (['pregnant' => 'Pregnant?', 'nursing' => 'Nursing?', 'birth_control' => 'Taking birth control pills?'] as $field => $label): ?>
                <tr>
                <td><?= $label ?></td>
                <td class="text-center">
                    <input type="radio" name="<?= $field ?>" value="1" class="form-check-input vd-radio"
                    <?= ($patient[$field] ?? null) == 1 ? 'checked' : '' ?>>
                </td>
                <td class="text-center">
                    <input type="radio" name="<?= $field ?>" value="0" class="form-check-input vd-radio"
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
        <form id="conditionsForm" inert>
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
        <form id="consentForm" inert>
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
