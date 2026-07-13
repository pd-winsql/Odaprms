<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once '../../../../config/conn.php';
require_once '../../../models/patientModel.php';

$db   = new Database();
$conn = $db->connect();
$patientModel = new Patient($conn);

$patient_id = $_GET['id'] ?? null;
$patient    = $patientModel->getPatientFull($patient_id);

if (!$patient) {
    echo '<div class="vd-empty-state">Patient not found.</div>';
    exit;
}

// Helper: yes/no display
function yn($val) {
    if ($val === null) return '<span class="vd-profile-na">—</span>';
    return $val ? '<span class="vd-yn-yes">Yes</span>' : '<span class="vd-yn-no">No</span>';
}

// Helper: plain value display
function val($v, $fallback = '—') {
    return htmlspecialchars($v ?? $fallback);
}
?>

<!-- Back Button -->
<div class="mb-3">
    <button class="btn vd-btn-outline vd-back-btn" id="backToPatients">
        <i class="ti ti-arrow-left me-1"></i> Back to Patients
    </button>
    </div>

    <div class="d-flex flex-column gap-4">

    <!-- ── HEADER CARD ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Patient Profile</span>
        <span class="vd-topbar-date">Registered <?= date('M d, Y', strtotime($patient['created_at'])) ?></span>
        </div>
        <div class="vd-profile-header">
        <div class="vd-profile-avatar">
            <?= strtoupper(substr($patient['firstname'], 0, 1) . substr($patient['lastname'], 0, 1)) ?>
        </div>
        <div>
            <div class="vd-profile-name"><?= val($patient['full_name']) ?></div>
            <div class="vd-profile-sub">
            <?= val($patient['email']) ?> &nbsp;·&nbsp; <?= val($patient['phone_number']) ?>
            </div>
        </div>
        <?php if (!empty($patient['birthdate'])): ?>
            <span class="vd-status vd-status-confirmed ms-auto">Form Complete</span>
        <?php else: ?>
            <span class="vd-status vd-status-pending ms-auto">Form Incomplete</span>
        <?php endif; ?>
        </div>
    </div>

    <!-- ── PERSONAL INFORMATION ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Personal Information</span>
        </div>
        <div class="vd-profile-body">
        <div class="vd-profile-grid">
            <div class="vd-profile-field">
            <div class="vd-profile-label">First Name</div>
            <div class="vd-profile-value"><?= val($patient['firstname']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Middle Name</div>
            <div class="vd-profile-value"><?= val($patient['middlename']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Last Name</div>
            <div class="vd-profile-value"><?= val($patient['lastname']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Birthdate</div>
            <div class="vd-profile-value">
                <?= $patient['birthdate'] ? date('F d, Y', strtotime($patient['birthdate'])) : '—' ?>
            </div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Age</div>
            <div class="vd-profile-value"><?= val($patient['age']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Gender</div>
            <div class="vd-profile-value"><?= val($patient['gender']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Civil Status</div>
            <div class="vd-profile-value"><?= val($patient['civil_status']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Phone Number</div>
            <div class="vd-profile-value"><?= val($patient['phone_number']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Email</div>
            <div class="vd-profile-value"><?= val($patient['email']) ?></div>
            </div>
            <div class="vd-profile-field vd-profile-field-full">
            <div class="vd-profile-label">Home Address</div>
            <div class="vd-profile-value"><?= val($patient['home_address']) ?></div>
            </div>
            <div class="vd-profile-field vd-profile-field-full">
            <div class="vd-profile-label">Work Address</div>
            <div class="vd-profile-value"><?= val($patient['work_address']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Occupation</div>
            <div class="vd-profile-value"><?= val($patient['occupation']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Office Contact</div>
            <div class="vd-profile-value"><?= val($patient['office_contact']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">FB Account</div>
            <div class="vd-profile-value"><?= val($patient['fb_account']) ?></div>
            </div>
        </div>
        </div>
    </div>

    <!-- ── FOR MINORS ── -->
    <?php if (!empty($patient['guardian_name'])): ?>
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Guardian / Physician Information</span>
        </div>
        <div class="vd-profile-body">
        <div class="vd-profile-grid">
            <div class="vd-profile-field">
            <div class="vd-profile-label">Guardian Name</div>
            <div class="vd-profile-value"><?= val($patient['guardian_name']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Guardian Contact</div>
            <div class="vd-profile-value"><?= val($patient['guardian_contact']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Physician Name</div>
            <div class="vd-profile-value"><?= val($patient['physician_name']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Physician Contact</div>
            <div class="vd-profile-value"><?= val($patient['physician_contact']) ?></div>
            </div>
            <div class="vd-profile-field vd-profile-field-full">
            <div class="vd-profile-label">Physician Address</div>
            <div class="vd-profile-value"><?= val($patient['physician_address']) ?></div>
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── DENTAL HISTORY ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Dental History</span>
        </div>
        <div class="vd-profile-body">
        <div class="vd-profile-grid">
            <div class="vd-profile-field">
            <div class="vd-profile-label">Previous Dentist</div>
            <div class="vd-profile-value"><?= val($patient['previous_dentist']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Last Dental Visit</div>
            <div class="vd-profile-value">
                <?= $patient['last_dental_visit'] ? date('F d, Y', strtotime($patient['last_dental_visit'])) : '—' ?>
            </div>
            </div>
            <div class="vd-profile-field vd-profile-field-full">
            <div class="vd-profile-label">Treatment Done</div>
            <div class="vd-profile-value"><?= val($patient['treatment_done']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Reason for Visit</div>
            <div class="vd-profile-value"><?= val($patient['reason_for_visit']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Referred By</div>
            <div class="vd-profile-value"><?= val($patient['referred_by']) ?></div>
            </div>
        </div>
        </div>
    </div>

    <!-- ── HEALTH QUESTIONNAIRE ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Health Questionnaire</span>
        </div>
        <div class="vd-profile-body">
        <table class="vd-profile-hq-table w-100">
            <thead>
            <tr>
                <th>Question</th>
                <th class="text-center">Answer</th>
                <th>Details</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>In good health?</td>
                <td class="text-center"><?= yn($patient['good_health']) ?></td>
                <td>—</td>
            </tr>
            <tr>
                <td>Under medical condition?</td>
                <td class="text-center"><?= yn($patient['medical_condition']) ?></td>
                <td><?= val($patient['medical_condition_detail']) ?></td>
            </tr>
            <tr>
                <td>Serious illness or surgical operation?</td>
                <td class="text-center"><?= yn($patient['serious_illness']) ?></td>
                <td><?= val($patient['serious_illness_detail']) ?></td>
            </tr>
            <tr>
                <td>Ever been hospitalized?</td>
                <td class="text-center"><?= yn($patient['hospitalized']) ?></td>
                <td><?= val($patient['hospitalized_detail']) ?></td>
            </tr>
            <tr>
                <td>Taking any medication?</td>
                <td class="text-center"><?= yn($patient['medication']) ?></td>
                <td><?= val($patient['medication_detail']) ?></td>
            </tr>
            <tr>
                <td>Smokes?</td>
                <td class="text-center"><?= yn($patient['smoke']) ?></td>
                <td>—</td>
            </tr>
            <tr>
                <td>Uses alcohol?</td>
                <td class="text-center"><?= yn($patient['alcohol']) ?></td>
                <td>—</td>
            </tr>
            <tr>
                <td>Uses drugs?</td>
                <td class="text-center"><?= yn($patient['drugs']) ?></td>
                <td>—</td>
            </tr>
            <tr>
                <td>Allergic to any substance?</td>
                <td class="text-center"><?= yn($patient['allergy']) ?></td>
                <td><?= val($patient['allergy_detail']) ?></td>
            </tr>
            <tr class="vd-hq-section-row">
                <td colspan="3">For Women Only</td>
            </tr>
            <tr>
                <td>Pregnant?</td>
                <td class="text-center"><?= yn($patient['pregnant']) ?></td>
                <td>—</td>
            </tr>
            <tr>
                <td>Nursing?</td>
                <td class="text-center"><?= yn($patient['nursing']) ?></td>
                <td>—</td>
            </tr>
            <tr>
                <td>Taking birth control pills?</td>
                <td class="text-center"><?= yn($patient['birth_control']) ?></td>
                <td>—</td>
            </tr>
            </tbody>
        </table>

        <!-- Blood info -->
        <div class="vd-profile-grid mt-3">
            <div class="vd-profile-field">
            <div class="vd-profile-label">Blood Type</div>
            <div class="vd-profile-value"><?= val($patient['blood_type']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Blood Pressure</div>
            <div class="vd-profile-value"><?= val($patient['blood_pressure']) ?></div>
            </div>
        </div>
        </div>
    </div>

    <!-- ── CONDITIONS ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Medical Conditions</span>
        </div>
        <div class="vd-profile-body">
        <?php if (!empty($patient['patient_conditions'])): ?>
            <div class="vd-conditions-wrap">
            <?php foreach (explode(', ', $patient['patient_conditions']) as $cond): ?>
                <span class="vd-condition-tag"><?= htmlspecialchars(trim($cond)) ?></span>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="vd-profile-na">No conditions recorded.</div>
        <?php endif; ?>
        </div>
    </div>

    <!-- ── CONSENT ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Consent</span>
        </div>
        <div class="vd-profile-body">
        <div class="vd-profile-grid">
            <div class="vd-profile-field">
            <div class="vd-profile-label">Consent Name</div>
            <div class="vd-profile-value"><?= val($patient['consent_name']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Consent For</div>
            <div class="vd-profile-value"><?= val($patient['consent_for']) ?></div>
            </div>
            <div class="vd-profile-field">
            <div class="vd-profile-label">Date</div>
            <div class="vd-profile-value">
                <?= $patient['consent_date'] ? date('F d, Y', strtotime($patient['consent_date'])) : '—' ?>
            </div>
            </div>
        </div>
        </div>
    </div>

</div>

<script>
(function () {
    document.getElementById('backToPatients').addEventListener('click', function () {
        // Simulate clicking the Patients nav item to load patient-content.php
        const patientNav = document.querySelector('[data-page="patient-content.php"]');
        if (patientNav) patientNav.click();
    });
    })();
</script>