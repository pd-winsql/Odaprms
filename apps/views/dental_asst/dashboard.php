<?php
session_start();
require_once '../../../config/conn.php';
require_once '../../models/appointmentModel.php';
require_once '../../models/clinicModel.php';
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
if (!in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    header('Location: ../patient/dashboard.php');
    exit;
}

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$db   = new Database();
$conn = $db->connect();

$appointmentModel = new Appointment($conn);
$clinicModel      = new Clinic($conn);

$upcoming = $appointmentModel->getAllUpcomingWithStatus();
$latestAppointmentId = $appointmentModel->getLatestAppointmentId();
$depositFeedVersion = $appointmentModel->getDepositFeedVersion();
$clinics  = $clinicModel->getAllClinics();
$branding = vdLoadSiteBranding($conn);

$displayName = $_SESSION['display_name'] ?? $_SESSION['email'] ?? 'Dental Assistant';
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_filter(explode(' ', $displayName)))));
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../../../public/css/styles.css?v=<?= filemtime(__DIR__ . '/../../../public/css/styles.css') ?>">
    <link rel="stylesheet" href="../../../public/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../../../public/css/dashboard.css') ?>">
    <link rel="stylesheet" href="../../../public/css/ui-refinements.css?v=<?= filemtime(__DIR__ . '/../../../public/css/ui-refinements.css') ?>">
    <link rel="stylesheet" href="../../../public/css/loading.css?v=20260822-dashboard-skeletons-1">
    <script src="../../../public/js/loading.js?v=20260822-dashboard-skeletons-1" defer></script>
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
            <a href="#" class="vd-nav-item active" data-page="dashboard-content.php">
                <span class="vd-nav-icon"><i class="ti ti-layout-dashboard"></i></span> Dashboard
            </a>
            <a href="#" class="vd-nav-item" data-page="appointment-content.php">
                <span class="vd-nav-icon"><i class="ti ti-calendar"></i></span> Appointments
            </a>
            <a href="#" class="vd-nav-item" data-page="payment-review-content.php">
                <span class="vd-nav-icon"><i class="ti ti-receipt"></i></span> Deposit Records
            </a>
            <a href="#" class="vd-nav-item" data-page="cash-billing-content.php">
                <span class="vd-nav-icon"><i class="ti ti-cash"></i></span> Billing Records
            </a>
            <a href="#" class="vd-nav-item" data-page="logbook-content.php">
                <span class="vd-nav-icon"><i class="ti ti-book"></i></span> Logbook
            </a>
            <a href="#" class="vd-nav-item" data-page="patient-content.php">
                <span class="vd-nav-icon"><i class="ti ti-users"></i></span> Patients
            </a>

            <div class="vd-nav-section">Manage</div>
            <a href="#" class="vd-nav-item" data-page="services-content.php">
                <span class="vd-nav-icon"><i class="ti ti-building"></i></span> Services
            </a>
            <a href="#" class="vd-nav-item" data-page="clinic-content.php">
                <span class="vd-nav-icon"><i class="ti ti-building"></i></span> Clinics
            </a>
            <a href="#" class="vd-nav-item" data-page="schedule-content.php">
                <span class="vd-nav-icon"><i class="ti ti-clock"></i></span> Schedules
            </a>

            <div class="vd-nav-section">Account</div>
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
                    <div class="vd-user-name"><?= htmlspecialchars($displayName) ?></div>
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
                <button class="vd-menu-toggle" id="menuToggle" aria-label="Open navigation menu"
                    aria-controls="sidebar" aria-expanded="false">
                    <i class="ti ti-menu-2"></i>
                </button>
                <span class="vd-dash-title" id="dashTitle">Dashboard</span>
            </div>
            <div class="vd-topbar-right">
                <div class="vd-topbar-datetime">
                    <time class="vd-topbar-date" id="vdTopbarDate"><?= $today ?></time>
                    <time class="vd-topbar-clock" id="vdTopbarClock" aria-label="Current time in Manila">--:--:-- --</time>
                </div>
                <span class="vd-topbar-bell"><i class="ti ti-bell"></i><span class="vd-topbar-bell-dot"></span></span>
                <span class="vd-role-badge"><?= htmlspecialchars($_SESSION['user_role']) ?></span>
            </div>
        </div>

        <!-- Content -->
        <div class="vd-dash-content">
            <?php include 'partials/dashboard-content.php'; ?>
        </div><!-- /vd-dash-content -->
    </main>

    <!-- Global Toast (for dental assistant partials) -->
    <div id="globalToast" class="vd-toast d-none" role="status" aria-live="polite" aria-atomic="true" style="right:16px; bottom:16px;">
        <div class="vd-toast-body">
            <div class="vd-toast-message" id="globalToastMsg"></div>
        </div>
    </div>

    <?php include __DIR__ . '/../shared/staff-action-modal.php'; ?>

    <script src="../../../public/js/bootstrap.bundle.min.js"></script>
    <script src="../../../public/js/action-modal.js?v=3"></script>
    <script src="../../../public/js/logout-confirmation.js"></script>
    <script src="../../../public/js/dashboard-tables.js?v=<?= filemtime(__DIR__ . '/../../../public/js/dashboard-tables.js') ?>"></script>
    <script src="../../../public/js/dashboard-topbar.js?v=20260824-2"></script>
    <script src="../../../public/js/dashboard-sidebar.js?v=<?= filemtime(__DIR__ . '/../../../public/js/dashboard-sidebar.js') ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
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


        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
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

        /* Navigation */
        const navItems = document.querySelectorAll('.vd-nav-item');
        const dashContent = document.querySelector('.vd-dash-content');
        const dashTitle = document.getElementById('dashTitle');

        function getPageTitle(page) {
            const nav = document.querySelector(`.vd-nav-item[data-page="${page}"]`);
            return nav ? nav.textContent.trim() : 'Dashboard';
        }

        function setDashboardTitle(page) {
            dashTitle.textContent = getPageTitle(page);
        }

        async function loadpage(page, options = {}) {
            const silent = options.silent === true;
            let loaded = false;
            if (!silent) LoadingUI.showContent(dashContent, {
                label: 'Loading dashboard…',
                page
            });
            try {
                const response = await fetch(`partials/${page}`, {
                    cache: 'no-store'
                });
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
                loaded = true;
            } catch (error) {
                dashContent.innerHTML = `<div class="vd-empty-state">Error loading content.</div>`;
                console.error('Error fetching page:', error);
            } finally {
                if (!silent) LoadingUI.finishContent(dashContent);
            }
            return loaded;
        }

        // Poll for new appointments or deposit changes and silently refresh the
        // relevant staff view while preserving filters and avoiding open modals.
        let lastKnownAppointmentId = <?= (int) $latestAppointmentId ?>;
        let lastKnownDepositVersion = <?= json_encode($depositFeedVersion) ?>;
        let appointmentRefreshInFlight = false;

        function appointmentViewState() {
            return {
                view: dashContent.querySelector('.vd-toggle-btn.active')?.dataset.view || 'upcoming',
                upcomingStatus: dashContent.querySelector('#upcomingStatusToggles .active')?.dataset.status || '',
                pastStatus: dashContent.querySelector('#pastStatusToggles .active')?.dataset.status || '',
                upcomingFrom: dashContent.querySelector('#filterDateFromUpcoming')?.value || '',
                upcomingTo: dashContent.querySelector('#filterDateToUpcoming')?.value || '',
                pastFrom: dashContent.querySelector('#filterDateFromPast')?.value || '',
                pastTo: dashContent.querySelector('#filterDateToPast')?.value || ''
            };
        }

        function restoreAppointmentViewState(state) {
            dashContent.querySelector(`.vd-toggle-btn[data-view="${state.view}"]`)?.click();
            const restoreFilter = (key, suffix, status, from, to) => {
                dashContent.querySelector(`#${key}StatusToggles [data-status="${CSS.escape(status)}"]`)?.click();
                const fromInput = dashContent.querySelector(`#filterDateFrom${suffix}`);
                const toInput = dashContent.querySelector(`#filterDateTo${suffix}`);
                if (fromInput) fromInput.value = from;
                if (toInput) {
                    toInput.value = to;
                    toInput.dispatchEvent(new Event('change'));
                }
            };
            restoreFilter('upcoming', 'Upcoming', state.upcomingStatus, state.upcomingFrom, state.upcomingTo);
            restoreFilter('past', 'Past', state.pastStatus, state.pastFrom, state.pastTo);
        }

        function depositViewState() {
            return {
                status: dashContent.querySelector('#depositStatusFilter')?.value || '',
                from: dashContent.querySelector('#depositDateFrom')?.value || '',
                to: dashContent.querySelector('#depositDateTo')?.value || ''
            };
        }

        function restoreDepositViewState(state) {
            const status = dashContent.querySelector('#depositStatusFilter');
            const from = dashContent.querySelector('#depositDateFrom');
            const to = dashContent.querySelector('#depositDateTo');
            if (status) status.value = state.status;
            if (from) from.value = state.from;
            if (to) {
                to.value = state.to;
                to.dispatchEvent(new Event('change'));
            }
        }

        async function checkForNewAppointments() {
            if (document.hidden || appointmentRefreshInFlight) return;
            const currentPage = document.querySelector('.vd-nav-item.active')?.dataset.page;
            try {
                const response = await fetch('../../controllers/appointmentController.php?action=latestAppointment', {
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json'
                    }
                });
                if (!response.ok) return;
                const result = await response.json();
                const latestId = Number(result.latest_appointment_id || 0);
                const depositVersion = String(result.deposit_feed_version || '0:0:0');
                const hasNewAppointment = latestId > lastKnownAppointmentId;
                const hasDepositChange = depositVersion !== lastKnownDepositVersion;
                if (!result.success || (!hasNewAppointment && !hasDepositChange)) return;

                if (!['appointment-content.php', 'payment-review-content.php'].includes(currentPage)) {
                    lastKnownAppointmentId = latestId;
                    lastKnownDepositVersion = depositVersion;
                    return;
                }
                if (dashContent.querySelector('.modal.show')) return;

                appointmentRefreshInFlight = true;
                const state = currentPage === 'appointment-content.php' ?
                    appointmentViewState() :
                    depositViewState();
                const refreshed = await loadpage(currentPage, {
                    silent: true
                });
                if (!refreshed) return;
                if (currentPage === 'appointment-content.php') restoreAppointmentViewState(state);
                else restoreDepositViewState(state);
                lastKnownAppointmentId = latestId;
                lastKnownDepositVersion = depositVersion;
                const message = hasDepositChange && !hasNewAppointment ?
                    'A deposit record changed. The list has been updated.' :
                    'A new appointment was added. The list has been updated.';
                window.showToast(message, true);
            } catch (error) {
                console.debug('Automatic appointment refresh skipped:', error);
            } finally {
                appointmentRefreshInFlight = false;
            }
        }

        window.setInterval(checkForNewAppointments, 10000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) checkForNewAppointments();
        });

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

                await loadpage(page);
            });
        });

        window.addEventListener('DOMContentLoaded', async () => {
            const hash = window.location.hash.replace('#', '');
            if (hash) {
                const matchingNav = document.querySelector(`[data-page="${hash}"]`);
                if (matchingNav) {
                    navItems.forEach(i => i.classList.remove('active'));
                    matchingNav.classList.add('active');
                    setDashboardTitle(hash);
                    await loadpage(hash);
                }
            }
        });

        // Prevent back button after logout
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                window.location.reload();
            }
        });
    </script>

    <script>
        window.emailNotificationDeliveryConfig = {
            endpoint: '../../controllers/emailNotificationController.php',
            csrfToken: <?= json_encode($_SESSION['csrf_token']) ?>
        };
    </script>
    <script src="../../../public/js/email-notification-delivery.js?v=1"></script>

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
