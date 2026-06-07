<div class="vd-content">
        <div class="d-flex flex-column gap-4">

        <!-- Schedule overview -->
        <div class="vd-dash-card">
            <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Schedule Overview</span>
            </div>
            <div class="vd-dash-card-body">
            <?php if (empty($schedules)): ?>
                <div class="vd-empty-state">No schedules found.</div>
            <?php else: ?>
                <?php foreach ($schedules as $schedule): ?>
                <div class="vd-schedule-row">
                    <div class="vd-schedule-icon"><i class="ti ti-clock"></i></div>
                    <div class="flex-1" style="flex:1; min-width:0;">
                    <div class="vd-appt-name"><?= htmlspecialchars($schedule['clinic_name']) ?></div>
                    <div class="vd-appt-meta"><?= htmlspecialchars($schedule['day_of_week']) ?> · <?= htmlspecialchars(date('h:i A', strtotime($schedule['start_time']))) ?> - <?= htmlspecialchars(date('h:i A', strtotime($schedule['end_time']))) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
</div>