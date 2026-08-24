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
$schedulesByClinic = [];
$totalUpcomingSchedules = 0;
$totalCapacity = 0;
$totalBooked = 0;
foreach ($clinics as $clinic) {
    $clinicSchedules = $scheduleModel->getAvailableSchedulesByClinic($clinic['clinic_id']);
    $schedulesByClinic[(int) $clinic['clinic_id']] = $clinicSchedules;
    $totalUpcomingSchedules += count($clinicSchedules);
    foreach ($clinicSchedules as $schedule) {
        $totalCapacity += (int) $schedule['max_appointments'];
        $totalBooked += (int) $schedule['total_appointments'];
    }
}
$totalAvailable = max(0, $totalCapacity - $totalBooked);

// Build one global blocked-date list because schedules are unique across clinics.
$occupiedScheduleDates = [];
foreach ($schedulesByClinic as $clinicSchedules) {
    foreach ($clinicSchedules as $schedule) {
        $occupiedScheduleDates[] = $schedule['sched_date'];
    }
}
$occupiedScheduleDates = array_values(array_unique($occupiedScheduleDates));
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
?>

<div class="d-flex flex-column gap-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <div class="vd-welcome-greet">SCHEDULE MANAGEMENT</div>
                <div class="vd-welcome-name">Clinic Availability</div>
                <p class="text-muted small mb-0 mt-2">Create appointment dates, review remaining availability, and adjust each schedule’s capacity.</p>
            </div>
            <?php $firstClinic = $clinics[0] ?? null; ?>
            <button type="button" class="btn vd-btn-gold align-self-start" id="addScheduleForActiveClinic"
                data-bs-toggle="modal" data-bs-target="#addScheduleModal"
                data-clinic-id="<?= (int) ($firstClinic['clinic_id'] ?? 0) ?>"
                data-clinic-name="<?= htmlspecialchars($firstClinic['clinic_name'] ?? '', ENT_QUOTES) ?>"
                <?= $firstClinic ? '' : 'disabled' ?>>
                <i class="ti ti-calendar-plus me-1"></i> Add Schedule
            </button>
        </div>

        <div class="vd-schedule-summary-grid">
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-calendar-event"></i></span><span><small>Upcoming Dates</small><strong><?= $totalUpcomingSchedules ?></strong></span></div>
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-users"></i></span><span><small>Total Capacity</small><strong><?= $totalCapacity ?></strong></span></div>
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-user-check"></i></span><span><small>Booked Slots</small><strong><?= $totalBooked ?></strong></span></div>
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-armchair"></i></span><span><small>Available Slots</small><strong><?= $totalAvailable ?></strong></span></div>
        </div>

        <div class="vd-clinic-switch" role="tablist" aria-label="Schedule clinic">
            <?php foreach ($clinics as $index => $clinic): ?>
            <button type="button" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                class="vd-clinic-switch-btn vd-schedule-clinic-btn <?= $index === 0 ? 'active' : '' ?>"
                data-clinic-id="<?= (int) $clinic['clinic_id'] ?>"
                data-clinic-name="<?= htmlspecialchars($clinic['clinic_name'], ENT_QUOTES) ?>">
                <i class="ti ti-building-hospital"></i> <?= htmlspecialchars($clinic['clinic_name']) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Schedule Overview -->
        <?php foreach ($clinics as $clinicIndex => $clinic):
            $schedules = $schedulesByClinic[(int) $clinic['clinic_id']] ?? [];
        ?>
        <div role="tabpanel" class="vd-dash-card vd-schedule-clinic-panel <?= $clinicIndex === 0 ? '' : 'd-none' ?>" data-clinic-panel="<?= (int) $clinic['clinic_id'] ?>">
            <div class="vd-dash-card-header">
                <span class="vd-dash-card-title">
                    <i class="ti ti-building me-1"></i>
                    <?= htmlspecialchars($clinic['clinic_name']) ?>
                </span>
                <span class="vd-topbar-date"><?= count($schedules) ?> upcoming date<?= count($schedules) === 1 ? '' : 's' ?></span>
            </div>
            <div class="vd-dash-card-body">
                <?php if (empty($schedules)): ?>
                    <div class="vd-empty-state">No schedules yet.</div>
                <?php else: ?>
                    <div class="vd-sched-grid">
                        <?php foreach ($schedules as $sched):
                            $d      = new DateTime($sched['sched_date']);
                            $isPast = $d < new DateTime('today');
                            $booked = (int) $sched['total_appointments'];
                            $capacity = (int) $sched['max_appointments'];
                            $available = max(0, (int) $sched['available_slots']);
                            $usagePercent = $capacity > 0 ? min(100, (int) round(($booked / $capacity) * 100)) : 0;
                        ?>
                        <div class="vd-sched-card <?= $isPast ? 'past' : '' ?>"
                            id="schedCard-<?= $sched['schedule_id'] ?>" data-booked="<?= $booked ?>">

                            <!-- Default view -->
                            <div class="vd-sched-card-view">
                                <div class="vd-sched-date">
                                <span class="vd-sched-dayname"><?= $d->format('D') ?></span>
                                <span class="vd-sched-daynum"><?= $d->format('d') ?></span>
                                <span class="vd-sched-month"><?= $d->format('M Y') ?></span>
                                </div>
                                <span class="vd-sched-slots" id="slots-<?= $sched['schedule_id'] ?>">
                                <?= $available ?> available
                                </span>
                                <div class="vd-sched-capacity">
                                    <span id="usage-<?= $sched['schedule_id'] ?>"><?= $booked ?> booked of <?= $capacity ?></span>
                                    <div class="vd-sched-capacity-track"><span id="progress-<?= $sched['schedule_id'] ?>" style="width:<?= $usagePercent ?>%"></span></div>
                                </div>
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
                                    <?= $booked > 0 ? 'disabled' : '' ?>
                                    title="<?= $booked > 0 ? 'Schedules with bookings cannot be deleted' : 'Delete schedule' ?>">
                                    <i class="ti ti-trash"></i>
                                </button>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Inline edit form — hidden by default -->
                            <div class="vd-sched-card-edit d-none">
                                <label class="vd-label" style="font-size:8px;">Max Slots</label>
                                <input type="number" class="form-control vd-input vd-edit-max-input"
                                value="<?= $capacity ?>" min="<?= max(1, $booked) ?>" max="50"
                                style="text-align:center; font-size:14px;">
                                <small class="vd-sched-edit-help">Minimum: <?= max(1, $booked) ?> based on current bookings</small>
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
    const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
    function refreshPage() {
        if (typeof loadpage === 'function') loadpage('schedule-content.php');
    }
    window.refreshSchedulePage = refreshPage;

    const clinicButtons = Array.from(document.querySelectorAll('.vd-schedule-clinic-btn'));
    const clinicPanels = Array.from(document.querySelectorAll('.vd-schedule-clinic-panel'));
    const addScheduleButton = document.getElementById('addScheduleForActiveClinic');
    clinicButtons.forEach(button => button.addEventListener('click', () => {
        clinicButtons.forEach(item => {
            const isActive = item === button;
            item.classList.toggle('active', isActive);
            item.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        clinicPanels.forEach(panel => panel.classList.toggle('d-none', panel.dataset.clinicPanel !== button.dataset.clinicId));
        addScheduleButton.dataset.clinicId = button.dataset.clinicId;
        addScheduleButton.dataset.clinicName = button.dataset.clinicName;
    }));

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
            fd.append('csrf_token', csrfToken);
            LoadingUI.setButton(btn, true, 'Saving…');

            try {
                const res    = await fetch('../../controllers/scheduleController.php', { method: 'POST', body: fd });
                const result = await res.text();

                if (result.trim() === 'success') {
                    const booked = Number(card.dataset.booked || 0);
                    const capacity = Number(newMax);
                    document.getElementById('slots-' + id).textContent = Math.max(0, capacity - booked) + ' available';
                    document.getElementById('usage-' + id).textContent = booked + ' booked of ' + capacity;
                    document.getElementById('progress-' + id).style.width = Math.min(100, Math.round((booked / capacity) * 100)) + '%';
                    card.querySelector('.vd-sched-card-view').classList.remove('d-none');
                    card.querySelector('.vd-sched-card-edit').classList.add('d-none');
                    showToast('Schedule updated.', true);
                } else {
                    showToast(result.trim() || 'Failed to update. Please try again.', false);
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
        formData.append('csrf_token', csrfToken);

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

<?php include __DIR__ . '/_add-schedule-modal.php'; ?>
