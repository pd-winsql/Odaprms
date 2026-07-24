<?php
session_start();
require_once '../../../config/conn.php';
require_once '../../models/appointmentModel.php';
require_once '../../models/clinicModel.php';

// Prevent browser from caching protected pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

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
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="../../../public/css/bootstrap.min.css">
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
        <a href="#" class="vd-nav-item active" data-page="dashboard-content.php">
            <i class="ti ti-layout-dashboard"></i> Dashboard
        </a>
        <a href="#" class="vd-nav-item" data-page="appointment-content.php">
            <i class="ti ti-calendar"></i> Appointments
        </a>
        <a href="#" class="vd-nav-item" data-page="patient-content.php">
            <i class="ti ti-users"></i> Patients
        </a>

        <div class="vd-nav-section">Manage</div>
        <a href="#" class="vd-nav-item" data-page="den-assist-content.php">
            <i class="ti ti-nurse"></i> Dental Assistants
        </a>
        <a href="#" class="vd-nav-item" data-page="clinic-content.php">
            <i class="ti ti-building"></i> Clinics
        </a>
        
        <a href="#" class="vd-nav-item" data-page="schedule-content.php">
            <i class="ti ti-clock"></i> Schedules
        </a>

        <div class="vd-nav-section">Account</div>
        <a href="#" class="vd-nav-item" data-page="settings-content.php">
            <i class="ti ti-settings"></i> Settings
        </a>
        <a href="#" class="vd-nav-item" data-logout-confirm="../../../apps/controllers/userController.php?action=logout">
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
        <?php include 'partials/dashboard-content.php'; ?>
        </div><!-- /vd-dash-content -->
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../public/js/logout-confirmation.js"></script>
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

        /* Navigation */
        const navItems = document.querySelectorAll('.vd-nav-item');
        const dashContent = document.querySelector('.vd-dash-content');
        
        async function loadpage(page) {
        try {
            const response = await fetch(`partials/${page}`);
            if (!response.ok) throw new Error('Network response was not ok');
            const html = await response.text();
            dashContent.innerHTML = html;

            dashContent.querySelectorAll('script').forEach(oldScript => {
            const newScript = document.createElement('script');
            newScript.textContent = oldScript.textContent;
            document.body.appendChild(newScript);
            oldScript.remove();
            });
        
            closeSidebar();
            } catch (error) {
            dashContent.innerHTML = `<div class="vd-empty-state">Error loading content.</div>`;
            console.error('Error fetching page:', error);
            }
        }
        
        navItems.forEach(item => {
            item.addEventListener('click', async (e) => {
            e.preventDefault();

            if (item.hasAttribute('data-logout-confirm')) {
                return;
            }

            const page = item.getAttribute('data-page');
            if (!page || page === '#') return;

            navItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            window.location.hash = page;

            await loadpage(page);
            });
        });

            window.addEventListener('DOMContentLoaded', async () => {
            const hash = window.location.hash.replace('#', '');
            if (hash) {
                const matchingNav = document.querySelector(`[data-page="${hash}"]`);
                if(matchingNav) {
                navItems.forEach(i => i.classList.remove('active'));
                matchingNav.classList.add('active');
                await loadpage(hash);
                }
            }
            });
        
        // Prevent back button after logout
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                window.location.reload();
            }
        });
    </script>

    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content vd-modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title vd-modal-title" id="logoutModalLabel">Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to logout from your account?</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="vd-btn-outline btn" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmLogoutBtn" class="vd-btn-gold btn">Logout</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>