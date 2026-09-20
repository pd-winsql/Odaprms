<?php

$root = dirname(__DIR__);
$viewPath = $root . '/apps/views/shared/patient-record-print.php';
$cssPath = $root . '/public/css/patient-record-print.css';
$modelPath = $root . '/apps/models/patientPrintModel.php';
$auditPath = $root . '/apps/controllers/patientRecordPrintController.php';
$profilePath = $root . '/apps/views/admin/partials/_patient-profie.php';
$patientsPath = $root . '/apps/views/admin/partials/patient-content.php';

function printRecordExpect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

foreach ([$viewPath, $cssPath, $modelPath, $auditPath] as $file) {
    printRecordExpect(is_file($file), basename($file) . ' exists.');
}

$view = file_get_contents($viewPath);
$css = file_get_contents($cssPath);
$model = file_get_contents($modelPath);
$audit = file_get_contents($auditPath);
$profile = file_get_contents($profilePath);
$patients = file_get_contents($patientsPath);

printRecordExpect(str_contains($view, "['Admin', 'Dental Assistant']"), 'Only Admin and Dental Assistant roles can open the print view.');
printRecordExpect(!preg_match('~https?://~i', $view), 'The print view has no external runtime asset dependency.');
printRecordExpect(str_contains($css, '@page { size: Letter portrait; margin: 0; }'), 'The print stylesheet targets Letter portrait.');
printRecordExpect(str_contains($css, 'break-after: page'), 'The front and back have an explicit duplex page break.');
printRecordExpect(str_contains($model, 'getPatientFull') && str_contains($model, 'getChart'), 'Print data reuses the canonical patient and odontogram models.');
printRecordExpect(str_contains($view, 'array_slice($allLedgerRows, 0, 12)'), 'The paper ledger is capped at twelve finalized visits.');
printRecordExpect(str_contains($view, 'window.close()') && str_contains($view, 'window.location.replace(dashboardUrl)'), 'Close preview attempts to close the tab and has a dashboard fallback.');
printRecordExpect(str_contains($audit, 'patient_record_printed'), 'Print actions are auditable.');
printRecordExpect(str_contains($audit, "['Admin', 'Dental Assistant']"), 'The print audit endpoint enforces staff roles.');
printRecordExpect(str_contains($profile, 'Print dental record'), 'The patient profile exposes the print action to both staff dashboards.');
printRecordExpect(str_contains($patients, 'Print dental record'), 'The Dental Assistant patient list exposes the print action.');

echo "Patient record print checks completed.\n";
