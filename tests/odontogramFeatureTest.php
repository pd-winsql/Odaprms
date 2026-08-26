<?php

session_save_path(sys_get_temp_dir());
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['csrf_token'] = 'test-csrf';

require_once __DIR__ . '/../apps/helpers/odontogramView.php';
require_once __DIR__ . '/../apps/models/odontogramModel.php';

function odontogramExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

ob_start();
vdRenderOdontogramWorkspace('editableChart', false, true);
$editable = ob_get_clean();
odontogramExpect(substr_count($editable, 'data-tooth=') === 52, 'The chart renders all 32 permanent and 20 primary teeth.');
odontogramExpect(str_contains($editable, 'data-dentition="Mixed"'), 'Mixed dentition can display both tooth sets.');
odontogramExpect(str_contains($editable, 'data-finding-form') && str_contains($editable, 'data-save-odontogram'), 'Admin chart renders finding and save controls.');
odontogramExpect(str_contains($editable, 'Procedure and settlement ledger'), 'The clinic treatment ledger is included.');

$model = new OdontogramModel(new PDO('sqlite::memory:'));
$normalize = new ReflectionMethod($model, 'normalizePayload');
$normalized = $normalize->invoke($model, [
    'dentition_type' => 'Mixed',
    'periodontal_status' => 'Moderate Periodontitis',
    'teeth' => [
        ['tooth_number' => '16', 'surface' => 'Occlusal', 'category' => 'Condition', 'finding_code' => 'D'],
        ['tooth_number' => '99', 'surface' => 'Whole', 'category' => 'Condition', 'finding_code' => 'D'],
        ['tooth_number' => '24', 'surface' => 'Whole', 'category' => 'Condition', 'finding_code' => 'INVALID'],
    ],
]);
odontogramExpect($normalized['dentition_type'] === 'Mixed' && count($normalized['teeth']) === 1, 'Server normalization accepts clinic codes and rejects invalid teeth or findings.');

ob_start();
vdRenderOdontogramWorkspace('readOnlyChart', true);
$readOnly = ob_get_clean();
odontogramExpect(!str_contains($readOnly, 'data-finding-form') && !str_contains($readOnly, 'data-save-odontogram'), 'Dental Assistant chart is read-only in the rendered UI.');
odontogramExpect(str_contains($readOnly, 'data-read-only="1"'), 'Read-only state is explicit for the client.');

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/apps/controllers/odontogramController.php');
odontogramExpect(str_contains($controller, "['Admin', 'Dental Assistant']"), 'Admin and Dental Assistant may read dental charts.');
odontogramExpect(str_contains($controller, "!== 'Admin'"), 'Only Admin may save a dental chart.');
odontogramExpect(str_contains($controller, 'validate_csrf'), 'Dental chart updates require CSRF validation.');

$billing = file_get_contents($root . '/apps/models/billingModel.php');
odontogramExpect(str_contains($billing, 'patient_odontogram_snapshots'), 'Final settlement enforces an appointment chart review.');
odontogramExpect(str_contains($billing, 'snap.reviewed_at >= chart.updated_at'), 'Final settlement rejects a stale chart review.');

$dashboard = file_get_contents($root . '/apps/views/admin/partials/dashboard-content.php');
odontogramExpect(str_contains($dashboard, 'finalBillingOdontogram') && str_contains($dashboard, 'odontogram:reviewed'), 'Final billing embeds and observes the dental chart review workspace.');

$migration = file_get_contents($root . '/database/migrations/20260915_add_patient_odontograms.sql');
foreach (['patient_odontograms', 'patient_odontogram_teeth', 'patient_odontogram_snapshots'] as $table) {
    odontogramExpect(str_contains($migration, $table), "Migration creates {$table}.");
}

echo "Odontogram feature test completed.\n";
