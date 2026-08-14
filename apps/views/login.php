<?php
session_start();
require_once '../helpers/siteBranding.php';
$branding = vdLoadSiteBranding();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'Patient') {
        header('Location: apps/views/patient/dashboard.php');
    } else if ($_SESSION['user_role'] === 'Admin') {
        header('Location: apps/views/admin/dashboard.php');
    } else if ($_SESSION['user_role'] === 'Dentist') {
        header('Location: apps/views/dentist/dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sign In | Dr. Aprille Ventura Clinica Dental</title>
	<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
	<link rel="stylesheet" href="../../public/css/bootstrap.min.css">
	<link rel="stylesheet" href="../../public/css/styles.css">
	<link rel="stylesheet" href="../../public/css/auth.css?v=20260814-link-visibility">
	<link rel="stylesheet" href="../../public/css/loading.css">
	<script src="../../public/js/loading.js" defer></script>
</head>
<body class="vd-auth-body">

	<div class="vd-auth-split">

		<!-- LEFT — Branding -->
		<div class="vd-auth-left">
		<div class="vd-auth-geo vd-geo-1"></div>
		<div class="vd-auth-geo vd-geo-2"></div>
		<div class="vd-auth-geo vd-geo-3"></div>
		<div class="vd-auth-sq vd-sq-1"></div>
		<div class="vd-auth-sq vd-sq-2"></div>

		<div class="vd-auth-brand">
			<?= vdRenderSiteBranding($branding, '../../public/assets', 'auth') ?>
			<div class="vd-auth-tagline">
			Your smile is our<br>greatest achievement.
			</div>
		</div>
		</div>

		<!-- RIGHT — Form -->
		<div class="vd-auth-right">
		<div class="vd-auth-form-wrap">

			<div class="vd-auth-heading">
			<div class="vd-auth-title">Welcome back</div>
			<div class="vd-auth-sub">Sign in to your account to continue.</div>
			</div>

			<!-- Error message -->
			<div id="loginError" class="vd-auth-error d-none"></div>

			<form id="loginForm" class="vd-auth-form" novalidate>
			<div class="vd-auth-group">
				<label class="vd-label">Email Address</label>
				<input type="text" name="identity" class="vd-auth-input"
				placeholder="Enter your email" required autocomplete="email" inputmode="email">
			</div>
			<div class="vd-auth-group">
				<label class="vd-label">Password</label>
				<div class="vd-auth-input-wrap">
				<input type="password" name="password" id="logPassword" class="vd-auth-input"
					placeholder="••••••••" required autocomplete="current-password">
				<button type="button" class="vd-pw-toggle" id="toggleRegPw" aria-label="Show password">
					<i class="ti ti-eye" id="regEyeIcon"></i>
				</button>
				</div>
			</div>

			<div class="vd-auth-forgot">
				<a href="forgot-pass.php">Forgot password?</a>
			</div>

			<button type="submit" class="vd-auth-btn" id="loginBtn">
				Sign In
			</button>
			</form>

			<div class="vd-auth-footer">
            Don't have an account? <a href="register.php<?= ($_GET['next'] ?? '') === 'booking' ? '?next=booking' : '' ?>">Sign up</a>
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
		togglePassword('logPassword', 'regEyeIcon');
		});
		
		document.getElementById('loginForm').addEventListener('submit', async function (e) {
		e.preventDefault();

		const btn    = document.getElementById('loginBtn');
		const errEl  = document.getElementById('loginError');
		errEl.classList.add('d-none');
		btn.textContent = 'Signing in…';
		btn.disabled    = true;
		LoadingUI.setButton(btn, true, 'Signing in…');

		const formData = new FormData(this);
		formData.append('action', 'login');
		formData.append('next', new URLSearchParams(window.location.search).get('next') || '');

		try {
			const res    = await fetch('../controllers/userController.php', {
			method: 'POST', body: formData
			});
			const result = await res.json();

			if (result.success) {
			window.location.href = result.redirect;
			} else {
			errEl.textContent = result.message;
			errEl.classList.remove('d-none');
			btn.textContent = 'Sign In';
			LoadingUI.setButton(btn, false);
			btn.disabled    = false;
			}
		} catch (err) {
			errEl.textContent = 'Network error. Please try again.';
			errEl.classList.remove('d-none');
			btn.textContent = 'Sign In';
			LoadingUI.setButton(btn, false);
			btn.disabled    = false;
		}
		});
	</script>

</body>
</html>
