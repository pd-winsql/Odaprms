<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: forgot-password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Dr. Aprille Ventura Clinica Dental</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/bootstrap.min.css">
    <link rel="stylesheet" href="public/css/styles.css">
    <link rel="stylesheet" href="public/css/auth.css">
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
            <div class="vd-logo-name">Dr. Aprille</div>
            <div class="vd-logo-ventura vd-auth-ventura">
            VEN<span class="vd-cross vd-auth-cross">✚</span>URA
            </div>
            <div class="vd-logo-sub">Clinica Dental</div>
            <div class="vd-auth-tagline">
            Set a new password<br>for your account.
            </div>
        </div>
        </div>

        <!-- RIGHT -->
        <div class="vd-auth-right">
        <div class="vd-auth-form-wrap">

            <div class="vd-auth-heading">
            <div class="vd-auth-title">Reset password</div>
            <div class="vd-auth-sub">Choose a strong new password for your account.</div>
            </div>

            <div id="rpError"   class="vd-auth-error   d-none"></div>
            <div id="rpSuccess" class="vd-auth-success d-none"></div>

            <form id="resetForm" class="vd-auth-form" novalidate>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="vd-auth-group">
                <label class="vd-label">New Password</label>
                <div class="vd-auth-input-wrap">
                <input type="password" name="new_password" id="newPw"
                    class="vd-auth-input" placeholder="Min. 8 characters" required>
                <button type="button" class="vd-pw-toggle" id="toggleNewPw">
                    <i class="ti ti-eye" id="newPwIcon"></i>
                </button>
                </div>
            </div>

            <div class="vd-auth-group">
                <label class="vd-label">Confirm New Password</label>
                <div class="vd-auth-input-wrap">
                <input type="password" name="confirm_password" id="confirmPw"
                    class="vd-auth-input" placeholder="Re-enter password" required>
                <button type="button" class="vd-pw-toggle" id="toggleConfirmPw">
                    <i class="ti ti-eye" id="confirmPwIcon"></i>
                </button>
                </div>
            </div>

            <button type="submit" class="vd-auth-btn" id="rpBtn">
                Reset Password
            </button>
            </form>

        </div>
        </div>

    </div>

    <script>
        function togglePw(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        const isHidden = input.type === 'password';
        input.type     = isHidden ? 'text' : 'password';
        icon.className = isHidden ? 'ti ti-eye-off' : 'ti ti-eye';
        }

        document.getElementById('toggleNewPw').addEventListener('click', () => togglePw('newPw', 'newPwIcon'));
        document.getElementById('toggleConfirmPw').addEventListener('click', () => togglePw('confirmPw', 'confirmPwIcon'));

        document.getElementById('resetForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn   = document.getElementById('rpBtn');
        const errEl = document.getElementById('rpError');
        const sucEl = document.getElementById('rpSuccess');
        errEl.classList.add('d-none');
        sucEl.classList.add('d-none');

        const newPw  = document.getElementById('newPw').value;
        const confPw = document.getElementById('confirmPw').value;

        if (newPw.length < 8) {
            errEl.textContent = 'Password must be at least 8 characters.';
            errEl.classList.remove('d-none');
            return;
        }
        if (newPw !== confPw) {
            errEl.textContent = 'Passwords do not match.';
            errEl.classList.remove('d-none');
            return;
        }

        btn.textContent = 'Saving…';
        btn.disabled    = true;

        const formData = new FormData(this);
        formData.append('action', 'resetPassword');

        try {
            const res    = await fetch('apps/controllers/passwordResetController.php', {
            method: 'POST', body: formData
            });
            const result = await res.json();

            if (result.success) {
            sucEl.textContent = 'Password reset successfully! Redirecting to login…';
            sucEl.classList.remove('d-none');
            setTimeout(() => window.location.href = 'login.php', 2000);
            } else {
            errEl.textContent = result.message;
            errEl.classList.remove('d-none');
            btn.textContent = 'Reset Password';
            btn.disabled    = false;
            }
        } catch (err) {
            errEl.textContent = 'Network error. Please try again.';
            errEl.classList.remove('d-none');
            btn.textContent = 'Reset Password';
            btn.disabled    = false;
        }
        });
    </script>

</body>
</html>