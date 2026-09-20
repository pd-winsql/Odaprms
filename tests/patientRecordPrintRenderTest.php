<?php

putenv('APP_BASE_URL=/Capstone%20System');
require_once __DIR__ . '/../config/conn.php';

$conn = (new Database())->connect();
if (!$conn) {
    fwrite(STDERR, "SKIP: Database connection unavailable.\n");
    exit(0);
}

$adminId = (int) $conn->query("SELECT id FROM users WHERE user_role = 'Admin' ORDER BY id LIMIT 1")->fetchColumn();
$patientId = (int) $conn->query('SELECT patient_id FROM patients ORDER BY profile_completed_at IS NULL, patient_id LIMIT 1')->fetchColumn();
if ($adminId < 1 || $patientId < 1) {
    fwrite(STDERR, "SKIP: An Admin and patient fixture are required.\n");
    exit(0);
}

ini_set('session.save_path', sys_get_temp_dir());
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = $adminId;
$_SESSION['user_role'] = 'Admin';
$_GET['patient_id'] = $patientId;

ob_start();
require __DIR__ . '/../apps/views/shared/patient-record-print.php';
$html = ob_get_clean();

function printRenderExpect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

printRenderExpect(substr_count($html, 'class="vd-print-page ') === 2, 'The runtime view renders exactly two paper sides.');
printRenderExpect(str_contains($html, 'Health Questionnaire'), 'The front includes the health questionnaire.');
printRenderExpect(str_contains($html, 'Medical Conditions'), 'The front includes medical conditions.');
printRenderExpect(str_contains($html, 'Odontogram &amp; Treatment History'), 'The back includes the odontogram.');
printRenderExpect(str_contains($html, 'Procedure and Settlement Ledger'), 'The back includes the settlement ledger.');
printRenderExpect(str_contains($html, 'Print double-sided, flip on long edge'), 'The preview explains the duplex printer setting.');
printRenderExpect(
    str_contains($html, vdAppUrl('apps/views/admin/dashboard.php')),
    'An Admin preview has a safe dashboard fallback when tab closing is blocked.'
);
printRenderExpect(!preg_match('~<script[^>]+src=~i', $html), 'The print workflow has no third-party script dependency.');

session_destroy();
echo "Patient record runtime render checks completed.\n";
