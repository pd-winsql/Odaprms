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
$scheduleSummaryByClinic = [];
foreach ($clinics as $clinic) {
    $clinicSchedules = $scheduleModel->getAvailableSchedulesByClinic($clinic['clinic_id']);
    $clinicId = (int) $clinic['clinic_id'];
    $clinicCapacity = 0;
    $clinicBooked = 0;
    $schedulesByClinic[$clinicId] = $clinicSchedules;
    foreach ($clinicSchedules as $schedule) {
        $clinicCapacity += (int) $schedule['max_appointments'];
        $clinicBooked += (int) $schedule['total_appointments'];
    }
    $scheduleSummaryByClinic[$clinicId] = [
        'upcoming' => count($clinicSchedules),
        'capacity' => $clinicCapacity,
        'booked' => $clinicBooked,
        'available' => max(0, $clinicCapacity - $clinicBooked),
    ];
}
$firstClinic = $clinics[0] ?? null;
$activeSummary = $firstClinic
    ? $scheduleSummaryByClinic[(int) $firstClinic['clinic_id']]
    : ['upcoming' => 0, 'capacity' => 0, 'booked' => 0, 'available' => 0];

// A clinic can only have one window per date. The other clinic may use that
// same date when its time window preserves the transition interval.
$occupiedScheduleDatesByClinic = [];
foreach ($schedulesByClinic as $clinicId => $clinicSchedules) {
    $occupiedScheduleDatesByClinic[$clinicId] = array_values(array_unique(array_column($clinicSchedules, 'sched_date')));
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
?>

<div class="d-flex flex-column gap-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <div class="vd-welcome-greet">SCHEDULE MANAGEMENT</div>
                <div class="vd-welcome-name">Clinic Availability</div>
                <p class="text-muted small mb-0 mt-2">Create appointment dates, review remaining availability, and adjust each schedule’s capacity.</p>
            </div>
            <button type="button" class="btn vd-btn-gold align-self-start" id="addScheduleForActiveClinic"
                data-bs-toggle="modal" data-bs-target="#addScheduleModal"
                data-schedule-mode="add"
                data-clinic-id="<?= (int) ($firstClinic['clinic_id'] ?? 0) ?>"
                data-clinic-name="<?= htmlspecialchars($firstClinic['clinic_name'] ?? '', ENT_QUOTES) ?>"
                data-default-start="<?= htmlspecialchars(substr($firstClinic['default_start_time'] ?? '08:00:00', 0, 5)) ?>"
                data-default-end="<?= htmlspecialchars(substr($firstClinic['default_end_time'] ?? '17:00:00', 0, 5)) ?>"
                <?= $firstClinic ? '' : 'disabled' ?>>
                <i class="ti ti-calendar-plus me-1"></i> Add Schedule
            </button>
        </div>

        <div class="vd-clinic-switch" role="tablist" aria-label="Schedule clinic">
            <?php foreach ($clinics as $index => $clinic): ?>
            <?php $summary = $scheduleSummaryByClinic[(int) $clinic['clinic_id']]; ?>
            <button type="button" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                class="vd-clinic-switch-btn vd-schedule-clinic-btn <?= $index === 0 ? 'active' : '' ?>"
                data-clinic-id="<?= (int) $clinic['clinic_id'] ?>"
                data-clinic-name="<?= htmlspecialchars($clinic['clinic_name'], ENT_QUOTES) ?>"
                data-default-start="<?= htmlspecialchars(substr($clinic['default_start_time'] ?? '08:00:00', 0, 5)) ?>"
                data-default-end="<?= htmlspecialchars(substr($clinic['default_end_time'] ?? '17:00:00', 0, 5)) ?>"
                data-upcoming="<?= $summary['upcoming'] ?>"
                data-capacity="<?= $summary['capacity'] ?>"
                data-booked="<?= $summary['booked'] ?>"
                data-available="<?= $summary['available'] ?>">
                <i class="ti ti-building-hospital"></i> <?= htmlspecialchars($clinic['clinic_name']) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="vd-schedule-summary-grid" aria-live="polite">
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-calendar-event"></i></span><span><small>Upcoming Dates</small><strong id="scheduleSummaryUpcoming"><?= $activeSummary['upcoming'] ?></strong></span></div>
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-users"></i></span><span><small>Total Capacity</small><strong id="scheduleSummaryCapacity"><?= $activeSummary['capacity'] ?></strong></span></div>
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-user-check"></i></span><span><small>Booked Slots</small><strong id="scheduleSummaryBooked"><?= $activeSummary['booked'] ?></strong></span></div>
            <div class="vd-schedule-summary-card"><span class="vd-schedule-summary-icon"><i class="ti ti-armchair"></i></span><span><small>Available Slots</small><strong id="scheduleSummaryAvailable"><?= $activeSummary['available'] ?></strong></span></div>
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
                            $timeRange = Schedule::formatTimeRange($sched['start_time'], $sched['end_time']);
                        ?>
                        <div class="vd-sched-card <?= $isPast ? 'past' : '' ?>"
                            id="schedCard-<?= $sched['schedule_id'] ?>" data-booked="<?= $booked ?>" data-capacity="<?= $capacity ?>"
                            data-clinic-id="<?= (int) $clinic['clinic_id'] ?>" data-date="<?= htmlspecialchars($sched['sched_date']) ?>"
                            data-start-time="<?= htmlspecialchars(substr($sched['start_time'], 0, 5)) ?>" data-end-time="<?= htmlspecialchars(substr($sched['end_time'], 0, 5)) ?>">

                            <!-- Default view -->
                            <div class="vd-sched-card-view">
                                <div class="vd-sched-date">
                                <span class="vd-sched-dayname"><?= $d->format('D') ?></span>
                                <span class="vd-sched-daynum"><?= $d->format('d') ?></span>
                                <span class="vd-sched-month"><?= $d->format('M Y') ?></span>
                                </div>
                                <div class="vd-sched-window"><i class="ti ti-clock" aria-hidden="true"></i><span><?= htmlspecialchars($timeRange) ?></span></div>
                                <span class="vd-sched-slots" id="slots-<?= $sched['schedule_id'] ?>">
                                <?= $available ?> available
                                </span>
                                <div class="vd-sched-capacity">
                                    <span id="usage-<?= $sched['schedule_id'] ?>"><?= $booked ?> booked of <?= $capacity ?></span>
                                    <div class="vd-sched-capacity-track"><span id="progress-<?= $sched['schedule_id'] ?>" style="width:<?= $usagePercent ?>%"></span></div>
                                </div>
                                <?php if (!$isPast): ?>
                                <div class="vd-sched-actions">
                                <button type="button" class="vd-sched-btn vd-edit-sched-btn"
                                    data-bs-toggle="modal" data-bs-target="#addScheduleModal" data-schedule-mode="edit"
                                    data-id="<?= $sched['schedule_id'] ?>"
                                    data-clinic-id="<?= (int) $clinic['clinic_id'] ?>"
                                    data-clinic-name="<?= htmlspecialchars($clinic['clinic_name'], ENT_QUOTES) ?>"
                                    data-booked="<?= $booked ?>"
                                    data-date="<?= htmlspecialchars($sched['sched_date']) ?>"
                                    data-start-time="<?= htmlspecialchars(substr($sched['start_time'], 0, 5)) ?>"
                                    data-end-time="<?= htmlspecialchars(substr($sched['end_time'], 0, 5)) ?>"
                                    data-max="<?= $sched['max_appointments'] ?>"
                                    title="Edit schedule" aria-label="Edit this schedule">
                                    <i class="ti ti-pencil" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="vd-sched-btn vd-delete-btn"
                                    data-id="<?= $sched['schedule_id'] ?>"
                                    <?= $booked > 0 ? 'disabled' : '' ?>
                                    title="<?= $booked > 0 ? 'Schedules with bookings cannot be deleted' : 'Delete schedule' ?>"
                                    aria-label="<?= $booked > 0 ? 'Cannot delete this schedule because it has bookings' : 'Delete this schedule' ?>">
                                    <i class="ti ti-trash" aria-hidden="true"></i>
                                </button>
                                </div>
                                <?php endif; ?>
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
    const summaryFields = {
        upcoming: document.getElementById('scheduleSummaryUpcoming'),
        capacity: document.getElementById('scheduleSummaryCapacity'),
        booked: document.getElementById('scheduleSummaryBooked'),
        available: document.getElementById('scheduleSummaryAvailable')
    };

    function updateScheduleSummary(button) {
        Object.entries(summaryFields).forEach(([key, field]) => {
            if (field) field.textContent = button.dataset[key] || '0';
        });
    }

    clinicButtons.forEach(button => button.addEventListener('click', () => {
        clinicButtons.forEach(item => {
            const isActive = item === button;
            item.classList.toggle('active', isActive);
            item.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        clinicPanels.forEach(panel => panel.classList.toggle('d-none', panel.dataset.clinicPanel !== button.dataset.clinicId));
        addScheduleButton.dataset.clinicId = button.dataset.clinicId;
        addScheduleButton.dataset.clinicName = button.dataset.clinicName;
        addScheduleButton.dataset.defaultStart = button.dataset.defaultStart;
        addScheduleButton.dataset.defaultEnd = button.dataset.defaultEnd;
        updateScheduleSummary(button);
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
