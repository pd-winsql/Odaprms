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
    'dental-clinics' => __DIR__ . '/../apps/views/dental_asst/partials/clinic-content.php',
    'dental-services' => __DIR__ . '/../apps/views/dental_asst/partials/services-content.php',
    'schedules' => __DIR__ . '/../apps/views/admin/partials/schedule-content.php',
    'dental-schedules' => __DIR__ . '/../apps/views/dental_asst/partials/schedule-content.php',
    'historical-logbook' => __DIR__ . '/../apps/views/admin/partials/logbook-content.php',
    'analytics' => __DIR__ . '/../apps/views/admin/partials/analytics-content.php',
    'reviews' => __DIR__ . '/../apps/views/admin/partials/reviews-content.php',
    'patient-billing' => __DIR__ . '/../apps/views/patient/partials/billing-content.php',
    'patient-booking' => __DIR__ . '/../apps/views/patient/partials/booking-content.php',
    'patient-home' => __DIR__ . '/../apps/views/patient/partials/home-content.php',
    'patient-history' => __DIR__ . '/../apps/views/patient/partials/history-content.php',
    'staff-patient-form' => __DIR__ . '/../apps/views/admin/partials/_patient-form.php',
    'staff-checkin-form' => __DIR__ . '/../apps/views/admin/partials/_patient-checkin-form.php',
    'patient-profile' => __DIR__ . '/../apps/views/patient/partials/profile-content.php',
    'cash-billing' => __DIR__ . '/../apps/views/admin/partials/cash-billing-content.php',
    'admin-change-password' => __DIR__ . '/../apps/views/admin/partials/change-password-content.php',
    'dental-change-password' => __DIR__ . '/../apps/views/dental_asst/partials/change-password-content.php',
    'patient-change-password' => __DIR__ . '/../apps/views/patient/partials/change-password-content.php',
    'settings' => __DIR__ . '/../apps/views/admin/partials/siteSettings-content.php',
    'dental-settings' => __DIR__ . '/../apps/views/dental_asst/partials/siteSettings-content.php',
];

if (!isset($files[$case])) {
    fwrite(STDERR, "Unknown smoke-test case.\n");
    exit(2);
}
if ($case === 'historical-logbook') $_GET['date'] = date('Y-m-d');
if (in_array($case, ['staff-patient-form', 'staff-checkin-form'], true)) $_GET['id'] = 14;

ob_start();
include $files[$case];
$html = ob_get_clean();
if (in_array($case, ['schedules', 'dental-schedules'], true)) {
    if (!str_contains($html, '<clock-timepicker')
        || !str_contains($html, 'precision="00:05"')
        || !str_contains($html, 'scheduleTimeAvailability')) {
        fwrite(STDERR, "Schedule management clock picker or availability feedback did not render.\n");
        exit(1);
    }
}
if (in_array($case, ['settings', 'dental-settings'], true)
    && substr_count($html, '<clock-timepicker') < 2) {
    fwrite(STDERR, "Clinic schedule default clock pickers did not render.\n");
    exit(1);
}
if ($case === 'historical-logbook'
    && (!str_contains($html, 'enable: recordDates')
        || !str_contains($html, 'historicalLogbookDateValue')
        || str_contains($html, 'type="date"'))) {
    fwrite(STDERR, "The historical logbook did not render its record-aware date picker.\n");
    exit(1);
}
if ($case === 'reviews'
    && (!str_contains($html, 'Visit Rating &amp; Feedback')
        || !str_contains($html, 'Recent patient feedback'))) {
    fwrite(STDERR, "The Admin patient feedback overview did not render.\n");
    exit(1);
}
if ($case === 'patient-history'
    && (!str_contains($html, 'visitRatingModal')
        || !str_contains($html, 'Overall visit rating')
        || !str_contains($html, 'vd-history-column-head')
        || !str_contains($html, 'Review &amp; actions')
        || !str_contains($html, 'vd-history-status')
        || !str_contains($html, 'vd-history-review-slot')
        || !str_contains($html, 'vd-history-details-slot'))) {
    fwrite(STDERR, "The patient visit-rating interface did not render.\n");
    exit(1);
}
if ($case === 'dental-settings') {
    if (!str_contains($html, 'Clinic Schedule Defaults') || str_contains($html, 'Brand &amp; Logo')) {
        fwrite(STDERR, "Dental Assistant schedule-settings access was not correctly scoped.\n");
        exit(1);
    }
    echo "PASS: Dental Assistant can access schedule defaults without admin-only settings.\n";
    exit(0);
}
if (strlen($html) < 300) {
    fwrite(STDERR, "Rendered output was unexpectedly short for {$case}.\n");
    exit(1);
}
echo "PASS: {$case} rendered without a fatal error.\n";
