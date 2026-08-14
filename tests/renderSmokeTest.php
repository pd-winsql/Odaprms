<?php

$case = $argv[1] ?? '';
session_save_path(sys_get_temp_dir());
session_start();
$patientCase = str_starts_with($case, 'patient-');
$dentalCase = str_starts_with($case, 'dental-');
$_SESSION['user_id'] = $patientCase ? 14 : 7;
$_SESSION['user_role'] = $patientCase ? 'Patient' : ($dentalCase ? 'Dental Assistant' : 'Admin');
$_SESSION['display_name'] = $patientCase ? 'Patient' : ($dentalCase ? 'Dental Assistant' : 'Administrator');

$files = [
    'dashboard' => __DIR__ . '/../apps/views/admin/partials/dashboard-content.php',
    'payment-review' => __DIR__ . '/../apps/views/admin/partials/payment-review-content.php',
    'appointments' => __DIR__ . '/../apps/views/admin/partials/appointment-content.php',
    'dental-appointments' => __DIR__ . '/../apps/views/dental_asst/partials/appointment-content.php',
    'clinics' => __DIR__ . '/../apps/views/admin/partials/clinic-content.php',
    'schedules' => __DIR__ . '/../apps/views/admin/partials/schedule-content.php',
    'dental-schedules' => __DIR__ . '/../apps/views/dental_asst/partials/schedule-content.php',
    'historical-logbook' => __DIR__ . '/../apps/views/admin/partials/logbook-content.php',
    'analytics' => __DIR__ . '/../apps/views/admin/partials/analytics-content.php',
    'patient-billing' => __DIR__ . '/../apps/views/patient/partials/billing-content.php',
    'patient-booking' => __DIR__ . '/../apps/views/patient/partials/booking-content.php',
    'staff-patient-form' => __DIR__ . '/../apps/views/admin/partials/_patient-form.php',
    'cash-billing' => __DIR__ . '/../apps/views/admin/partials/cash-billing-content.php',
    'admin-change-password' => __DIR__ . '/../apps/views/admin/partials/change-password-content.php',
    'dental-change-password' => __DIR__ . '/../apps/views/dental_asst/partials/change-password-content.php',
    'patient-change-password' => __DIR__ . '/../apps/views/patient/partials/change-password-content.php',
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
