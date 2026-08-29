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
          Check your email<br>for the reset code.
        </div>
      </div>
    </div>

    <!-- RIGHT -->
    <div class="vd-auth-right">
      <div class="vd-auth-form-wrap">

        <div class="vd-auth-heading">
          <div class="vd-auth-title">Enter OTP</div>
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
            <label class="vd-label">6-Digit OTP Code</label>
            <input type="text" name="otp" id="otpInput" class="vd-auth-input vd-otp-input"
              placeholder="_ _ _ _ _ _"
              maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required>
          </div>

          <button type="submit" class="vd-auth-btn" id="otpBtn">
            Verify Code
          </button>
        </form>

        <!-- Resend -->
        <div class="vd-auth-footer mt-3">
          Didn't receive it?
          <a href="#" id="resendBtn">Resend OTP</a>
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
    let countdown = 60;
    const timerEl  = document.getElementById('resendTimer');
    const resendBtn = document.getElementById('resendBtn');

    function startTimer() {
      resendBtn.style.pointerEvents = 'none';
      resendBtn.style.opacity       = '0.4';
      timerEl.textContent = ` (${countdown}s)`;

      const interval = setInterval(() => {
        countdown--;
        timerEl.textContent = ` (${countdown}s)`;
        if (countdown <= 0) {
          clearInterval(interval);
          timerEl.textContent          = '';
          resendBtn.style.pointerEvents = 'auto';
          resendBtn.style.opacity       = '1';
          countdown = 60;
        }
      }, 1000);
    }

    startTimer();

    resendBtn.addEventListener('click', async (e) => {
      e.preventDefault();
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
        if (result.success) {
          LoadingUI.setButton(resendBtn, false);
          document.getElementById('otpSuccess').textContent = 'New OTP sent!';
          document.getElementById('otpSuccess').classList.remove('d-none');
          startTimer();
        }
      } catch (err) {
        LoadingUI.setButton(resendBtn, false);
        console.error(err);
      }
    });

    // Verify OTP
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
          errEl.textContent = result.message;
          errEl.classList.remove('d-none');
          btn.textContent = 'Verify Code';
          LoadingUI.setButton(btn, false);
          btn.disabled    = false;
        }
      } catch (err) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.classList.remove('d-none');
        btn.textContent = 'Verify Code';
        LoadingUI.setButton(btn, false);
        btn.disabled    = false;
      }
    });

    // Only allow numbers in OTP input
    document.getElementById('otpInput').addEventListener('input', function () {
      this.value = this.value.replace(/[^0-9]/g, '');
    });
  </script>

</body>
</html>
