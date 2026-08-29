<?php
session_start();
require_once '../helpers/siteBranding.php';
$branding = vdLoadSiteBranding();
if (isset($_SESSION['user_id'])) {
    header('Location: /Capstone System/index.php');
    exit;
}

// Browser Back should resume an in-progress verification. The explicit edit
// link is the one exception: keep the pending data and use it to refill the
// registration form.
$pendingRegistration = $_SESSION['pending_registration'] ?? null;
$isEditingRegistration = $pendingRegistration && isset($_GET['edit']);
if ($pendingRegistration && !$isEditingRegistration) {
    header('Location: /Capstone System/apps/views/verify-register.php?email=' . rawurlencode($pendingRegistration['email']));
    exit;
}

$pendingIdentity = $pendingRegistration['identity'] ?? [];
$registrationValues = [
    'firstname' => $pendingIdentity['firstname'] ?? '',
    'middlename' => $pendingIdentity['middlename'] ?? '',
    'lastname' => $pendingIdentity['lastname'] ?? '',
    'suffix' => $pendingIdentity['suffix'] ?? '',
    'birthdate' => $pendingIdentity['birthdate'] ?? '',
    'gender' => $pendingIdentity['gender'] ?? '',
    'phone_number' => $pendingIdentity['phone_number'] ?? '',
    'email' => $pendingRegistration['email'] ?? '',
];
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
  <link rel="stylesheet" href="../../public/css/styles.css?v=<?= filemtime(__DIR__ . '/../../public/css/styles.css') ?>">
  <link rel="stylesheet" href="../../public/css/auth.css?v=<?= filemtime(__DIR__ . '/../../public/css/auth.css') ?>">
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

        <div id="registerError" class="vd-auth-error d-none" role="alert" aria-live="polite"></div>
        <div id="registerSuccess" class="vd-auth-success d-none" role="status" aria-live="polite"></div>

        <form id="registerForm" class="vd-auth-form vd-register-grid" novalidate>
          <div class="vd-auth-group"><label class="vd-label" for="regFirstName">First Name</label><input type="text" name="firstname" id="regFirstName" class="vd-auth-input" value="<?= $escape($registrationValues['firstname']) ?>" required autocomplete="given-name"></div>
          <div class="vd-auth-group"><label class="vd-label" for="regMiddleName">Middle Name <span class="text-muted">(optional)</span></label><input type="text" name="middlename" id="regMiddleName" class="vd-auth-input" value="<?= $escape($registrationValues['middlename']) ?>" autocomplete="additional-name"></div>
          <div class="vd-auth-group"><label class="vd-label" for="regLastName">Last Name</label><input type="text" name="lastname" id="regLastName" class="vd-auth-input" value="<?= $escape($registrationValues['lastname']) ?>" required autocomplete="family-name"></div>
          <div class="vd-auth-group"><label class="vd-label" for="regSuffix">Suffix <span class="text-muted">(optional)</span></label><input type="text" name="suffix" id="regSuffix" class="vd-auth-input" value="<?= $escape($registrationValues['suffix']) ?>" placeholder="Jr., Sr., III"></div>
          <div class="vd-auth-group"><label class="vd-label" for="regBirthdate">Birthdate</label><input type="date" name="birthdate" id="regBirthdate" class="vd-auth-input" value="<?= $escape($registrationValues['birthdate']) ?>" max="<?= date('Y-m-d') ?>" required autocomplete="bday"></div>
          <div class="vd-auth-group">
            <label class="vd-label" for="regGender">Gender</label>
            <select name="gender" id="regGender" class="vd-auth-input" required>
              <option value="" <?= $registrationValues['gender'] === '' ? 'selected' : '' ?> disabled>Select gender</option>
              <option value="Male" <?= $registrationValues['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= $registrationValues['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
              <option value="Prefer not to say" <?= $registrationValues['gender'] === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
            </select>
          </div>
          <div class="vd-auth-group"><label class="vd-label" for="regPhoneNumber">Contact Number</label><input type="tel" name="phone_number" id="regPhoneNumber" class="vd-auth-input" value="<?= $escape($registrationValues['phone_number']) ?>" placeholder="09XXXXXXXXX" required maxlength="11" minlength="11" inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel"></div>
          <div class="vd-auth-group">
            <label class="vd-label" for="regEmail">Email Address</label>
            <input type="email" name="email" id="regEmail" class="vd-auth-input"
              value="<?= $escape($registrationValues['email']) ?>" placeholder="email@example.com" required autocomplete="email">
          </div>
          <div class="vd-auth-group">
            <label class="vd-label" for="regPassword">Password</label>
            <div class="vd-auth-input-wrap">
              <input type="password" name="password" id="regPassword" class="vd-auth-input"
                placeholder="Min. 8 characters" required autocomplete="new-password" aria-describedby="regPasswordHint">
              <button type="button" class="vd-pw-toggle" id="toggleRegPw" aria-label="Show password">
                <i class="ti ti-eye" id="regEyeIcon"></i>
              </button>
            </div>
            <div class="vd-auth-hint" id="regPasswordHint">Use at least 8 characters with both letters and numbers.</div>
          </div>
          <div class="vd-auth-group">
            <label class="vd-label" for="regConfirmPassword">Confirm Password</label>
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

    const registerForm = document.getElementById('registerForm');
    const registrationPasswordKey = 'pendingRegistrationPasswords';
    const isEditingRegistration = <?= $isEditingRegistration ? 'true' : 'false' ?>;

    // Passwords cannot be reconstructed from the secure server-side hash. Keep
    // them only in this tab while email verification is in progress.
    if (isEditingRegistration) {
      try {
        const savedPasswords = JSON.parse(sessionStorage.getItem(registrationPasswordKey) || 'null');
        if (savedPasswords) {
          document.getElementById('regPassword').value = savedPasswords.password || '';
          document.getElementById('regConfirmPassword').value = savedPasswords.confirmPassword || '';
        }
      } catch (err) {
        // The other registration details are still restored by the server.
      }
    }

    function isRequiredFieldMissing(field) {
      return field.required && !String(field.value).trim();
    }

    function clearRequiredError(field) {
      if (!isRequiredFieldMissing(field)) {
        field.classList.remove('vd-auth-input-invalid');
        field.removeAttribute('aria-invalid');
      }
    }

    registerForm.querySelectorAll('[required]').forEach(function (field) {
      field.addEventListener(field.tagName === 'SELECT' ? 'change' : 'input', function () {
        clearRequiredError(field);
      });
    });

    const phoneNumber = document.getElementById('regPhoneNumber');
    phoneNumber.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });

    registerForm.addEventListener('submit', async function (e) {
      e.preventDefault();

      const btn   = document.getElementById('registerBtn');
      const errEl = document.getElementById('registerError');
      const sucEl = document.getElementById('registerSuccess');
      errEl.classList.add('d-none');
      sucEl.classList.add('d-none');

      const formData = new FormData(this);
      const pw       = formData.get('password');
      const cpw      = formData.get('confirm_password');

      const requiredFields = Array.from(this.querySelectorAll('[required]'));
      const missingFields = requiredFields.filter(isRequiredFieldMissing);

      requiredFields.forEach(function (field) {
        const isMissing = missingFields.includes(field);
        field.classList.toggle('vd-auth-input-invalid', isMissing);
        if (isMissing) {
          field.setAttribute('aria-invalid', 'true');
        } else {
          field.removeAttribute('aria-invalid');
        }
      });

      if (missingFields.length) {
        errEl.textContent = 'Please fill in all required fields.';
        errEl.classList.remove('d-none');
        missingFields[0].focus();
        return;
      }

      if (!/^\d{11}$/.test(phoneNumber.value)) {
        phoneNumber.classList.add('vd-auth-input-invalid');
        phoneNumber.setAttribute('aria-invalid', 'true');
        errEl.textContent = 'Contact number must contain exactly 11 digits.';
        errEl.classList.remove('d-none');
        phoneNumber.focus();
        return;
      }

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
      LoadingUI.setButton(btn, true, 'Sending code…');

      try {
        const res    = await fetch('/Capstone System/apps/controllers/userController.php', {
          method: 'POST', body: formData
        });
        const result = await res.json();

        if (result.success) {
          try {
            sessionStorage.setItem(registrationPasswordKey, JSON.stringify({
              password: pw,
              confirmPassword: cpw
            }));
            if (!result.resumed) {
              sessionStorage.setItem('registerOtpResendAvailableAt', String(Date.now() + 60000));
            }
          } catch (err) {
            // Registration can continue even when browser storage is disabled.
          }
          sucEl.textContent = result.message + ' Redirecting…';
          sucEl.classList.remove('d-none');
          const email = formData.get('email');
          setTimeout(() => {
            window.location.href = '/Capstone System/apps/views/verify-register.php?email=' + encodeURIComponent(email);
          }, 1500);
        } else {
          errEl.textContent = result.message;
          errEl.classList.remove('d-none');
          LoadingUI.setButton(btn, false);
        }
      } catch (err) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.classList.remove('d-none');
        LoadingUI.setButton(btn, false);
      }
    });
  </script>

</body>
</html>
