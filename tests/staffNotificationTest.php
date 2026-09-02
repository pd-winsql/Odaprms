<?php

function notificationExpect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$dashboards = [
    'Admin' => $root . '/apps/views/admin/dashboard.php',
    'Dental Assistant' => $root . '/apps/views/dental_asst/dashboard.php',
];

foreach ($dashboards as $role => $path) {
    $dashboard = file_get_contents($path);
    notificationExpect(
        str_contains($dashboard, "shared/staff-notification-center.php")
            && str_contains($dashboard, 'staff-appointment-notifications.js')
            && str_contains($dashboard, 'StaffAppointmentNotifications?.create')
            && str_contains($dashboard, 'appointmentNotificationCenter?.observe'),
        "{$role} dashboard loads and observes the shared appointment notification center."
    );
    notificationExpect(
        !str_contains($dashboard, 'A new appointment was added. The list has been updated.')
            && !str_contains($dashboard, 'A deposit record changed. The list has been updated.'),
        "{$role} dashboard no longer uses background-update toasts."
    );
}

$markup = file_get_contents($root . '/apps/views/shared/staff-notification-center.php');
notificationExpect(
    str_contains($markup, '<button type="button" class="vd-topbar-bell"')
        && str_contains($markup, 'aria-controls="staffNotificationPanel"')
        && str_contains($markup, 'aria-expanded="false"')
        && str_contains($markup, 'staffNotificationMarkAll')
        && str_contains($markup, 'Mark all as read')
        && str_contains($markup, 'staffNotificationCaughtUp'),
    'Notification bell markup exposes accessible panel controls.'
);

$script = file_get_contents($root . '/public/js/staff-appointment-notifications.js');
notificationExpect(
    str_contains($script, "appointment_created")
        && str_contains($script, "deposit_updated")
        && str_contains($script, 'window.localStorage')
        && str_contains($script, 'vd-notification-unread-indicator')
        && str_contains($script, "list.querySelector('.vd-notification-item')")
        && str_contains($script, 'firstItem.focus()')
        && str_contains($script, 'window.StaffAppointmentNotifications = { create }'),
    'Shared notification behavior supports both feed events and browser-local persistence.'
);

echo "Staff notification test completed.\n";
