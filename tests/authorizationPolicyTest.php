<?php
require_once __DIR__ . '/../apps/helpers/authorization.php';

function policyExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$_SESSION = ['user_id' => 1, 'user_role' => 'Admin'];
policyExpect(vdIsAdmin() && !vdIsDentalAssistant() && vdCanPerformBilling(), 'Admin / Dentist has oversight and final billing permission.');

$_SESSION = ['user_id' => 2, 'user_role' => 'Dental Assistant'];
policyExpect(!vdIsAdmin() && vdIsDentalAssistant() && vdCanPerformBilling(), 'Dental Assistant has daily operations and final billing permission.');

$_SESSION = ['user_id' => 3, 'user_role' => 'Patient'];
policyExpect(!vdIsAdmin() && !vdIsDentalAssistant() && !vdCanPerformBilling(), 'Patient has neither staff operations nor final billing permission.');

$root = dirname(__DIR__);
$dailyControllers = [
    'appointmentController.php' => 'vdRequireDentalAssistantJson',
    'clinicController.php' => 'vdRequireDentalAssistantJson',
    'depositController.php' => 'vdRequireDentalAssistantJson',
    'emailNotificationController.php' => 'vdRequireDentalAssistantJson',
    'logbookController.php' => 'vdRequireDentalAssistantJson',
    'patientController.php' => 'vdRequireDentalAssistantJson',
    'scheduleController.php' => 'vdRequireDentalAssistantJson',
    'serviceController.php' => 'vdRequireDentalAssistantJson',
];
foreach ($dailyControllers as $file => $permissionCheck) {
    $controllerSource = file_get_contents($root . '/apps/controllers/' . $file);
    policyExpect(
        str_contains($controllerSource, $permissionCheck),
        "{$file} enforces Dental Assistant operations."
    );
    policyExpect(str_contains($controllerSource, 'validate_csrf'), "{$file} protects state-changing requests with CSRF validation.");
}

policyExpect(str_contains(file_get_contents($root . '/apps/controllers/billingController.php'), 'vdCanPerformBilling'), 'Final billing remains available to both staff roles.');
$staffController = file_get_contents($root . '/apps/controllers/staffController.php');
policyExpect(str_contains($staffController, 'vdRequireAdminJson'), 'Dental Assistant account management remains Admin-only.');
policyExpect(str_contains($staffController, 'validate_csrf'), 'Dental Assistant account changes require CSRF validation.');

$adminDashboard = file_get_contents($root . '/apps/views/admin/dashboard.php');
foreach (['appointment-content.php', 'services-content.php', 'clinic-content.php', 'schedule-content.php', 'patient-content.php', 'payment-review-content.php', 'logbook-content.php', 'messages-content.php'] as $operationalPage) {
    policyExpect(!str_contains($adminDashboard, 'data-page="' . $operationalPage . '"'), "Admin navigation excludes {$operationalPage}.");
}
foreach (['dashboard-content.php', 'upcoming-appointments-content.php', 'den-assist-content.php', 'insights-content.php', 'activity-logs-content.php', 'siteSettings-content.php'] as $oversightPage) {
    policyExpect(str_contains($adminDashboard, 'data-page="' . $oversightPage . '"'), "Admin navigation includes {$oversightPage}.");
}

$assistantDashboard = file_get_contents($root . '/apps/views/dental_asst/dashboard.php');
foreach (['appointment-content.php', 'messages-content.php', 'services-content.php', 'clinic-content.php', 'schedule-content.php', 'patient-content.php', 'payment-review-content.php', 'cash-billing-content.php', 'logbook-content.php'] as $operationalPage) {
    policyExpect(str_contains($assistantDashboard, 'data-page="' . $operationalPage . '"'), "Dental Assistant navigation includes {$operationalPage}.");
}
policyExpect(!str_contains($assistantDashboard, 'data-page="siteSettings-content.php"'), 'Schedule defaults are merged into Schedules instead of a separate assistant module.');

$auditCoverage = [
    'clinicController.php' => ['clinic_created', 'clinic_updated'],
    'scheduleController.php' => ['schedule_created', 'schedule_deleted', 'schedule_updated'],
    'serviceController.php' => ['service_category_created', 'service_created', 'service_updated'],
    'siteSettingsController.php' => ['schedule_defaults_updated', 'settings_updated', 'gcash_qr_updated'],
    'staffController.php' => ['staff_account_created', 'staff_account_updated', 'staff_status_updated'],
];
foreach ($auditCoverage as $file => $actions) {
    $source = file_get_contents($root . '/apps/controllers/' . $file);
    foreach ($actions as $action) policyExpect(str_contains($source, $action), "{$action} is included in the activity audit trail.");
}
$depositSource = file_get_contents($root . '/apps/models/depositModel.php');
foreach (['deposit_transferred_out', 'deposit_transferred', 'deposit_refunded'] as $action) {
    policyExpect(str_contains($depositSource, $action), "{$action} is included in the activity audit trail.");
}

$activityLogPage = file_get_contents($root . '/apps/views/admin/partials/activity-logs-content.php');
policyExpect(str_contains($activityLogPage, "!== 'Admin'") && !str_contains($activityLogPage, '<form'), 'Activity Logs are Admin-only and read-only.');

echo "Authorization policy test completed.\n";
