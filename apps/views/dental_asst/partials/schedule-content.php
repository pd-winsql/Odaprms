<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if(!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/scheduleModel.php';
require_once __DIR__ . '/../../../models/clinicModel.php';

$db = new Database();
$conn = $db->connect();
$scheduleModel = new Schedule($conn);
$clinicModel = new Clinic($conn);
$clinics = $clinicModel->getAllClinics();
?>

<div class="vd-content">
        <div class="d-flex flex-column gap-4">

        <!-- Schedule Overview -->
        <?php foreach ($clinics as $clinic): 
            $schedules = $scheduleModel->getUpcomingSchedulesByClinic($clinic['clinic_id']);
        ?>
        <div class="vd-dash-card">
            <div class="vd-dash-card-header">
                <span class="vd-dash-card-title">
                    <i class="ti ti-building me-1"></i>
                    <?= htmlspecialchars($clinic['clinic_name']) ?>
                </span>
                <button class="btn vd-btn-gold btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#addScheduleModal"
                    data-clinic-id="<?= $clinic['clinic_id'] ?>"
                    data-clinic-name="<?= htmlspecialchars($clinic['clinic_name']) ?>">
                    <i class="ti ti-plus me-1"></i> Add Schedule
                </button>
            </div>
            <div class="vd-dash-card-body">
                <?php if (empty($schedules)): ?>
                    <div class="vd-empty-state">No schedules yet.</div>
                <?php else: ?>
                    <div class="vd-sched-grid">
                        <?php foreach ($schedules as $sched): 
                            $d      = new DateTime($sched['sched_date']);
                            $isPast = $d < new DateTime('today');
                        ?>
                        <div class="vd-sched-card <?= $isPast ? 'past' : '' ?>" 
                        id="schedCard-<?= $sched['schedule_id'] ?>">
                            <div class="vd-sched-date">
                                <span class="vd-sched-dayname"><?= $d->format('D') ?></span>
                                <span class="vd-sched-daynum"><?= $d->format('d') ?></span>
                                <span class="vd-sched-month"><?= $d->format('M Y') ?></span>
                            </div>
                            <span class="vd-sched-slots"><?= $sched['max_appointments'] ?> slots</span>
                            <?php if (!$isPast): ?>
                            <div class="vd-sched-actions">
                                <button class="vd-sched-btn vd-delete-btn"
                                    data-id="<?= $sched['schedule_id'] ?>">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        </div>
</div>

<!-- Modal included from separate partial file -->
<?php include '_add-schedule-modal.php'; ?>