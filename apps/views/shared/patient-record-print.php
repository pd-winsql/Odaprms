<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    http_response_code(403);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><p>Unauthorized.</p></body></html>';
    exit;
}

require_once __DIR__ . '/../../../config/conn.php';
require_once __DIR__ . '/../../models/patientPrintModel.php';
require_once __DIR__ . '/../../helpers/siteBranding.php';
require_once __DIR__ . '/../../helpers/csrf.php';

function recordEscape(mixed $value, string $fallback = '—'): string
{
    $value = trim((string) ($value ?? ''));
    return htmlspecialchars($value !== '' ? $value : $fallback, ENT_QUOTES, 'UTF-8');
}

function recordDate(mixed $value, string $fallback = '—'): string
{
    if (empty($value)) return $fallback;
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('M j, Y', $timestamp) : $fallback;
}

function recordMoney(mixed $value): string
{
    return '₱' . number_format((float) $value, 2);
}

function recordAnswer(mixed $value, string $expected): string
{
    if ($value === null || $value === '') return '□';
    $isYes = (int) $value === 1;
    return (($expected === 'yes') === $isYes) ? '■' : '□';
}

function recordHasCondition(array $selected, string $condition): bool
{
    return in_array(mb_strtolower(trim($condition)), $selected, true);
}

function renderRecordField(string $label, mixed $value, string $class = ''): void
{
    echo '<div class="vd-print-field ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"><span>'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><strong>' . recordEscape($value) . '</strong></div>';
}

function renderRecordArch(array $teeth, array $findings, string $class = ''): void
{
    echo '<div class="vd-print-arch ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">';
    foreach ($teeth as $tooth) {
        $codes = [];
        foreach ($findings[$tooth] ?? [] as $finding) {
            $codes[] = trim((string) ($finding['finding_code'] ?? ''));
        }
        $codes = array_values(array_unique(array_filter($codes)));
        $codeText = $codes ? implode(' · ', $codes) : '&nbsp;';
        echo '<div class="vd-print-tooth"><span>' . htmlspecialchars($tooth) . '</span>';
        echo '<svg viewBox="0 0 48 48" aria-label="Tooth ' . htmlspecialchars($tooth) . '"><path d="M13 5C8 7 5 13 6 20c1 8 4 15 7 20 3 5 7 4 11 4s8 1 11-4c3-5 6-12 7-20 1-7-2-13-7-15-4-2-7 1-11 1s-7-3-11-1Z"/><path d="M17 16h14v16H17z"/><path d="M8 12l9 4m14 0 9-4M8 36l9-4m14 0 9 4"/></svg>';
        echo '<b>' . $codeText . '</b></div>';
    }
    echo '</div>';
}

$patientId = (int) ($_GET['patient_id'] ?? 0);

try {
    $conn = (new Database())->connect();
    if (!$conn) throw new RuntimeException('Database connection unavailable.');

    $record = (new PatientPrintModel($conn))->getRecord($patientId);
    $branding = vdLoadSiteBranding($conn);
} catch (InvalidArgumentException $e) {
    http_response_code(404);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Patient record unavailable</title></head><body><p>'
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    exit;
} catch (Throwable $e) {
    error_log('patient record print error: ' . $e->getMessage());
    http_response_code(500);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Patient record unavailable</title></head><body><p>Unable to prepare the patient record.</p></body></html>';
    exit;
}

$patient = $record['patient'];
$chart = $record['chart'];
$allLedgerRows = $record['ledger'];
$ledgerRows = array_slice($allLedgerRows, 0, 12);
$clinics = $record['clinics'];
$csrfToken = get_csrf_token();
$dashboardUrl = ($_SESSION['user_role'] ?? '') === 'Admin'
    ? vdAppUrl('apps/views/admin/dashboard.php')
    : vdAppUrl('apps/views/dental_asst/dashboard.php');

$fullName = trim(implode(' ', array_filter([
    $patient['firstname'] ?? '',
    $patient['middlename'] ?? '',
    $patient['lastname'] ?? '',
    $patient['suffix'] ?? '',
])));
$clinicSummary = implode(' · ', array_map(static fn(array $clinic): string => trim((string) ($clinic['clinic_name'] ?? '')), $clinics));
$clinicAddresses = implode(' · ', array_filter(array_map(static fn(array $clinic): string => trim((string) ($clinic['clinic_address'] ?? '')), $clinics)));
$logoFilename = vdSiteLogoFilename($branding);

$conditionGroups = require __DIR__ . '/../../../config/medicalConditions.php';
$selectedConditions = array_map(
    static fn(string $condition): string => mb_strtolower(trim($condition)),
    array_filter(explode(', ', (string) ($patient['patient_conditions'] ?? '')))
);

