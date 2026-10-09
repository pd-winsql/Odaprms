<?php
// Render the actual queue markup with fictional data; no database is accessed.
$source = file_get_contents(__DIR__ . '/../apps/views/admin/partials/dashboard-content.php');
$functionStart = strpos($source, 'function dashboardQueueState(');
$functionEnd = strpos($source, 'function dashboardBillingPayload(', $functionStart);
function dashboardStatusClass($status) { return 'vd-status vd-status-' . strtolower(str_replace(' ', '-', $status)); }
eval(substr($source, $functionStart, $functionEnd - $functionStart));
$isAdminQueueView = ($argv[1] ?? '') !== 'assistant';
$csrfToken = 'qa-token';
$activeQueueEntries = [];
for ($i = 1; $i <= 12; $i++) {
    $activeQueueEntries[] = [
        'appointment_id' => $i, 'patient_id' => $i,
        'appointment_status' => 'In Progress', 'is_in_treatment' => true,
        'is_next' => false, 'queue_position' => null, 'queue_status' => null,
        'checkin_status' => 'Ready', 'checkin_id' => $i,
        'date' => date('Y-m-d'), 'start_time' => '08:00:00', 'end_time' => '17:00:00',
        'serve_next_at' => null, 'firstname' => 'Julian', 'lastname' => 'Torres ' . $i,
        'email' => 'julian.torres.long.email.address@example.invalid',
        'arrived_at' => date('Y-m-d') . ' 08:05:00',
        'service_name' => 'Restoration (Fillings), Periapical X-ray',
        'clinic_name' => 'Tuguegarao Branch', 'checked_in_by' => 'Demo assistant',
    ];
}
$start = strpos($source, '<div class="vd-appt-table-wrap">');
$end = strpos($source, '</table>', $start) + strlen('</table>');
echo '<div class="vd-dash-card"><div class="vd-dash-card-body' . ($isAdminQueueView ? ' vd-admin-queue' : '') . '">';
eval('?>' . substr($source, $start, $end - $start));
echo '</div></div></div>';
