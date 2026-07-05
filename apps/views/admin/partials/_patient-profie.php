<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once '../../../../config/conn.php';
require_once '../../../models/patientModel.php';

$db   = new Database();
$conn = $db->connect();
$patientModel = new Patient($conn);

$patient = $patientModel->getPatient($_GET['id'] ?? null);
?>

<div class="vd-dash-card">
    <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Patient Profile</span>
    </div>

    <?php if ($patient): ?>
        <div class="vd-dash-card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars($patient['firstname'] . ' ' . $patient['lastname']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($patient['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($patient['phone']) ?></p>
            <p><strong>Created At:</strong> <?= htmlspecialchars($patient['created_at']) ?></p>
        </div>
    <?php else: ?>
        <div class="vd-dash-card-body">
            <p>Patient not found.</p>
        </div>
    <?php endif; ?>
</div>