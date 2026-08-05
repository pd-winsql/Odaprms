<?php

$case = $argv[1] ?? '';
session_save_path(sys_get_temp_dir());
session_start();
$_SESSION['user_id'] = $case === 'patient-billing' ? 14 : 7;
$_SESSION['user_role'] = $case === 'patient-billing' ? 'Patient' : 'Admin';
$_SESSION['username'] = $case === 'patient-billing' ? 'patient' : 'admin';

$files = [
    'dashboard' => __DIR__ . '/../apps/views/admin/partials/dashboard-content.php',
    'payment-review' => __DIR__ . '/../apps/views/admin/partials/payment-review-content.php',
    'historical-logbook' => __DIR__ . '/../apps/views/admin/partials/logbook-content.php',
    'patient-billing' => __DIR__ . '/../apps/views/patient/partials/billing-content.php',
    'staff-patient-form' => __DIR__ . '/../apps/views/admin/partials/_patient-form.php',
];

if (!isset($files[$case])) {
    fwrite(STDERR, "Unknown smoke-test case.\n");
    exit(2);
}
if ($case === 'historical-logbook') $_GET['date'] = date('Y-m-d');
if ($case === 'staff-patient-form') $_GET['id'] = 14;

ob_start();
include $files[$case];
$html = ob_get_clean();
if (strlen($html) < 300) {
    fwrite(STDERR, "Rendered output was unexpectedly short for {$case}.\n");
    exit(1);
}
echo "PASS: {$case} rendered without a fatal error.\n";
