<?php
session_start();
require_once '../../../config/conn.php';
require_once '../../models/patientModel.php';
require_once '../../helpers/siteBranding.php';

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
$branding = vdLoadSiteBranding($conn);

// Get patient record linked to this user
$patient = $patientModel->getPatientByUserId($_SESSION['user_id']);

// If no patient record exists yet, create one
if (!$patient) {
    $patientModel->createPatientFromUser($_SESSION['user_id'], $_SESSION['email']);
    $patient = $patientModel->getPatientByUserId($_SESSION['user_id']);
}

$displayName = $_SESSION['display_name'] ?? $_SESSION['email'] ?? 'Patient';
$initials = strtoupper(substr($patient['firstname'] ?? $displayName, 0, 1) . substr($patient['lastname'] ?? '', 0, 1));
$initials = trim($initials) ?: strtoupper(substr($displayName, 0, 2));
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../public/css/styles.css">
    <link rel="stylesheet" href="../../../public/css/dashboard.css?v=20260813-brand-logo-2">
    <link rel="stylesheet" href="../../../public/css/patient-dashboard.css?v=5">
    <link rel="stylesheet" href="../../../public/css/loading.css">
    <script src="../../../public/js/loading.js" defer></script>
</head>
<body class="vd-dash-body">

    <!-- Sidebar overlay (mobile) -->
    <div class="vd-sidebar-overlay" id="sidebarOverlay"></div>

    <!-- SIDEBAR -->
    <aside class="vd-sidebar" id="sidebar">
        <div class="vd-sidebar-brand">
            <?= vdRenderSiteBranding($branding, '../../../public/assets', 'sidebar') ?>
        </div>

        <nav class="vd-sidebar-nav">
        <div class="vd-nav-section">Main</div>
        <a href="#" class="vd-nav-item active" data-page="home-content.php">
            <span class="vd-nav-icon"><i class="ti ti-home"></i></span> Home
        </a>
        <a href="#" class="vd-nav-item" data-page="booking-content.php">
            <span class="vd-nav-icon"><i class="ti ti-calendar-plus"></i></span> Book Appointment
        </a>
        <a href="#" class="vd-nav-item" data-page="billing-content.php">
            <span class="vd-nav-icon"><i class="ti ti-receipt"></i></span> Billing
        </a>
        <a href="#" class="vd-nav-item" data-page="history-content.php">
            <span class="vd-nav-icon"><i class="ti ti-calendar"></i></span> History
        </a>

        <div class="vd-nav-section">Account</div>
        <a href="#" class="vd-nav-item" data-page="profile-content.php">
            <span class="vd-nav-icon"><i class="ti ti-user"></i></span> My Profile
        </a>
        <a href="#" class="vd-nav-item" data-page="change-password-content.php">
            <span class="vd-nav-icon"><i class="ti ti-lock"></i></span> Change Password
        </a>
        <a href="#" class="vd-nav-item" data-logout-confirm="../../../apps/controllers/userController.php?action=logout">
            <span class="vd-nav-icon"><i class="ti ti-logout"></i></span> Logout
        </a>
        </nav>

        <div class="vd-sidebar-footer">
        <div class="vd-user-chip">
            <div class="vd-user-avatar"><?= htmlspecialchars($initials) ?></div>
            <div>
            <div class="vd-user-name"><?= htmlspecialchars($patient['firstname'] ?? $displayName) ?></div>
            <div class="vd-user-role">Patient</div>
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
            <span class="vd-dash-title" id="dashTitle">Home</span>
        </div>
        <div class="vd-topbar-right">
            <span class="vd-topbar-date"><?= $today ?></span>
            <span class="vd-topbar-bell"><i class="ti ti-bell"></i><span class="vd-topbar-bell-dot"></span></span>
            <span class="vd-role-badge">Patient</span>
        </div>
        </div>

        <!-- Content -->
        <div class="vd-dash-content">
        <?php include 'partials/home-content.php'; ?>
        </div>

    </main>

    <!-- Global Toast (for patient partials) -->
    <div id="globalToast" class="vd-toast d-none" role="status" aria-live="polite" aria-atomic="true" style="right:16px; bottom:16px;">
        <div class="vd-toast-body">
            <div class="vd-toast-message" id="globalToastMsg"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../public/js/logout-confirmation.js"></script>
    <script>
        // Expose a global showToast() so all loaded partials can call it
        window.showToast = function(message, success = true, duration = 4000) {
            const toast = document.getElementById('globalToast');
            const msgEl = document.getElementById('globalToastMsg');
            if (!toast || !msgEl) return;
            msgEl.textContent = message;
            toast.classList.remove('d-none', 'vd-toast-success', 'vd-toast-error', 'show');
            toast.classList.add(success ? 'vd-toast-success' : 'vd-toast-error', 'show');
            clearTimeout(window._globalToastTimeout);
            window._globalToastTimeout = setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.classList.add('d-none'), 250);
            }, duration);
        };

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
        const dashContent = document.querySelector('.vd-dash-content');
        const dashTitle = document.getElementById('dashTitle');

        function getPageTitle(page) {
            const nav = document.querySelector(`.vd-nav-item[data-page="${page}"]`);
            return nav ? nav.textContent.trim() : 'Home';
        }

        function setDashboardTitle(page) {
            dashTitle.textContent = getPageTitle(page);
        }

        async function loadPage(page) {
        LoadingUI.showContent(dashContent, { label: 'Loading dashboard…' });
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
        } finally {
            LoadingUI.finishContent(dashContent);
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
            setDashboardTitle(page);
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
            setDashboardTitle(hash);
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

    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content vd-modal-content vd-confirm-modal">
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
