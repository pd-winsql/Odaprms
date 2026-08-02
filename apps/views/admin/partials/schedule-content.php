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

                            <!-- Default view -->
                            <div class="vd-sched-card-view">
                                <div class="vd-sched-date">
                                <span class="vd-sched-dayname"><?= $d->format('D') ?></span>
                                <span class="vd-sched-daynum"><?= $d->format('d') ?></span>
                                <span class="vd-sched-month"><?= $d->format('M Y') ?></span>
                                </div>
                                <span class="vd-sched-slots" id="slots-<?= $sched['schedule_id'] ?>">
                                <?= $sched['max_appointments'] ?> slots
                                </span>
                                <?php if (!$isPast): ?>
                                <div class="vd-sched-actions">
                                <button class="vd-sched-btn vd-edit-sched-btn"
                                    data-id="<?= $sched['schedule_id'] ?>"
                                    data-max="<?= $sched['max_appointments'] ?>"
                                    title="Edit">
                                    <i class="ti ti-pencil"></i>
                                </button>
                                <button class="vd-sched-btn vd-delete-btn"
                                    data-id="<?= $sched['schedule_id'] ?>"
                                    title="Delete">
                                    <i class="ti ti-trash"></i>
                                </button>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Inline edit form — hidden by default -->
                            <div class="vd-sched-card-edit d-none">
                                <label class="vd-label" style="font-size:8px;">Max Slots</label>
                                <input type="number" class="form-control vd-input vd-edit-max-input"
                                value="<?= $sched['max_appointments'] ?>" min="1" max="50"
                                style="text-align:center; font-size:14px;">
                                <div class="vd-sched-actions mt-2">
                                <button class="vd-sched-btn vd-save-sched-btn"
                                    data-id="<?= $sched['schedule_id'] ?>" title="Save">
                                    <i class="ti ti-check"></i>
                                </button>
                                <button class="vd-sched-btn vd-cancel-sched-btn" title="Cancel">
                                    <i class="ti ti-x"></i>
                                </button>
                                </div>
                            </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content vd-confirm-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title vd-modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete this schedule? This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="vd-btn-outline btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="vd-btn-gold btn" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function refreshPage() {
        if (typeof loadpage === 'function') loadpage('schedule-content.php');
    }
    window.refreshSchedulePage = refreshPage;

    // Show toast for query param results (e.g., edit conflict) — use global showToast if available
    (function () {
        const params = new URLSearchParams(window.location.search);
        if (params.get('error') === 'conflict') {
            if (typeof showToast === 'function') showToast('Cannot change date: another schedule exists for that date.', false);
            // remove param from URL to avoid repeat on refresh
            history.replaceState(null, '', window.location.pathname);
        }
        if (params.get('updated') === '1') {
            if (typeof showToast === 'function') showToast('Schedule updated.', true);
            history.replaceState(null, '', window.location.pathname);
        }
    })();

    // ── Edit max appointments ──
    document.querySelectorAll('.vd-edit-sched-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card     = document.getElementById('schedCard-' + btn.dataset.id);
            const cardView = card.querySelector('.vd-sched-card-view');
            const cardEdit = card.querySelector('.vd-sched-card-edit');
            cardView.classList.add('d-none');
            cardEdit.classList.remove('d-none');
        });
    });

    document.querySelectorAll('.vd-cancel-sched-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card     = btn.closest('.vd-sched-card');
            card.querySelector('.vd-sched-card-view').classList.remove('d-none');
            card.querySelector('.vd-sched-card-edit').classList.add('d-none');
        });
    });

    document.querySelectorAll('.vd-save-sched-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id      = btn.dataset.id;
            const card    = document.getElementById('schedCard-' + id);
            const newMax  = card.querySelector('.vd-edit-max-input').value;

            if (!newMax || newMax < 1) {
                showToast('Please enter a valid number.', false);
                return;
            }

            const fd = new FormData();
            fd.append('action',          'edit_schedule');
            fd.append('schedule_id',     id);
            fd.append('max_appointments', newMax);
            LoadingUI.setButton(btn, true, 'Saving…');

            try {
                const res    = await fetch('../../controllers/scheduleController.php', { method: 'POST', body: fd });
                const result = await res.text();

                if (result.trim() === 'success') {
                    document.getElementById('slots-' + id).textContent = newMax + ' slots';
                    card.querySelector('.vd-sched-card-view').classList.remove('d-none');
                    card.querySelector('.vd-sched-card-edit').classList.add('d-none');
                    showToast('Schedule updated.', true);
                } else {
                    showToast('Failed to update. Please try again.', false);
                }
            } catch (err) {
                showToast('Network error.', false);
                console.error(err);
            } finally {
                LoadingUI.setButton(btn, false);
            }
        });
    });

    // ── Delete schedule via confirmation modal ──
    let scheduleToDelete = null;
    document.querySelectorAll('.vd-delete-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            scheduleToDelete = btn.dataset.id;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteScheduleModal'));
            deleteModal.show();
        });
    });

    document.getElementById('confirmDeleteBtn').addEventListener('click', async function () {
        if (!scheduleToDelete) return;
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Deleting…';

        LoadingUI.setButton(btn, true, 'Deleting…');
        const formData = new FormData();
        formData.append('action', 'delete_schedule');
        formData.append('schedule_id', scheduleToDelete);

        let shouldRefresh = false;
        try {
            const resp = await fetch('../../controllers/scheduleController.php', { method: 'POST', body: formData });
            const text = await resp.text();
            if (text.trim() === 'success') {
                showToast('Schedule deleted successfully!', true);
                shouldRefresh = true;
            } else {
                showToast('Error: ' + text, false);
            }
        } catch (err) {
            showToast('Network error. Please try again.', false);
            console.error(err);
        } finally {
            LoadingUI.setButton(btn, false);
            btn.disabled = false;
            btn.textContent = 'Delete';
            scheduleToDelete = null;
            const deleteModalEl = document.getElementById('deleteScheduleModal');
            const modal = bootstrap.Modal.getInstance(deleteModalEl);
            if (shouldRefresh) {
                deleteModalEl.addEventListener('hidden.bs.modal', refreshPage, { once: true });
            }
            if (modal) modal.hide();
            else if (shouldRefresh) refreshPage();
        }
    });
})();
</script>

<?php include '_add-schedule-modal.php'; ?>
