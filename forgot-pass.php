<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Dr. Aprille Ventura Clinica Dental</title>
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
            We'll help you get<br>back into your account.
            </div>
        </div>
        </div>

        <!-- RIGHT -->
        <div class="vd-auth-right">
        <div class="vd-auth-form-wrap">

            <div class="vd-auth-heading">
            <div class="vd-auth-title">Forgot password?</div>
            <div class="vd-auth-sub">Enter your email and we'll send you a reset code.</div>
            </div>

            <div id="fpError"   class="vd-auth-error   d-none"></div>
            <div id="fpSuccess" class="vd-auth-success d-none"></div>

            <form id="forgotForm" class="vd-auth-form" novalidate>
            <div class="vd-auth-group">
                <label class="vd-label">Email Address</label>
                <input type="email" name="email" id="fpEmail" class="vd-auth-input"
                placeholder="Enter your registered email" required>
            </div>
            <button type="submit" class="vd-auth-btn" id="fpBtn">
                Send Reset Code
            </button>
            </form>

            <div class="vd-auth-footer mt-3">
            <a href="login.php">← Back to Sign In</a>
            </div>

        </div>
        </div>

    </div>

    <script>
        document.getElementById('forgotForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn   = document.getElementById('fpBtn');
        const errEl = document.getElementById('fpError');
        const sucEl = document.getElementById('fpSuccess');
        errEl.classList.add('d-none');
        sucEl.classList.add('d-none');
        btn.textContent = 'Sending…';
        btn.disabled    = true;

        const formData = new FormData(this);
        formData.append('action', 'sendOTP');

        try {
            const res    = await fetch('apps/controllers/passwordResetController.php', {
            method: 'POST', body: formData
            });
            const result = await res.json();

            if (result.success) {
            sucEl.textContent = result.message;
            sucEl.classList.remove('d-none');
            // Redirect to OTP verification after 1.5s
            setTimeout(() => {
                window.location.href = 'verify-otp.php?email=' + encodeURIComponent(
                document.getElementById('fpEmail').value
                );
            }, 1500);
            } else {
            errEl.textContent = result.message;
            errEl.classList.remove('d-none');
            btn.textContent = 'Send Reset Code';
            btn.disabled    = false;
            }
        } catch (err) {
            errEl.textContent = 'Network error. Please try again.';
            errEl.classList.remove('d-none');
            btn.textContent = 'Send Reset Code';
            btn.disabled    = false;
        }
        });
    </script>

</body>
</html>