$findingsByTooth = [];
foreach ((array) ($chart['teeth'] ?? []) as $finding) {
    $tooth = (string) ($finding['tooth_number'] ?? '');
    if ($tooth !== '') $findingsByTooth[$tooth][] = $finding;
}

$permanentUpper = ['18','17','16','15','14','13','12','11','21','22','23','24','25','26','27','28'];
$permanentLower = ['48','47','46','45','44','43','42','41','31','32','33','34','35','36','37','38'];
$primaryUpper = ['55','54','53','52','51','61','62','63','64','65'];
$primaryLower = ['85','84','83','82','81','71','72','73','74','75'];
$healthQuestions = [
    ['good_health', 'Are you in good health?', null],
    ['medical_condition', 'Are you under a medical condition right now?', 'medical_condition_detail'],
    ['serious_illness', 'Have you had a serious illness or surgical operation?', 'serious_illness_detail'],
    ['hospitalized', 'Have you ever been hospitalized?', 'hospitalized_detail'],
    ['medication', 'Are you taking any medication?', 'medication_detail'],
    ['smoke', 'Do you smoke?', null],
    ['alcohol', 'Do you use alcohol?', null],
    ['drugs', 'Do you use drugs?', null],
    ['allergy', 'Allergic to anesthetics, latex, penicillin, aspirin, or others?', 'allergy_detail'],
    ['pregnant', 'For women: Are you pregnant?', null],
    ['nursing', 'For women: Are you nursing?', null],
    ['birth_control', 'For women: Are you taking birth control pills?', null],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Dental Record · <?= recordEscape($fullName, 'Patient') ?></title>
    <link rel="stylesheet" href="../../../public/css/patient-record-print.css?v=<?= filemtime(__DIR__ . '/../../../public/css/patient-record-print.css') ?>">
</head>
<body>
    <header class="vd-print-toolbar" aria-label="Print controls">
        <div>
            <strong>Patient dental record</strong>
            <span>Letter portrait · 2 pages · Print double-sided, flip on long edge</span>
        </div>
        <div class="vd-print-toolbar-actions">
            <button type="button" class="vd-print-button vd-print-button-secondary" id="closePrintPreview">Close preview</button>
            <button type="button" class="vd-print-button" id="printPatientRecord">Print record</button>
        </div>
    </header>

    <main class="vd-print-document">
        <section class="vd-print-page vd-print-front" aria-label="Patient information, medical history, and consent">
            <header class="vd-print-clinic-header">
                <div class="vd-print-brand">
                    <?php if ($logoFilename !== ''): ?>
                        <img src="../../../public/assets/<?= rawurlencode($logoFilename) ?>" alt="<?= recordEscape(vdBrandFullName($branding)) ?>">
                    <?php else: ?>
                        <span><?= recordEscape($branding['brand_name_top'] ?? 'Dr. Aprille') ?></span>
                        <strong>VENTURA</strong>
                        <small><?= recordEscape($branding['brand_name_sub'] ?? 'Clinica Dental') ?></small>
                    <?php endif; ?>
                </div>
                <div class="vd-print-clinic-details">
                    <h1>Dr. Aprille Cabayu Ventura</h1>
                    <p><?= recordEscape($clinicSummary, 'Dental clinic') ?></p>
                    <p><?= recordEscape($clinicAddresses, 'Clinic address') ?></p>
                    <p>Patient dental record · By appointment</p>
                </div>
                <div class="vd-print-issued"><span>Printed</span><strong><?= date('M j, Y') ?></strong></div>
            </header>

            <div class="vd-print-identity-grid">
                <?php renderRecordField('Last name', $patient['lastname'] ?? null); ?>
                <?php renderRecordField('First name', $patient['firstname'] ?? null); ?>
                <?php renderRecordField('Middle name', $patient['middlename'] ?? null); ?>
                <?php renderRecordField('Birthdate', recordDate($patient['birthdate'] ?? null)); ?>
                <?php renderRecordField('Age', $patient['age'] ?? null); ?>
                <?php renderRecordField('Sex', $patient['gender'] ?? null); ?>
                <?php renderRecordField('Mobile number', $patient['phone_number'] ?? null); ?>
                <?php renderRecordField('Email address', $patient['email'] ?? null, 'vd-print-span-2'); ?>
                <?php renderRecordField('Civil status', $patient['civil_status'] ?? null); ?>
                <?php renderRecordField('Home address', $patient['home_address'] ?? null, 'vd-print-span-2'); ?>
                <?php renderRecordField('FB account', $patient['fb_account'] ?? null); ?>
                <?php renderRecordField('Work address', $patient['work_address'] ?? null, 'vd-print-span-2'); ?>
                <?php renderRecordField('Occupation', $patient['occupation'] ?? null); ?>
                <?php renderRecordField('Office contact', $patient['office_contact'] ?? null); ?>
            </div>

            <section class="vd-print-compact-section">
                <h2>Care contacts and dental history</h2>
                <div class="vd-print-identity-grid">
                    <?php renderRecordField('Parent / guardian', $patient['guardian_name'] ?? null); ?>
                    <?php renderRecordField('Guardian contact', $patient['guardian_contact'] ?? null); ?>
                    <?php renderRecordField('Physician', $patient['physician_name'] ?? null); ?>
                    <?php renderRecordField('Physician contact', $patient['physician_contact'] ?? null); ?>
                    <?php renderRecordField('Physician address', $patient['physician_address'] ?? null, 'vd-print-span-2'); ?>
                    <?php renderRecordField('Previous dentist', $patient['previous_dentist'] ?? null); ?>
                    <?php renderRecordField('Last dental visit', recordDate($patient['last_dental_visit'] ?? null)); ?>
                    <?php renderRecordField('Referred by', $patient['referred_by'] ?? null); ?>
                    <?php renderRecordField('Treatment done', $patient['treatment_done'] ?? null, 'vd-print-span-2'); ?>
                    <?php renderRecordField('Reason for visit', $patient['reason_for_visit'] ?? null); ?>
                </div>
            </section>

            <section class="vd-print-questionnaire">
                <h2>Health Questionnaire <small>Please review with the patient before treatment</small></h2>
                <table>
                    <thead><tr><th>Question</th><th>Details</th><th>Yes</th><th>No</th></tr></thead>
                    <tbody>
                    <?php foreach ($healthQuestions as [$field, $label, $detailField]): ?>
                        <tr>
                            <td><?= recordEscape($label) ?></td>
                            <td><?= $detailField ? recordEscape($patient[$detailField] ?? null) : '—' ?></td>
                            <td><?= recordAnswer($patient[$field] ?? null, 'yes') ?></td>
                            <td><?= recordAnswer($patient[$field] ?? null, 'no') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="vd-print-vitals">
                    <span>Blood type <strong><?= recordEscape($patient['blood_type'] ?? null) ?></strong></span>
                    <span>Blood pressure <strong><?= recordEscape($patient['blood_pressure'] ?? null) ?></strong></span>
                </div>
            </section>

            <section class="vd-print-conditions">
                <h2>Medical Conditions</h2>
                <div class="vd-print-condition-grid">
                    <?php foreach ($conditionGroups as $group): ?>
                        <?php foreach ($group as $condition): ?>
                            <span><?= recordHasCondition($selectedConditions, $condition) ? '■' : '□' ?> <?= recordEscape($condition) ?></span>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <span><?= !empty($patient['cond_others']) ? '■' : '□' ?> Others: <?= recordEscape($patient['cond_others'] ?? null) ?></span>
                </div>
            </section>

            <section class="vd-print-consent">
                <p>I, <strong><?= recordEscape($patient['consent_name'] ?? $fullName) ?></strong>, do hereby consent to the performance upon <strong><?= recordEscape($patient['consent_for'] ?? 'myself') ?></strong> of all dental procedures, operations, and/or treatment considered necessary to restore oral and dental health.</p>
                <p>This consent is given voluntarily. Whatever the result of any intervention or treatment may be, I absolve my dentist from liability. I understand that I am responsible for payment for services rendered to me and/or my family.</p>
                <div class="vd-print-signatures">
                    <span>Patient / representative signature</span><span>Dentist signature</span><span>Date</span>
                </div>
            </section>
            <footer class="vd-print-page-footer"><span>Patient #<?= (int) $patientId ?> · <?= recordEscape($fullName) ?></span><span>Page 1 of 2</span></footer>
        </section>

        <section class="vd-print-page vd-print-back" aria-label="Odontogram and procedure settlement ledger">
            <header class="vd-print-record-header">
                <div><span>Clinical dental record</span><h1>Odontogram &amp; Treatment History</h1></div>
                <div><strong><?= recordEscape($fullName) ?></strong><span>Patient #<?= (int) $patientId ?></span></div>
            </header>

            <section class="vd-print-chart-layout">
                <div class="vd-print-chart">
                    <div class="vd-print-orientation"><span>Patient’s right</span><span>Patient’s left</span></div>
                    <h2>Permanent teeth</h2>
                    <?php renderRecordArch($permanentUpper, $findingsByTooth); ?>
                    <div class="vd-print-midline"></div>
                    <?php renderRecordArch($permanentLower, $findingsByTooth); ?>
                    <h2>Primary teeth</h2>
                    <?php renderRecordArch($primaryUpper, $findingsByTooth, 'vd-print-primary'); ?>
                    <div class="vd-print-midline vd-print-primary-midline"></div>
                    <?php renderRecordArch($primaryLower, $findingsByTooth, 'vd-print-primary'); ?>
                </div>
                <aside class="vd-print-assessment">
                    <h2>Chart assessment</h2>
                    <dl>
                        <div><dt>Dentition</dt><dd><?= recordEscape($chart['dentition_type'] ?? null) ?></dd></div>
                        <div><dt>Periodontal</dt><dd><?= recordEscape($chart['periodontal_status'] ?? null) ?></dd></div>
                        <div><dt>Occlusion</dt><dd><?= recordEscape($chart['occlusion_class'] ?? null) ?></dd></div>
                        <div><dt>Occlusion findings</dt><dd><?= recordEscape($chart['occlusion_findings'] ?? null) ?></dd></div>
                        <div><dt>Appliances</dt><dd><?= recordEscape($chart['appliances'] ?? null) ?></dd></div>
                        <div><dt>TMD findings</dt><dd><?= recordEscape($chart['tmd_findings'] ?? null) ?></dd></div>
                        <div><dt>Clinical notes</dt><dd><?= recordEscape($chart['clinical_notes'] ?? null) ?></dd></div>
                    </dl>
                    <div class="vd-print-legend">
                        <strong>Finding codes</strong>
                        <p>D Caries · M Missing · F Filled · X Extraction · RF Root fragment · MO Missing other cause · IM Impacted</p>
                        <p>JC Jacket crown · AM Amalgam · CO Composite · AB Abutment · P Pontic · IN Inlay · S Sealant · RD Removable denture</p>
                    </div>
                </aside>
            </section>

            <section class="vd-print-ledger-section">
                <div class="vd-print-ledger-heading">
                    <div><span>Completed visits</span><h2>Procedure and Settlement Ledger</h2></div>
                    <p><?= count($allLedgerRows) ?> finalized record<?= count($allLedgerRows) === 1 ? '' : 's' ?></p>
                </div>
                <table class="vd-print-ledger">
                    <thead><tr><th>Date</th><th>Tooth no/s</th><th>Procedure</th><th>Dentist/s</th><th>Amount charge</th><th>Amount paid</th><th>Balance</th><th>Remarks</th></tr></thead>
                    <tbody>
                    <?php foreach ($ledgerRows as $row): ?>
                        <tr>
                            <td><?= recordDate($row['date'] ?? null, '') ?></td>
                            <td><?= recordEscape(implode(', ', (array) ($row['tooth_numbers'] ?? [])), '—') ?></td>
                            <td><?= recordEscape($row['procedure_name'] ?? null) ?></td>
                            <td><?= recordEscape($row['dentist_name'] ?? null) ?></td>
                            <td><?= recordMoney($row['actual_service_amount'] ?? 0) ?></td>
                            <td><?= recordMoney($row['amount_paid'] ?? 0) ?></td>
                            <td><?= recordMoney($row['outstanding_balance'] ?? 0) ?></td>
                            <td><?= recordEscape($row['notes'] ?? null, 'Paid') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php for ($blank = count($ledgerRows); $blank < 12; $blank++): ?>
                        <tr class="vd-print-ledger-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
                <?php if (count($allLedgerRows) > count($ledgerRows)): ?>
                    <p class="vd-print-continuation-note">Showing the latest <?= count($ledgerRows) ?> finalized visits. The complete treatment history remains available in the clinic system.</p>
                <?php endif; ?>
            </section>
            <footer class="vd-print-page-footer"><span>Generated from the clinic’s current patient record</span><span>Page 2 of 2</span></footer>
        </section>
    </main>

    <script>
    (() => {
        const patientId = <?= (int) $patientId ?>;
        const csrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const dashboardUrl = <?= json_encode($dashboardUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
        let auditSent = false;

        function recordPrint() {
            if (auditSent) return;
            auditSent = true;
            const form = new FormData();
            form.append('patient_id', String(patientId));
            form.append('csrf_token', csrfToken);
            navigator.sendBeacon(<?= json_encode(vdAppUrl('apps/controllers/patientRecordPrintController.php'), JSON_UNESCAPED_SLASHES) ?>, form);
        }

        document.getElementById('printPatientRecord').addEventListener('click', () => {
            recordPrint();
            window.print();
        });
        document.getElementById('closePrintPreview').addEventListener('click', () => {
            window.close();
            window.setTimeout(() => window.location.replace(dashboardUrl), 150);
        });
        window.addEventListener('beforeprint', recordPrint);
    })();
    </script>
</body>
</html>
