<?php
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Auth guard
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
        echo '<div class="vd-empty-state">Unauthorized.</div>';
        exit;
    }

    require_once '../../../../config/conn.php';
    require_once '../../../models/appointmentModel.php';
?>

<div class="d-flex flex-column gap-4">

<div class="vd-dash-card">
    <div class="vd-dash-card-header">
    <span class="vd-dash-card-title">Upcoming Appointments</span>
    <span class="vd-topbar-date"><?= count($upcoming) ?> total</span>
    </div>
    <div class="vd-dash-card-body">
    <?php if (empty($upcoming)): ?>
        <div class="vd-empty-state">No upcoming appointments found.</div>
    <?php else: ?>
        <div class="vd-appt-table-wrap">
        <table class="vd-appt-table w-100">
            <thead>
            <tr>
                <th>Patient</th>
                <th>Service</th>
                <th>Clinic</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($upcoming as $appt): ?>
                <tr data-id="<?= $appt['appointment_id'] ?>">
                <td>
                    <div class="vd-appt-name"><?= htmlspecialchars($appt['lastname'] . ', ' . $appt['firstname']) ?></div>
                    <div class="vd-appt-meta"><?= htmlspecialchars($appt['email']) ?></div>
                </td>
                <td class="vd-appt-meta"><?= htmlspecialchars($appt['service']) ?></td>
                <td class="vd-appt-meta"><?= htmlspecialchars($appt['clinic_name']) ?></td>
                <td class="vd-appt-meta"><?= date('M d, Y', strtotime($appt['date'])) ?></td>
                <td><span class="<?= statusClass($appt['status']) ?>"><?= htmlspecialchars($appt['status']) ?></span></td>
                <td>
                    <select class="vd-status-select" data-id="<?= $appt['appointment_id'] ?>">
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $appt['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                    </select>
                </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    </div>
</div>

</div>