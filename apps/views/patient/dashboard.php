<?php
session_start();
require_once '../../../config/conn.php';
require_once '../../models/patientModel.php';

// Prevent browser from caching protected pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../index.php?openModal=true');
    exit;
}
if ($_SESSION['user_role'] !== 'Patient') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$db   = new Database();
$conn = $db->connect();
$patientModel = new Patient($conn);

// Get patient record linked to this user
$patient = $patientModel->getPatientByUserId($_SESSION['user_id']);

// If no patient record exists yet, create one
if (!$patient) {
    $patientModel->createPatientFromUser(
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['email']
    );
    $patient = $patientModel->getPatientByUserId($_SESSION['user_id']);
}

$username = $_SESSION['username'] ?? 'Patient';
$initials = strtoupper(substr($patient['firstname'] ?? $username, 0, 1) . substr($patient['lastname'] ?? '', 0, 1));
$initials = trim($initials) ?: strtoupper(substr($username, 0, 2));
$today    = date('l, F j Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | Dr. Aprille Ventura Clinica Dental</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="../../../public/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../public/css/styles.css">
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="../../../public/css/patient-dashboard.css">
</head>
<body class="vd-pat-body">

    <!-- Sidebar overlay (mobile) -->
    <div class="vd-sidebar-overlay" id="sidebarOverlay"></div>

    <!-- SIDEBAR -->
    <aside class="vd-pat-sidebar" id="sidebar">
        <div class="vd-sidebar-brand">
        <div class="vd-logo-name">Dr. Aprille</div>
        <div class="vd-logo-ventura">VEN<span class="vd-cross">✚</span>URA</div>
        <div class="vd-logo-sub">Clinica Dental</div>
        </div>

        <nav class="vd-sidebar-nav">
        <div class="vd-nav-section">Main</div>
        <a href="#" class="vd-nav-item active" data-page="home-content.php">
            <i class="ti ti-home"></i> Home
        </a>
        <a href="#" class="vd-nav-item" data-page="appointment-content.php">
            <i class="ti ti-calendar"></i> Appointments
        </a>

        <div class="vd-nav-section">Account</div>
        <a href="#" class="vd-nav-item" data-page="profile-content.php">
            <i class="ti ti-user"></i> My Profile
        </a>
        <a href="#" class="vd-nav-item" data-page="change-password-content.php">
            <i class="ti ti-lock"></i> Change Password
        </a>
        <a href="#" class="vd-nav-item" data-logout-confirm="../../../apps/controllers/userController.php?action=logout">
            <i class="ti ti-logout"></i> Logout
        </a>
        </nav>

        <div class="vd-sidebar-footer">
        <div class="vd-user-chip">
            <div class="vd-user-avatar"><?= htmlspecialchars($initials) ?></div>
            <div>
            <div class="vd-user-name"><?= htmlspecialchars($patient['firstname'] ?? $username) ?></div>
            <div class="vd-user-role">Patient</div>
            </div>
        </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="vd-pat-main">

        <!-- Topbar -->
        <div class="vd-dash-topbar">
        <div class="vd-dash-topbar-left">
            <button class="vd-menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
            <i class="ti ti-menu-2"></i>
            </button>
            <span class="vd-dash-title">My Account</span>
        </div>
        <div class="vd-topbar-right">
            <span class="vd-topbar-date"><?= $today ?></span>
        </div>
        </div>

        <!-- Content -->
        <div class="vd-dash-content" id="patDashContent">
        <?php include 'partials/home-content.php'; ?>
        </div>

    </main>

    <script src="../../../public/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar    = document.getElementById('sidebar');
        const overlay    = document.getElementById('sidebarOverlay');
        const menuToggle = document.getElementById('menuToggle');

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

        const navItems   = document.querySelectorAll('.vd-nav-item');
        const dashContent = document.getElementById('patDashContent');

        async function loadPage(page) {
        try {
            const response = await fetch(`partials/${page}`);
            if (!response.ok) throw new Error('Failed to load');
            const html = await response.text();
            dashContent.innerHTML = html;

            dashContent.querySelectorAll('script').forEach(oldScript => {
            const newScript = document.createElement('script');
            newScript.textContent = oldScript.textContent;
            document.body.appendChild(newScript);
            oldScript.remove();
            });

            closeSidebar();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (err) {
            dashContent.innerHTML = '<div class="vd-empty-state">Error loading content.</div>';
            console.error(err);
        }
        }

        navItems.forEach(item => {
        item.addEventListener('click', async (e) => {
            e.preventDefault();

            if (item.hasAttribute('data-logout-confirm')) {
                const modal = new bootstrap.Modal(document.getElementById('logoutModal'));
                modal.show();
                return;
            }

            const page = item.getAttribute('data-page');
            if (!page) return;

            navItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            window.location.hash = page;
            await loadPage(page);
        });
        });

        // Restore last page on reload
        window.addEventListener('DOMContentLoaded', async () => {
        const hash = window.location.hash.replace('#', '');
        if (hash) {
            const matchingNav = document.querySelector(`[data-page="${hash}"]`);
            if (matchingNav) {
            navItems.forEach(i => i.classList.remove('active'));
            matchingNav.classList.add('active');
            await loadPage(hash);
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

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="vd-modal-title mb-0">Logout</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <p class="small mb-4">Are you sure you want to logout from your account?</p>
            <div class="d-flex justify-content-end gap-2">
            <button class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <a href="../../../apps/controllers/userController.php?action=logout" class="btn vd-btn-gold">Logout</a>
            </div>
        </div>
        </div>
    </div>

</body>
</html>