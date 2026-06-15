<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if(!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once '../../../../config/conn.php';
require_once '../../../models/scheduleModel.php';
require_once '../../../models/clinicModel.php';

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
                        <div class="vd-sched-card <?= $isPast ? 'past' : '' ?>">
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

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="vd-modal-title mb-0">Add Schedule for <span id="modalClinicName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="add-schedule-form" class="d-flex flex-column gap-3">
            <div>
                <label class="vd-label form-label">Date</label>
                <input type="date" name="sched_date" class="form-control vd-input" min="<?= date('Y-m-d') ?>" required>
            </div>
            <input type="hidden" name="clinic_id" id="modalClinicId">
            <div>
                <label class="vd-label form-label">Max Appointments</label>
                <input type="number" name="max_appointments" class="form-control vd-input" value="8" min="1" max="50" required>
            </div>
            <div id="addError" class="text-danger small d-none"></div>
            <button type="submit" class="btn vd-btn-gold">Add Schedule</button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    document.getElementById('addScheduleModal').addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget; // the button that triggered the modal
        document.getElementById('modalClinicName').textContent = btn.dataset.clinicName;
        document.getElementById('modalClinicId').value = btn.dataset.clinicId;
    });

    document.getElementById('add-schedule-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'add_schedule');

        fetch('../../controllers/scheduleController.php', {
        method: 'POST',
        body: formData
        })        
        .then(response => response.text())
        .then(text => {
            if (text.trim() === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('addScheduleModal')).hide();
                alert('Schedule added successfully!');
                location.reload();
            } else {
                document.getElementById('addError').textContent = text;
                document.getElementById('addError').classList.remove('d-none');            }
        })
    });

    document.querySelectorAll('.vd-delete-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!confirm('Delete this schedule?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_schedule');
            formData.append('schedule_id', btn.dataset.id);

            fetch('../../controllers/scheduleController.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.text())
            .then(text => {
                if (text.trim() === 'success') {
                    document.getElementById('schedCard-' + btn.dataset.id).remove();
                    location.reload();
                } else {
                    alert('Error: ' + text);
                }
            });
        });
    });
})();
</script>