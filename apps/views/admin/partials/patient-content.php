<?php
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Auth guard
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
        echo '<div class="vd-empty-state">Unauthorized.</div>';
        exit;
    }

    require_once '../../../../config/conn.php';
    require_once '../../../models/patientModel.php';

    $db = new Database();
    $conn = $db->connect();
    $patientModel = new Patient($conn);

    $getAllPatients = $patientModel->getAllPatients();

    $todayCount    = count(array_filter($getAllPatients, function($patient) {
        return date('Y-m-d', strtotime($patient['created_at'])) === date('Y-m-d');
    }));
?>

<div class="d-flex flex-column gap-4">

<div class="vd-dash-card">
    <div class="vd-dash-card-header">
    <span class="vd-dash-card-title">Patients</span>
    <span class="vd-topbar-date"><?= count($getAllPatients) ?> total</span>
    </div>
    <div class="vd-dash-card-body">
    <?php if (empty($getAllPatients)): ?>
        <div class="vd-empty-state">No patients found.</div>
    <?php else: ?>
        <div class="vd-appt-table-wrap">
        <table class="vd-appt-table w-100">
            <thead>
            <tr>
                <th>Patient</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Phone Number</th>
                <th>Email</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($getAllPatients as $patient): ?>
                <tr data-id="<?= $patient['patient_id'] ?>">
                <td>
                    <div class="vd-appt-name"><?= htmlspecialchars($patient['lastname'] . ', ' . $patient['firstname']) ?></div>
                    <div class="vd-appt-meta"><?= htmlspecialchars($patient['email']) ?></div>
                </td>
                <td class="vd-appt-meta"><?= htmlspecialchars($patient['age']) ?></td>
                <td class="vd-appt-meta"><?= htmlspecialchars($patient['gender']) ?></td>
                <td class="vd-appt-meta"><?= htmlspecialchars($patient['phone_number']) ?></td>
                <td class="vd-appt-meta"><?= htmlspecialchars($patient['email']) ?></td>
                <td>
                    <a href="patient-profile.php?id=<?= $patient['patient_id'] ?>" class="vd-btn vd-btn-primary">View Profile</a>
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