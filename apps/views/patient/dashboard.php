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
    header('Location: ../login.php');
    exit;
}
if ($_SESSION['user_role'] !== 'Patient') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$db   = new Database();
$conn = $db->connect();
require_once __DIR__ . '/../../models/depositModel.php';
(new DepositModel($conn))->expireUnpaidAppointments();
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
    <meta name="vd-app-base-url" content="<?= htmlspecialchars(vdAppBaseUrl(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="vd-dashboard-partials" content="apps/views/patient/partials">
    <title>My Account | Dr. Aprille Ventura Clinica Dental</title>
    <script src="<?= htmlspecialchars(vdAppUrl('public/js/app-url.js'), ENT_QUOTES, 'UTF-8') ?>?v=<?= filemtime(__DIR__ . '/../../../public/js/app-url.js') ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="../../../public/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../public/css/styles.css?v=<?= filemtime(__DIR__ . '/../../../public/css/styles.css') ?>">
    <link rel="stylesheet" href="../../../public/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../../../public/css/dashboard.css') ?>">
    <link rel="stylesheet" href="../../../public/css/patient-dashboard.css?v=<?= filemtime(__DIR__ . '/../../../public/css/patient-dashboard.css') ?>">
    <link rel="stylesheet" href="../../../public/css/ui-refinements.css?v=<?= filemtime(__DIR__ . '/../../../public/css/ui-refinements.css') ?>">
    <link rel="stylesheet" href="../../../public/css/deposit-ocr.css?v=<?= filemtime(__DIR__ . '/../../../public/css/deposit-ocr.css') ?>">
    <link rel="stylesheet" href="../../../public/css/loading.css?v=20260822-dashboard-skeletons-1">
    <script src="../../../public/js/bootstrap.bundle.min.js?v=5.3.8"></script>
    <script src="../../../public/js/loading.js?v=20260822-dashboard-skeletons-1" defer></script>
    <script src="../../../public/js/deposit-ocr.js?v=<?= filemtime(__DIR__ . '/../../../public/js/deposit-ocr.js') ?>" defer></script>
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
            <span class="vd-nav-icon"><i class="ti ti-receipt"></i></span> Deposit
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
        <a href="#" class="vd-nav-item" data-logout-confirm="<?= htmlspecialchars(vdAppUrl('apps/controllers/userController.php?action=logout'), ENT_QUOTES, 'UTF-8') ?>">
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
            <button class="vd-menu-toggle" id="menuToggle" aria-label="Open navigation menu"
                aria-controls="sidebar" aria-expanded="false">
            <i class="ti ti-menu-2"></i>
            </button>
            <span class="vd-dash-title" id="dashTitle">Home</span>
        </div>
        <div class="vd-topbar-right">
            <span class="vd-topbar-date"><?= $today ?></span>
            <?php include __DIR__ . '/../shared/patient-notification-center.php'; ?>
            <span class="vd-role-badge">Patient</span>
        </div>
        </div>

        <!-- Content -->
        <div class="vd-dash-content">
        <?php include 'partials/home-content.php'; ?>
        </div>

    </main>

    <!-- Global Toast (for patient partials) -->
    <div id="globalToast" class="vd-toast vd-patient-toast d-none" role="status" aria-live="polite" aria-atomic="true">
        <span class="vd-toast-icon" aria-hidden="true"><i class="ti ti-info-circle"></i></span>
        <div class="vd-toast-body">
            <div class="vd-toast-message" id="globalToastMsg"></div>
        </div>
        <button type="button" class="vd-toast-close" aria-label="Dismiss notification"><i class="ti ti-x" aria-hidden="true"></i></button>
    </div>

    <?php include __DIR__ . '/../shared/staff-action-modal.php'; ?>

    <script src="../../../public/js/action-modal.js?v=<?= filemtime(__DIR__ . '/../../../public/js/action-modal.js') ?>"></script>
    <script src="../../../public/js/logout-confirmation.js"></script>
    <script src="../../../public/js/patient-appointment-notifications.js?v=<?= filemtime(__DIR__ . '/../../../public/js/patient-appointment-notifications.js') ?>"></script>
    <script>
        // Expose a global showToast() so all loaded partials can call it
        window.showToast = function(message, success = true, duration = 4000) {
            const toast = document.getElementById('globalToast');
            const msgEl = document.getElementById('globalToastMsg');
            const iconEl = toast?.querySelector('.vd-toast-icon i');
            if (!toast || !msgEl) return;
            msgEl.textContent = message;
            if (iconEl) iconEl.className = success ? 'ti ti-circle-check' : 'ti ti-alert-circle';
            toast.setAttribute('role', success ? 'status' : 'alert');
            toast.setAttribute('aria-live', success ? 'polite' : 'assertive');
            toast.classList.remove('d-none', 'vd-toast-success', 'vd-toast-error', 'show');
            toast.classList.add(success ? 'vd-toast-success' : 'vd-toast-error', 'show');
            clearTimeout(window._globalToastTimeout);
            clearTimeout(window._globalToastTransitionTimeout);
            window._globalToastTimeout = setTimeout(() => {
                toast.classList.remove('show');
                window._globalToastTransitionTimeout = setTimeout(() => toast.classList.add('d-none'), 250);
            }, duration);
        };

        document.querySelector('#globalToast .vd-toast-close')?.addEventListener('click', () => {
            const toast = document.getElementById('globalToast');
            clearTimeout(window._globalToastTimeout);
            clearTimeout(window._globalToastTransitionTimeout);
            toast?.classList.remove('show');
            window._globalToastTransitionTimeout = setTimeout(() => toast?.classList.add('d-none'), 250);
        });

        const sidebar    = document.getElementById('sidebar');
        const overlay    = document.getElementById('sidebarOverlay');
        const menuToggle = document.getElementById('menuToggle');

        function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        menuToggle.setAttribute('aria-expanded', 'true');
        menuToggle.setAttribute('aria-label', 'Close navigation menu');
        }
        function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Open navigation menu');
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

        async function loadPage(page, options = {}) {
        const silent = options.silent === true;
        let loaded = false;
        if (!silent) LoadingUI.showContent(dashContent, { label: 'Loading dashboard…', page });
        try {
            const response = await fetch(window.vdDashboardPartialUrl(page), { cache: 'no-store' });
            if (!response.ok) throw new Error('Failed to load');
            const html = await response.text();
            dashContent.innerHTML = html;

            dashContent.querySelectorAll('script').forEach(oldScript => {
            const newScript = document.createElement('script');
            newScript.textContent = oldScript.textContent;
            document.body.appendChild(newScript);
            oldScript.remove();
            });

            if (!silent) {
                closeSidebar();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            loaded = true;
        } catch (err) {
            if (!silent) dashContent.innerHTML = '<div class="vd-empty-state">Error loading content.</div>';
            console.error(err);
        } finally {
            if (!silent) {
                LoadingUI.finishContent(dashContent);
                if (loaded) LoadingUI.revealContent(dashContent);
            }
        }
        return loaded;
        }

        const appointmentAutoRefreshPages = new Set([
            'home-content.php',
            'billing-content.php',
            'history-content.php'
        ]);
        const pendingAppointmentRefreshPages = new Set();
        let appointmentPageRefreshInFlight = false;

        async function refreshCurrentAppointmentPage() {
            if (appointmentPageRefreshInFlight || document.hidden || document.querySelector('.modal.show')) return;
            const currentPage = document.querySelector('.vd-nav-item.active')?.dataset.page;
            if (!appointmentAutoRefreshPages.has(currentPage) || !pendingAppointmentRefreshPages.has(currentPage)) return;

            appointmentPageRefreshInFlight = true;
            try {
                if (await loadPage(currentPage, { silent: true })) {
                    pendingAppointmentRefreshPages.delete(currentPage);
                }
            } finally {
                appointmentPageRefreshInFlight = false;
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
            if (await loadPage(page)) pendingAppointmentRefreshPages.delete(page);
        });
        });

        window.PatientAppointmentNotifications?.create({
            userId: <?= (int) $_SESSION['user_id'] ?>,
            endpoint: window.vdAppUrl('apps/controllers/appointmentController.php?action=patientNotificationSnapshot'),
            pollInterval: 10000,
            buttonId: 'patientNotificationButton',
            panelId: 'patientNotificationPanel',
            listId: 'patientNotificationList',
            emptyId: 'patientNotificationEmpty',
            caughtUpId: 'patientNotificationCaughtUp',
            markAllId: 'patientNotificationMarkAll',
            dotId: 'patientNotificationDot',
            onNavigate(destination) {
                document.querySelector(`.vd-nav-item[data-page="${destination}"]`)?.click();
            },
            onStatusChange(destinations) {
                destinations
                    .filter(destination => appointmentAutoRefreshPages.has(destination))
                    .forEach(destination => pendingAppointmentRefreshPages.add(destination));
                void refreshCurrentAppointmentPage();
            }
        });

        document.addEventListener('hidden.bs.modal', () => void refreshCurrentAppointmentPage());
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) void refreshCurrentAppointmentPage();
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
            if (await loadPage(hash)) pendingAppointmentRefreshPages.delete(hash);
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

<?php require __DIR__ . '/../shared/clinic-chat-shell.php'; ?>
</body>
</html>
