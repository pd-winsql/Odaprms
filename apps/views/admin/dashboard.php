<?php
session_start();
require_once '../../../config/conn.php';
require_once '../../models/appointmentModel.php';
require_once '../../models/clinicModel.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../index.php?openModal=true');
    exit;
}
if (!in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    header('Location: ../patient/dashboard.php');
    exit;
}

$db   = new Database();
$conn = $db->connect();

$appointmentModel = new Appointment($conn);
$clinicModel      = new Clinic($conn);

$upcoming = $appointmentModel->getAllUpcomingWithStatus();
$clinics  = $clinicModel->getAllClinics();

// Derive initials for avatar from username
$username = $_SESSION['username'] ?? 'User';
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $username))));
$initials = substr($initials, 0, 2);

// Current date display
$today = date('l, F j Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Dr. Aprille Ventura Clinica Dental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
  <link rel="stylesheet" href="../../../public/css/styles.css">
  <link rel="stylesheet" href="../../../public/css/dashboard.css">
</head>
<body class="vd-dash-body">

  <!-- Sidebar overlay (mobile) -->
  <div class="vd-sidebar-overlay" id="sidebarOverlay"></div>

  <!-- SIDEBAR -->
  <aside class="vd-sidebar" id="sidebar">

    <div class="vd-sidebar-brand">
      <div class="vd-logo-name">Dr. Aprille</div>
      <div class="vd-logo-ventura">VEN<span class="vd-cross">✚</span>URA</div>
      <div class="vd-logo-sub">Clinica Dental</div>
    </div>

    <nav class="vd-sidebar-nav">
      <div class="vd-nav-section">Main</div>
      <a href="dashboard.php" class="vd-nav-item active">
        <i class="ti ti-layout-dashboard"></i> Dashboard
      </a>
      <a href="appointments.php" class="vd-nav-item">
        <i class="ti ti-calendar"></i> Appointments
      </a>
      <a href="patients.php" class="vd-nav-item">
        <i class="ti ti-users"></i> Patients
      </a>

      <div class="vd-nav-section">Manage</div>
      <a href="clinics.php" class="vd-nav-item">
        <i class="ti ti-building"></i> Clinics
      </a>
      <a href="schedules.php" class="vd-nav-item">
        <i class="ti ti-clock"></i> Schedules
      </a>

      <div class="vd-nav-section">Account</div>
      <a href="settings.php" class="vd-nav-item">
        <i class="ti ti-settings"></i> Settings
      </a>
      <a href="../../../apps/controllers/userController.php?action=logout" class="vd-nav-item">
        <i class="ti ti-logout"></i> Logout
      </a>
    </nav>

    <div class="vd-sidebar-footer">
      <div class="vd-user-chip">
        <div class="vd-user-avatar"><?= htmlspecialchars($initials) ?></div>
        <div>
          <div class="vd-user-name"><?= htmlspecialchars($username) ?></div>
          <div class="vd-user-role"><?= htmlspecialchars($_SESSION['user_role']) ?></div>
        </div>
      </div>
    </div>

  </aside>

  <!-- MAIN -->
  <main class="vd-dash-main">

    <!-- Topbar -->
    <div class="vd-dash-topbar">
      <div class="vd-dash-topbar-left">
        <button class="vd-menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
          <i class="ti ti-menu-2"></i>
        </button>
        <span class="vd-dash-title">Dashboard</span>
      </div>
      <div class="vd-topbar-right">
        <span class="vd-topbar-date"><?= $today ?></span>
        <span class="vd-role-badge"><?= htmlspecialchars($_SESSION['user_role']) ?></span>
      </div>
    </div>

    <!-- Content -->
    <div class="vd-dash-content">

      <!-- STAT CARDS -->
      <div class="vd-stat-grid">
        <?php
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
                  $time   = date('g:i A', strtotime($appt['time']));
                  $status = strtolower($appt['status']);
                ?>
                <div class="vd-appt-row">
                  <div class="vd-appt-date-box">
                    <div class="vd-appt-day"><?= $day ?></div>
                    <div class="vd-appt-mon"><?= $mon ?></div>
                  </div>
                  <div class="vd-appt-info">
                    <div class="vd-appt-name"><?= htmlspecialchars($appt['lastname'] . ', ' . $appt['firstname']) ?></div>
                    <div class="vd-appt-meta"><?= $time ?> · <?= htmlspecialchars($appt['service']) ?> · <?= htmlspecialchars($appt['clinic']) ?></div>
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
    </div><!-- /vd-dash-content -->
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const sidebar        = document.getElementById('sidebar');
    const overlay        = document.getElementById('sidebarOverlay');
    const menuToggle     = document.getElementById('menuToggle');

    function openSidebar() {
      sidebar.classList.add('open');
      overlay.classList.add('active');
    }
    function closeSidebar() {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    }

    menuToggle.addEventListener('click', () => {
      sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);
  </script>
</body>
</html>