<!-- STAT CARDS -->
    <div class="vd-stat-grid">
        <?php
        if (session_status() === PHP_SESSION_NONE) session_start();

        // Auth guard
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
            echo '<div class="vd-empty-state">Unauthorized.</div>';
            exit;
        }

        require_once __DIR__ . '/../../../../config/conn.php';
        require_once __DIR__ . '/../../../models/appointmentModel.php';
        require_once __DIR__ . '/../../../models/clinicModel.php';

        $db = new Database();
        $conn = $db->connect();

        $appointmentModel = new Appointment($conn);
        $clinicModel = new Clinic($conn);

        $upcoming = $appointmentModel->getAllUpcomingWithStatus();
        $clinics = $clinicModel->getAllClinics();

        $todayCount    = count(array_filter($upcoming, fn($a) => $a['date'] === date('Y-m-d')));
        $pendingCount  = count(array_filter($upcoming, fn($a) => $a['status'] === 'Pending'));
        $totalUpcoming = count($upcoming);
        $clinicCount   = count($clinics);
        ?>
        <div class="vd-stat-card">
        <div class="vd-stat-label">Today's Appointments</div>
        <div class="vd-stat-value"><?= $todayCount ?></div>
        <div class="vd-stat-sub">Across <?= $clinicCount ?> clinics</div>
        </div>
        <div class="vd-stat-card">
        <div class="vd-stat-label">Pending</div>
        <div class="vd-stat-value"><?= $pendingCount ?></div>
        <div class="vd-stat-sub">Awaiting confirmation</div>
        </div>
        <div class="vd-stat-card">
        <div class="vd-stat-label">Upcoming</div>
        <div class="vd-stat-value"><?= $totalUpcoming ?></div>
        <div class="vd-stat-sub">Total scheduled</div>
        </div>
        <div class="vd-stat-card">
        <div class="vd-stat-label">Clinics</div>
        <div class="vd-stat-value"><?= $clinicCount ?></div>
        <div class="vd-stat-sub">Active locations</div>
        </div>
    </div>

    <!-- CARDS ROW -->
    <div class="vd-dash-grid2">

        <!-- Upcoming appointments -->
        <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Upcoming Appointments</span>
            <a href="appointments.php" class="vd-dash-card-link">View all →</a>
        </div>
        <div class="vd-dash-card-body">
            <?php if (empty($upcoming)): ?>
            <div class="vd-empty-state">No upcoming appointments.</div>
            <?php else: ?>
            <?php foreach (array_slice($upcoming, 0, 5) as $appt): ?>
                <?php
                $d      = new DateTime($appt['date']);
                $day    = $d->format('d');
                $mon    = $d->format('M');
                $status = strtolower($appt['status']);
                ?>
                <div class="vd-appt-row">
                <div class="vd-appt-date-box">
                    <div class="vd-appt-day"><?= $day ?></div>
                    <div class="vd-appt-mon"><?= $mon ?></div>
                </div>
                <div class="vd-appt-info">
                    <div class="vd-appt-name"><?= htmlspecialchars($appt['lastname'] . ', ' . $appt['firstname']) ?></div>
                    <div class="vd-appt-meta"><?= htmlspecialchars($appt['service']) ?> · <?= htmlspecialchars($appt['clinic_name']) ?></div>
                </div>
                <span class="vd-status vd-status-<?= $status ?>"><?= htmlspecialchars($appt['status']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div>

        <!-- Clinics + Quick actions -->
        <div class="d-flex flex-column gap-3">

        <div class="vd-dash-card">
            <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Clinic Locations</span>
            <a href="clinics.php" class="vd-dash-card-link">Manage →</a>
            </div>
            <div class="vd-dash-card-body">
            <?php if (empty($clinics)): ?>
                <div class="vd-empty-state">No clinics found.</div>
            <?php else: ?>
                <?php foreach ($clinics as $clinic): ?>
                <div class="vd-clinic-row">
                    <div class="vd-clinic-icon"><i class="ti ti-building"></i></div>
                    <div class="flex-1" style="flex:1; min-width:0;">
                    <div class="vd-appt-name"><?= htmlspecialchars($clinic['clinic_name']) ?></div>
                    <div class="vd-appt-meta"><?= htmlspecialchars($clinic['clinic_address']) ?></div>
                    </div>
                    <div class="vd-clinic-dot"></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>

        <div class="vd-dash-card">
            <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Quick Actions</span>
            </div>
            <div class="vd-quick-actions">
            <a href="appointments.php" class="btn vd-btn-outline btn-sm">
                <i class="ti ti-calendar me-1"></i> Manage Appointments
            </a>
            <a href="clinics.php" class="btn vd-btn-outline btn-sm">
                <i class="ti ti-building me-1"></i> Manage Clinics
            </a>
            <a href="schedules.php" class="btn vd-btn-outline btn-sm">
                <i class="ti ti-clock me-1"></i> Set Schedules
            </a>
        </div>
        </div>
        </div>
    </div>
    </div>