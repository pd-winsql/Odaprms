<?php
session_start();
require_once '../helpers/siteBranding.php';
$branding = vdLoadSiteBranding();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$email = $_GET['email'] ?? '';
if (!$email) {
    header('Location: forgot-pass.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify OTP | Dr. Aprille Ventura Clinica Dental</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
  <link rel="stylesheet" href="../../public/css/styles.css?v=<?= filemtime(__DIR__ . '/../../public/css/styles.css') ?>">
  <link rel="stylesheet" href="../../public/css/auth.css?v=<?= filemtime(__DIR__ . '/../../public/css/auth.css') ?>">
    <link rel="stylesheet" href="../../public/css/loading.css">
    <script src="../../public/js/loading.js" defer></script>
</head>
<body class="vd-auth-body vd-auth-otp-page">

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
          Check your email<br>for the reset code.
        </div>
      </div>
    </div>

    <!-- RIGHT -->
    <div class="vd-auth-right">
      <div class="vd-auth-form-wrap vd-auth-otp">

        <div class="vd-auth-heading">
          <h1 class="vd-auth-title">Enter OTP</h1>
          <div class="vd-auth-sub">
            We sent a 6-digit code to<br>
            <strong><?= htmlspecialchars($email) ?></strong>
          </div>
        </div>

        <form id="otpForm" class="vd-auth-form" novalidate>
          <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

          <div class="vd-auth-group">
            <label class="vd-label" for="otpInput">Verification code</label>
            <p class="vd-otp-help" id="otpHelp">Enter the six-digit code. It expires after 10 minutes.</p>
            <div class="vd-otp-control" id="otpControl">
              <input type="text" name="otp" id="otpInput" class="vd-otp-input"
                maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                autocomplete="one-time-code" enterkeyhint="done"
                aria-describedby="otpHelp otpError" aria-invalid="false" autofocus required>
              <div class="vd-otp-slots" aria-hidden="true">
                <?php for ($slot = 0; $slot < 6; $slot++): ?><span class="vd-otp-slot"></span><?php endfor; ?>
              </div>
            </div>
            <div id="otpError" class="vd-auth-error d-none" role="alert" aria-live="assertive"></div>
            <div id="otpSuccess" class="vd-auth-success d-none" role="status" aria-live="polite"></div>
          </div>

          <button type="submit" class="vd-auth-btn" id="otpBtn" disabled>
            Verify Code
          </button>
        </form>

        <!-- Resend -->
        <div class="vd-auth-resend">
          <span class="vd-auth-resend-copy">Didn't receive the email?</span>
          <button type="button" class="vd-resend-button" id="resendBtn" disabled>Resend verification code</button>
          <span id="resendTimer" class="vd-resend-timer"></span>
        </div>

        <div class="vd-auth-footer mt-2">
          <a href="forgot-pass.php?restart=1">← Use another email</a>
        </div>

      </div>
    </div>

  </div>

  <script>
    // Resend timer
    const timerEl  = document.getElementById('resendTimer');
    const resendBtn = document.getElementById('resendBtn');
    const otpInput = document.getElementById('otpInput');
    const otpControl = document.getElementById('otpControl');
    const otpSlots = Array.from(otpControl.querySelectorAll('.vd-otp-slot'));
    const otpBtn = document.getElementById('otpBtn');
    let resendInterval = null;
    let verificationInFlight = false;

    function syncOtpDisplay() {
      const value = otpInput.value.replace(/[^0-9]/g, '').slice(0, 6);
      if (otpInput.value !== value) otpInput.value = value;

      otpSlots.forEach((slot, index) => {
        slot.textContent = value[index] || '';
        slot.classList.toggle('is-filled', index < value.length);
        slot.classList.toggle('is-active', document.activeElement === otpInput && index === Math.min(value.length, 5));
      });
      otpBtn.disabled = verificationInFlight || value.length !== 6;
    }

    function clearOtpFeedback() {
      const errEl = document.getElementById('otpError');
      otpControl.classList.remove('is-invalid');
      otpInput.setAttribute('aria-invalid', 'false');
      errEl.classList.add('d-none');
      errEl.textContent = '';
    }

    function showOtpError(message) {
      const errEl = document.getElementById('otpError');
      errEl.textContent = message;
      errEl.classList.remove('d-none');
      otpControl.classList.add('is-invalid');
      otpInput.setAttribute('aria-invalid', 'true');
      otpInput.focus();
      syncOtpDisplay();
    }

    function startTimer() {
      if (resendInterval !== null) clearInterval(resendInterval);
      const availableAt = Date.now() + 60000;
      resendBtn.disabled = true;

      const updateTimer = () => {
        const remaining = Math.max(0, Math.ceil((availableAt - Date.now()) / 1000));
        if (remaining <= 0) {
          clearInterval(resendInterval);
          resendInterval = null;
          timerEl.textContent = '';
          resendBtn.disabled = false;
          return;
        }
        const minutes = Math.floor(remaining / 60);
        const seconds = String(remaining % 60).padStart(2, '0');
        timerEl.textContent = `Resend available in ${minutes}:${seconds}`;
      };

      updateTimer();
      resendInterval = setInterval(updateTimer, 250);
    }

    startTimer();

    resendBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      if (resendBtn.disabled) return;
      resendBtn.disabled = true;
      const formData = new FormData();
      // Only this explicit action replaces the currently valid code.
      formData.append('action', 'resendOTP');
      formData.append('email', '<?= htmlspecialchars($email) ?>');
      LoadingUI.setButton(resendBtn, true, 'Sending…');

      try {
        const res    = await fetch('../controllers/passwordResetController.php', {
          method: 'POST', body: formData
        });
        const result = await res.json();
        if (res.ok && result.success) {
          LoadingUI.setButton(resendBtn, false);
          document.getElementById('otpSuccess').textContent = 'New OTP sent!';
          document.getElementById('otpSuccess').classList.remove('d-none');
          startTimer();
        } else {
          LoadingUI.setButton(resendBtn, false);
          resendBtn.disabled = false;
          const errEl = document.getElementById('otpError');
          errEl.textContent = result.message || 'Unable to resend the code. Please try again.';
          errEl.classList.remove('d-none');
        }
      } catch (err) {
        LoadingUI.setButton(resendBtn, false);
        resendBtn.disabled = false;
        const errEl = document.getElementById('otpError');
        errEl.textContent = 'Network error. Please try again.';
        errEl.classList.remove('d-none');
      }
    });

    // Verify OTP
    document.getElementById('otpForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn   = otpBtn;
      const errEl = document.getElementById('otpError');
      const sucEl = document.getElementById('otpSuccess');
      errEl.classList.add('d-none');
      sucEl.classList.add('d-none');
      clearOtpFeedback();

      const otp = otpInput.value.trim();
      if (otp.length !== 6 || isNaN(otp)) {
        showOtpError('Enter the complete six-digit verification code.');
        return;
      }

      verificationInFlight = true;
      syncOtpDisplay();
      LoadingUI.setButton(btn, true, 'Verifying…');

      const formData = new FormData(this);
      formData.append('action', 'verifyOTP');

      try {
        const res    = await fetch('../controllers/passwordResetController.php', {
          method: 'POST', body: formData
        });
        const result = await res.json();

        if (result.success) {
          sucEl.textContent = 'Code verified! Redirecting…';
          sucEl.classList.remove('d-none');
          setTimeout(() => {
            window.location.href = 'reset-pass.php?token=' + result.token;
          }, 1000);
        } else {
          showOtpError(result.message);
          verificationInFlight = false;
          LoadingUI.setButton(btn, false);
          syncOtpDisplay();
        }
      } catch (err) {
        showOtpError('Network error. Please try again.');
        verificationInFlight = false;
        LoadingUI.setButton(btn, false);
        syncOtpDisplay();
      }
    });

    otpInput.addEventListener('input', () => {
      clearOtpFeedback();
      syncOtpDisplay();
    });
    otpInput.addEventListener('focus', syncOtpDisplay);
    otpInput.addEventListener('blur', syncOtpDisplay);
    syncOtpDisplay();
  </script>

</body>
</html>
