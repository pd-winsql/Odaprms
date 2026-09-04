<?php

function patientNotificationExpect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$dashboard = file_get_contents($root . '/apps/views/patient/dashboard.php');
$markup = file_get_contents($root . '/apps/views/shared/patient-notification-center.php');
$script = file_get_contents($root . '/public/js/patient-appointment-notifications.js');
$controller = file_get_contents($root . '/apps/controllers/appointmentController.php');
$model = file_get_contents($root . '/apps/models/appointmentModel.php');

patientNotificationExpect(
    str_contains($dashboard, 'shared/patient-notification-center.php')
        && str_contains($dashboard, 'patient-appointment-notifications.js')
        && str_contains($dashboard, 'PatientAppointmentNotifications?.create')
        && !str_contains($dashboard, '<span class="vd-topbar-bell"><i class="ti ti-bell"></i>'),
    'Patient dashboard loads the interactive notification center.'
);

patientNotificationExpect(
    str_contains($markup, '<button type="button" class="vd-topbar-bell"')
        && str_contains($markup, 'aria-controls="patientNotificationPanel"')
        && str_contains($markup, 'patientNotificationMarkAll')
        && str_contains($markup, 'patientNotificationDot" hidden'),
    'Patient bell exposes accessible panel controls and a conditional unread dot.'
);

foreach (['deposit_required', 'payment_rejected', 'appointment_confirmed', 'appointment_rejected', 'appointment_cancelled'] as $type) {
    patientNotificationExpect(str_contains($script, $type), "Patient notification script handles {$type}.");
}

patientNotificationExpect(
    str_contains($script, 'window.localStorage')
        && str_contains($script, 'visibilitychange')
        && str_contains($script, 'pollInterval')
        && str_contains($script, 'billing-content.php')
        && str_contains($script, 'history-content.php'),
    'Patient updates persist locally, refresh in the background, and deep-link to existing pages.'
);

patientNotificationExpect(
    str_contains($controller, 'patientNotificationSnapshot')
        && str_contains($controller, "(\$_SESSION['user_role'] ?? '') !== 'Patient'")
        && str_contains($model, 'WHERE p.user_id = :user_id'),
    'Snapshot endpoint is restricted to the signed-in patient account.'
);

echo "Patient notification test completed.\n";
