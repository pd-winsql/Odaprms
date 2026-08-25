<?php
session_start();
require_once '../helpers/siteBranding.php';
$branding = vdLoadSiteBranding();

if (isset($_SESSION['user_id'])) {
    header('Location: /Capstone System/index.php');
    exit;
}

if (!isset($_SESSION['pending_registration'])) {
    header('Location: /Capstone System/apps/views/register.php');
    exit;
}

$email = $_SESSION['pending_registration']['email'] ?? '';
if (!$email) {
    header('Location: /Capstone System/apps/views/register.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email | Dr. Aprille Ventura Clinica Dental</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link rel="stylesheet" href="../../public/css/auth.css?v=20260813-inverse-logo">
    <link rel="stylesheet" href="../../public/css/loading.css">
    <script src="../../public/js/loading.js" defer></script>
</head>
<body class="vd-auth-body">

    <div class="vd-auth-split">

        <!-- LEFT -->
        <div class="vd-auth-left">
        <div class="vd-auth-geo vd-geo-1"></div>
        <div class="vd-auth-geo vd-geo-2"></div>
        <div class="vd-auth-geo vd-geo-3"></div>
        <div class="vd-auth-sq vd-sq-1"></div>
        <div class="vd-auth-sq vd-sq-2"></div>
        <div class="vd-auth-brand">
            <?= vdRenderSiteBranding($branding, '../../public/assets', 'auth') ?>
            <div class="vd-auth-tagline">
            One last step —<br>verify your email address.
            </div>
        </div>
        </div>

        <!-- RIGHT -->
        <div class="vd-auth-right">
        <div class="vd-auth-form-wrap">

            <div class="vd-auth-heading">
            <div class="vd-auth-title">Verify your email</div>
            <div class="vd-auth-sub">
                We sent a 6-digit code to<br>
                <strong><?= htmlspecialchars($email) ?></strong>
            </div>
            </div>

            <div id="otpError"   class="vd-auth-error   d-none"></div>
            <div id="otpSuccess" class="vd-auth-success d-none"></div>

            <form id="otpForm" class="vd-auth-form" novalidate>
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

            <div class="vd-auth-group">
                <label class="vd-label">6-Digit Verification Code</label>
                <input type="text" name="otp" id="otpInput" class="vd-auth-input vd-otp-input"
                placeholder="_ _ _ _ _ _"
                maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required>
            </div>

            <button type="submit" class="vd-auth-btn" id="otpBtn">
                Verify &amp; Create Account
            </button>
            </form>

            <div class="vd-auth-footer mt-3">
            Didn't receive it?
            <a href="#" id="resendBtn">Resend code</a>
            <span id="resendTimer" class="vd-resend-timer"></span>
            </div>

            <div class="vd-auth-footer mt-2">
            <a href="register.php?edit=1">← Edit registration details</a>
            </div>

        </div>
        </div>

    </div>

    <script>
        const CONTROLLER = '/Capstone System/apps/controllers/userController.php';

        // ── Resend timer ──
        const timerEl   = document.getElementById('resendTimer');
        const resendBtn = document.getElementById('resendBtn');
        const resendCooldownKey = 'registerOtpResendAvailableAt';
        let resendInterval = null;
        let resendInFlight = false;

        function setResendEnabled(enabled) {
            resendBtn.style.pointerEvents = enabled ? 'auto' : 'none';
            resendBtn.style.opacity = enabled ? '1' : '0.4';
            resendBtn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        }

        function saveCooldown(availableAt) {
            try {
                sessionStorage.setItem(resendCooldownKey, String(availableAt));
            } catch (err) {
                // The in-memory timer still works when browser storage is unavailable.
            }
        }

        function updateTimer(availableAt) {
            const secondsRemaining = Math.max(0, Math.ceil((availableAt - Date.now()) / 1000));
            if (secondsRemaining <= 0) {
                if (resendInterval !== null) {
                    clearInterval(resendInterval);
                    resendInterval = null;
                }
                timerEl.textContent = '';
                setResendEnabled(true);
                // Keep an expired marker so refreshing the page does not start
                // another one-minute countdown.
                saveCooldown(0);
                return;
            }

            setResendEnabled(false);
            timerEl.textContent = ` (${secondsRemaining}s)`;
        }

        function startTimer(availableAt = Date.now() + 60000, persist = true) {
            if (resendInterval !== null) {
                clearInterval(resendInterval);
            }
            if (persist) saveCooldown(availableAt);
            updateTimer(availableAt);
            if (availableAt > Date.now()) {
                resendInterval = setInterval(() => updateTimer(availableAt), 250);
            }
        }

        let savedCooldown = null;
        try {
            const storedCooldown = sessionStorage.getItem(resendCooldownKey);
            savedCooldown = storedCooldown === null ? null : Number(storedCooldown);
        } catch (err) {
            // Fall back to a fresh in-memory timer.
        }

        if (savedCooldown === null || !Number.isFinite(savedCooldown)) {
            startTimer();
        } else if (savedCooldown > Date.now()) {
            startTimer(savedCooldown, false);
        } else {
            timerEl.textContent = '';
            resendBtn.style.pointerEvents = 'auto';
            resendBtn.style.opacity       = '1';
            resendBtn.setAttribute('aria-disabled', 'false');
        }

        // ── Resend — uses dedicated action, no password needed ──
        resendBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        if (resendInFlight || resendBtn.getAttribute('aria-disabled') === 'true') return;

        resendInFlight = true;
        setResendEnabled(false);
        const errEl = document.getElementById('otpError');
        const sucEl = document.getElementById('otpSuccess');
        errEl.classList.add('d-none');
        sucEl.classList.add('d-none');

        const formData = new FormData();
        formData.append('action', 'resendRegisterOTP');
        LoadingUI.setButton(resendBtn, true, 'Sending…');

        try {
            const res    = await fetch(CONTROLLER, { method: 'POST', body: formData });
            const result = await res.json();

            if (result.success) {
            resendInFlight = false;
            LoadingUI.setButton(resendBtn, false);
            sucEl.textContent = 'New code sent!';
            sucEl.classList.remove('d-none');
            startTimer();
            } else {
            resendInFlight = false;
            LoadingUI.setButton(resendBtn, false);
            setResendEnabled(true);
            errEl.textContent = result.message;
            errEl.classList.remove('d-none');
            }
        } catch (err) {
            resendInFlight = false;
            LoadingUI.setButton(resendBtn, false);
            setResendEnabled(true);
            errEl.textContent = 'Network error. Please try again.';
            errEl.classList.remove('d-none');
        }
        });

        // ── Verify OTP ──
        document.getElementById('otpForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const btn   = document.getElementById('otpBtn');
        const errEl = document.getElementById('otpError');
        const sucEl = document.getElementById('otpSuccess');
        errEl.classList.add('d-none');
        sucEl.classList.add('d-none');

        const otp = document.getElementById('otpInput').value.trim();
        if (otp.length !== 6 || isNaN(otp)) {
            errEl.textContent = 'Please enter a valid 6-digit code.';
            errEl.classList.remove('d-none');
            return;
        }

        btn.textContent = 'Verifying…';
        btn.disabled    = true;
        LoadingUI.setButton(btn, true, 'Verifying…');

        const formData = new FormData(this);
        formData.append('action', 'verifyRegisterOTP');

        try {
            const res    = await fetch(CONTROLLER, { method: 'POST', body: formData });
            const result = await res.json();

            if (result.success) {
            try {
                sessionStorage.removeItem('pendingRegistrationPasswords');
                sessionStorage.removeItem(resendCooldownKey);
            } catch (err) {
                // Account creation is complete even when browser storage is unavailable.
            }
            sucEl.textContent = 'Account created! Opening appointment booking…';
            sucEl.classList.remove('d-none');
            setTimeout(() => {
                window.location.href = result.redirect || '/Capstone System/apps/views/patient/dashboard.php#booking-content.php';
            }, 1500);
            } else {
            errEl.textContent = result.message;
            errEl.classList.remove('d-none');
            btn.textContent = 'Verify & Create Account';
            LoadingUI.setButton(btn, false);
            btn.disabled    = false;
            }
        } catch (err) {
            errEl.textContent = 'Network error. Please try again.';
            errEl.classList.remove('d-none');
            btn.textContent = 'Verify & Create Account';
            LoadingUI.setButton(btn, false);
            btn.disabled    = false;
        }
        });

        // Only allow numbers
        document.getElementById('otpInput').addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>

</body>
</html>
