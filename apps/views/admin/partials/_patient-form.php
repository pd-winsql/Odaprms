<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}
require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
$db = new Database();
$patient = (new Patient($db->connect()))->getPatientFull((int) ($_GET['id'] ?? 0));
if (!$patient) { echo '<div class="vd-empty-state">Patient not found.</div>'; exit; }
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
function staffVal($value) { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES); }
function staffRadio($name, $value, $current) {
    $checked = (string) $current === (string) $value ? ' checked' : '';
    return '<label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="' . $name . '" value="' . $value . '"' . $checked . '> ' . ucfirst($value) . '</label>';
}
?>

<div class="d-flex flex-column gap-4">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div><div class="vd-welcome-greet">FRONT DESK PATIENT FORM</div><div class="vd-welcome-name"><?= staffVal($patient['full_name']) ?></div><p class="text-muted small mt-2 mb-0">Complete the required profile before marking the patient ready.</p></div>
        <button type="button" class="btn vd-btn-outline" id="cancelStaffPatientForm">Back to Dashboard</button>
    </div>

    <form id="staffPatientForm">
        <input type="hidden" name="action" value="completeProfileByStaff">
        <input type="hidden" name="csrf_token" value="<?= staffVal($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="patient_id" value="<?= (int) $patient['patient_id'] ?>">

        <div class="vd-dash-card mb-4">
            <div class="vd-dash-card-header"><span class="vd-dash-card-title">Personal Information</span></div>
            <div class="vd-dash-card-body"><div class="row g-3">
                <div class="col-md-4"><label class="vd-label form-label">First Name *</label><input class="form-control vd-input" name="firstname" value="<?= staffVal($patient['firstname']) ?>" required></div>
                <div class="col-md-4"><label class="vd-label form-label">Middle Name</label><input class="form-control vd-input" name="middlename" value="<?= staffVal($patient['middlename']) ?>"></div>
                <div class="col-md-4"><label class="vd-label form-label">Last Name *</label><input class="form-control vd-input" name="lastname" value="<?= staffVal($patient['lastname']) ?>" required></div>
                <div class="col-md-4"><label class="vd-label form-label">Birthdate *</label><input type="date" class="form-control vd-input" name="birthdate" max="<?= date('Y-m-d') ?>" value="<?= staffVal($patient['birthdate']) ?>" required></div>
                <div class="col-md-4"><label class="vd-label form-label">Gender *</label><select class="form-select vd-input" name="gender" required><option value="">Select</option><?php foreach (['Male','Female','Prefer not to say'] as $option): ?><option <?= $patient['gender'] === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="vd-label form-label">Civil Status</label><input class="form-control vd-input" name="civil_status" value="<?= staffVal($patient['civil_status']) ?>"></div>
                <div class="col-md-6"><label class="vd-label form-label">Phone Number *</label><input class="form-control vd-input" name="phone_number" value="<?= staffVal($patient['phone_number']) ?>" required></div>
                <div class="col-md-6"><label class="vd-label form-label">Email</label><input type="email" class="form-control vd-input" name="email" value="<?= staffVal($patient['email']) ?>"></div>
                <div class="col-12"><label class="vd-label form-label">Home Address</label><input class="form-control vd-input" name="home_address" value="<?= staffVal($patient['home_address']) ?>"></div>
                <div class="col-md-6"><label class="vd-label form-label">Work Address</label><input class="form-control vd-input" name="work_address" value="<?= staffVal($patient['work_address']) ?>"></div>
                <div class="col-md-3"><label class="vd-label form-label">Occupation</label><input class="form-control vd-input" name="occupation" value="<?= staffVal($patient['occupation']) ?>"></div>
                <div class="col-md-3"><label class="vd-label form-label">Office Contact</label><input class="form-control vd-input" name="office_contact" value="<?= staffVal($patient['office_contact']) ?>"></div>
                <div class="col-md-6"><label class="vd-label form-label">Facebook Account</label><input class="form-control vd-input" name="fb_account" value="<?= staffVal($patient['fb_account']) ?>"></div>
                <div class="col-md-3"><label class="vd-label form-label">Guardian Name</label><input class="form-control vd-input" name="guardian_name" value="<?= staffVal($patient['guardian_name']) ?>"></div>
                <div class="col-md-3"><label class="vd-label form-label">Guardian Contact</label><input class="form-control vd-input" name="guardian_contact" value="<?= staffVal($patient['guardian_contact']) ?>"></div>
                <div class="col-md-4"><label class="vd-label form-label">Physician Name</label><input class="form-control vd-input" name="physician_name" value="<?= staffVal($patient['physician_name']) ?>"></div>
                <div class="col-md-4"><label class="vd-label form-label">Physician Contact</label><input class="form-control vd-input" name="physician_contact" value="<?= staffVal($patient['physician_contact']) ?>"></div>
                <div class="col-md-4"><label class="vd-label form-label">Physician Address</label><input class="form-control vd-input" name="physician_address" value="<?= staffVal($patient['physician_address']) ?>"></div>
            </div></div>
        </div>

        <div class="vd-dash-card mb-4">
            <div class="vd-dash-card-header"><span class="vd-dash-card-title">Dental History</span></div>
            <div class="vd-dash-card-body"><div class="row g-3">
                <div class="col-md-4"><label class="vd-label form-label">Previous Dentist</label><input class="form-control vd-input" name="previous_dentist" value="<?= staffVal($patient['previous_dentist']) ?>"></div>
                <div class="col-md-4"><label class="vd-label form-label">Last Dental Visit</label><input type="date" class="form-control vd-input" name="last_dental_visit" value="<?= staffVal($patient['last_dental_visit']) ?>"></div>
                <div class="col-md-4"><label class="vd-label form-label">Referred By</label><input class="form-control vd-input" name="referred_by" value="<?= staffVal($patient['referred_by']) ?>"></div>
                <div class="col-md-6"><label class="vd-label form-label">Treatment Done</label><textarea class="form-control vd-input" name="treatment_done" rows="2"><?= staffVal($patient['treatment_done']) ?></textarea></div>
                <div class="col-md-6"><label class="vd-label form-label">Reason for Visit *</label><textarea class="form-control vd-input" name="reason_for_visit" rows="2" required><?= staffVal($patient['reason_for_visit']) ?></textarea></div>
            </div></div>
        </div>

        <div class="vd-dash-card mb-4">
            <div class="vd-dash-card-header"><span class="vd-dash-card-title">Medical History</span></div>
            <div class="vd-dash-card-body">
                <?php
                $medicalQuestions = [
                    'good_health' => 'In good health?', 'medical_condition' => 'Under medical treatment?',
                    'serious_illness' => 'Serious illness or operation?', 'hospitalized' => 'Previously hospitalized?',
                    'medication' => 'Taking medication?', 'smoke' => 'Smokes?', 'alcohol' => 'Uses alcohol?',
                    'drugs' => 'Uses prohibited drugs?', 'allergy' => 'Has allergies?', 'pregnant' => 'Pregnant?',
                    'nursing' => 'Nursing?', 'birth_control' => 'Taking birth-control pills?'
                ];
                foreach ($medicalQuestions as $field => $label): ?>
                    <div class="row align-items-center py-2 border-bottom"><div class="col-md-7"><?= htmlspecialchars($label) ?></div><div class="col-md-5"><?= staffRadio($field, 'yes', $patient[$field] === null ? null : ($patient[$field] ? 'yes' : 'no')) ?><?= staffRadio($field, 'no', $patient[$field] === null ? null : ($patient[$field] ? 'yes' : 'no')) ?></div></div>
                <?php endforeach; ?>
                <div class="row g-3 mt-2">
                    <?php foreach (['medical_condition_detail'=>'Medical treatment details','serious_illness_detail'=>'Illness/operation details','hospitalized_detail'=>'Hospitalization details','medication_detail'=>'Medication details','allergy_detail'=>'Allergy details'] as $field=>$label): ?>
                        <div class="col-md-6"><label class="vd-label form-label"><?= $label ?></label><input class="form-control vd-input" name="<?= $field ?>" value="<?= staffVal($patient[$field]) ?>"></div>
                    <?php endforeach; ?>
                    <div class="col-md-3"><label class="vd-label form-label">Blood Type</label><input class="form-control vd-input" name="blood_type" value="<?= staffVal($patient['blood_type']) ?>"></div>
                    <div class="col-md-3"><label class="vd-label form-label">Blood Pressure</label><input class="form-control vd-input" name="blood_pressure" value="<?= staffVal($patient['blood_pressure']) ?>"></div>
                    <div class="col-md-6"><label class="vd-label form-label">Medical Conditions (comma-separated)</label><input class="form-control vd-input" name="conditions_text" value="<?= staffVal($patient['patient_conditions']) ?>"></div>
                    <div class="col-md-6"><label class="vd-label form-label">Other Conditions</label><input class="form-control vd-input" name="cond_others" value="<?= staffVal($patient['cond_others'] ?? '') ?>"></div>
                </div>
            </div>
        </div>

        <div class="vd-dash-card mb-4">
            <div class="vd-dash-card-header"><span class="vd-dash-card-title">Consent</span></div>
            <div class="vd-dash-card-body"><div class="row g-3">
                <div class="col-md-6"><label class="vd-label form-label">Consent Name *</label><input class="form-control vd-input" name="consent_name" value="<?= staffVal($patient['consent_name']) ?>" required></div>
                <div class="col-md-6"><label class="vd-label form-label">Consent For *</label><select class="form-select vd-input" name="consent_for" required><option value="">Select</option><?php foreach (['myself'=>'Myself','spouse'=>'Spouse','son'=>'Son','daughter'=>'Daughter','others'=>'Others'] as $value=>$label): ?><option value="<?= $value ?>" <?= $patient['consent_for'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            </div></div>
        </div>

        <div id="staffPatientFormError" class="alert alert-danger d-none"></div>
        <div class="d-flex justify-content-end gap-2"><button type="button" class="btn vd-btn-outline" id="cancelStaffPatientFormBottom">Cancel</button><button type="submit" class="btn vd-btn-gold" id="saveStaffPatientForm">Complete Patient Form</button></div>
    </form>
</div>

<script>
(function () {
    const back = () => document.querySelector('[data-page="dashboard-content.php"]')?.click();
    document.getElementById('cancelStaffPatientForm')?.addEventListener('click', back);
    document.getElementById('cancelStaffPatientFormBottom')?.addEventListener('click', back);
    document.getElementById('staffPatientForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = document.getElementById('saveStaffPatientForm');
        const errorBox = document.getElementById('staffPatientFormError');
        errorBox.classList.add('d-none');
        LoadingUI.setButton(button, true, 'Saving…');
        try {
            const response = await fetch('../../controllers/patientController.php', { method: 'POST', body: new FormData(this) });
            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'Unable to save the form.');
            window.showToast(result.message, true);
            back();
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove('d-none');
            LoadingUI.setButton(button, false);
        }
    });
})();
</script>
