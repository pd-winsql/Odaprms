<?php
session_start();
require_once '../helpers/siteBranding.php';
$branding = vdLoadSiteBranding();
if (isset($_SESSION['user_id'])) {
    header('Location: /Capstone System/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | Dr. Aprille Ventura Clinica Dental</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
  <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
  <link rel="stylesheet" href="../../public/css/styles.css">
  <link rel="stylesheet" href="../../public/css/auth.css?v=20260825-password-toggle">
    <link rel="stylesheet" href="../../public/css/loading.css">
    <script src="../../public/js/loading.js" defer></script>
</head>
<body class="vd-auth-body">

  <div class="vd-auth-split vd-register-split">

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
          Create an account to manage<br>your appointments with ease.
        </div>
      </div>
    </div>

    <!-- RIGHT -->
    <div class="vd-auth-right">
      <div class="vd-auth-form-wrap vd-register-wrap">

        <div class="vd-auth-heading">
          <div class="vd-auth-title">Create account</div>
          <div class="vd-auth-sub">Fill in your details to get started.</div>
        </div>

        <div id="registerError"   class="vd-auth-error   d-none"></div>
        <div id="registerSuccess" class="vd-auth-success d-none"></div>

        <form id="registerForm" class="vd-auth-form vd-register-grid" novalidate>
          <div class="vd-auth-group"><label class="vd-label">First Name</label><input type="text" name="firstname" class="vd-auth-input" required autocomplete="given-name"></div>
          <div class="vd-auth-group"><label class="vd-label">Middle Name <span class="text-muted">(optional)</span></label><input type="text" name="middlename" class="vd-auth-input" autocomplete="additional-name"></div>
          <div class="vd-auth-group"><label class="vd-label">Last Name</label><input type="text" name="lastname" class="vd-auth-input" required autocomplete="family-name"></div>
          <div class="vd-auth-group"><label class="vd-label">Suffix <span class="text-muted">(optional)</span></label><input type="text" name="suffix" class="vd-auth-input" placeholder="Jr., Sr., III"></div>
          <div class="vd-auth-group"><label class="vd-label">Birthdate</label><input type="date" name="birthdate" class="vd-auth-input" max="<?= date('Y-m-d') ?>" required autocomplete="bday"></div>
          <div class="vd-auth-group">
            <label class="vd-label">Gender</label>
            <select name="gender" class="vd-auth-input" required>
              <option value="" selected disabled>Select gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Prefer not to say">Prefer not to say</option>
            </select>
          </div>
          <div class="vd-auth-group"><label class="vd-label">Contact Number</label><input type="tel" name="phone_number" class="vd-auth-input" placeholder="09XXXXXXXXX" required autocomplete="tel"></div>
          <div class="vd-auth-group">
            <label class="vd-label">Email Address</label>
            <input type="email" name="email" class="vd-auth-input"
              placeholder="email@example.com" required autocomplete="email">
          </div>
          <div class="vd-auth-group">
            <label class="vd-label">Password</label>
            <div class="vd-auth-input-wrap">
              <input type="password" name="password" id="regPassword" class="vd-auth-input"
                placeholder="Min. 8 characters" required autocomplete="new-password">
              <button type="button" class="vd-pw-toggle" id="toggleRegPw" aria-label="Show password">
                <i class="ti ti-eye" id="regEyeIcon"></i>
              </button>
            </div>
            <div class="vd-auth-hint">Use at least 8 characters with both letters and numbers.</div>
          </div>
          <div class="vd-auth-group">
            <label class="vd-label">Confirm Password</label>
            <div class="vd-auth-input-wrap">
              <input type="password" name="confirm_password" id="regConfirmPassword" class="vd-auth-input"
                placeholder="Re-enter password" required autocomplete="new-password">
              <button type="button" class="vd-pw-toggle" id="toggleRegConfirmPw" aria-label="Show password">
                <i class="ti ti-eye" id="regConfirmEyeIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="vd-auth-btn vd-register-span-2" id="registerBtn">
            Create Account
          </button>
        </form>

        <div class="vd-auth-footer">
          Already have an account? <a href="login.php">Sign in</a>
        </div>

        <div class="vd-auth-footer mt-2">
          <a href="../../index.php">← Back to home</a>
        </div>

      </div>
    </div>

  </div>

  <script>
    function togglePassword(inputId, iconId) {
      const input = document.getElementById(inputId);
      const icon  = document.getElementById(iconId);
      const isHidden = input.type === 'password';
      input.type     = isHidden ? 'text' : 'password';
      icon.className = isHidden ? 'ti ti-eye-off' : 'ti ti-eye';
    }

    document.getElementById('toggleRegPw').addEventListener('click', function () {
      togglePassword('regPassword', 'regEyeIcon');
    });
    document.getElementById('toggleRegConfirmPw').addEventListener('click', function () {
      togglePassword('regConfirmPassword', 'regConfirmEyeIcon');
    });

    document.getElementById('registerForm').addEventListener('submit', async function (e) {
      e.preventDefault();

      const btn   = document.getElementById('registerBtn');
      const errEl = document.getElementById('registerError');
      const sucEl = document.getElementById('registerSuccess');
      errEl.classList.add('d-none');
      sucEl.classList.add('d-none');

      const formData = new FormData(this);
      const pw       = formData.get('password');
      const cpw      = formData.get('confirm_password');

      if (pw !== cpw) {
        errEl.textContent = 'Passwords do not match.';
        errEl.classList.remove('d-none');
        return;
      }

      const strongPassword = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/;
      if (!strongPassword.test(pw)) {
        errEl.textContent = 'Password must be at least 8 characters and include both letters and numbers.';
        errEl.classList.remove('d-none');
        return;
      }

      formData.append('action', 'sendRegisterOTP');
      btn.textContent = 'Sending verification code…';
      btn.disabled    = true;
      LoadingUI.setButton(btn, true, 'Sending code…');

      try {
        const res    = await fetch('/Capstone System/apps/controllers/userController.php', {
          method: 'POST', body: formData
        });
        const result = await res.json();

        if (result.success) {
          sucEl.textContent = result.message + ' Redirecting…';
          sucEl.classList.remove('d-none');
          const email = formData.get('email');
          setTimeout(() => {
            window.location.href = '/Capstone System/apps/views/verify-register.php?email=' + encodeURIComponent(email);
          }, 1500);
        } else {
          errEl.textContent = result.message;
          errEl.classList.remove('d-none');
          btn.textContent = 'Create Account';
          LoadingUI.setButton(btn, false);
          btn.disabled    = false;
        }
      } catch (err) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.classList.remove('d-none');
        btn.textContent = 'Create Account';
        LoadingUI.setButton(btn, false);
        btn.disabled    = false;
      }
    });
  </script>

</body>
</html>
