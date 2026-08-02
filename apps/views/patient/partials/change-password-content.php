<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}
?>

<div class="d-flex flex-column gap-4">
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Change Password</span>
        </div>
        <div class="vd-profile-body">
        <form id="changePasswordForm" class="d-flex flex-column gap-3" style="max-width: 420px;">
            <div class="vd-auth-group">
                <label class="vd-label form-label">Current Password</label>
                <div class="vd-auth-input-wrap">
                    <input type="password" name="current_password" id="currentPw" class="vd-input vd-auth-input" required>
                    <button type="button" class="vd-pw-toggle" id="toggleCurrentPw" aria-label="Show password">
                        <i class="ti ti-eye" id="currentPwIcon"></i>
                    </button>
                </div>
            </div>

            <div class="vd-auth-group">
                <label class="vd-label form-label">New Password</label>
                <div class="vd-auth-input-wrap">
                    <input type="password" name="new_password" id="newPw" class="vd-input vd-auth-input" required>
                    <button type="button" class="vd-pw-toggle" id="toggleNewPw" aria-label="Show password">
                        <i class="ti ti-eye" id="newPwIcon"></i>
                    </button>
                </div>
                <div class="small text-muted">Use at least 8 characters with both letters and numbers.</div>
            </div>

            <div>
                <label class="vd-label form-label">Confirm New Password</label>
                <div class="vd-auth-input-wrap">
                    <input type="password" name="confirm_password" id="confirmPw" class="vd-input vd-auth-input" required>
                    <button type="button" class="vd-pw-toggle" id="toggleConfirmPw" aria-label="Show password">
                        <i class="ti ti-eye" id="confirmPwIcon"></i>
                    </button>
                </div>
            </div>

            <div id="pwError" class="text-danger small d-none"></div>
            <div id="pwSuccess" class="text-success small d-none">Password changed successfully.</div>

            <div>
                <button type="submit" class="btn vd-btn-gold">Save Changes</button>
            </div>
        </form>
        </div>
    </div>
</div>

<script>
(function () {
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        icon.className = isHidden ? 'ti ti-eye-off' : 'ti ti-eye';
    }

    document.getElementById('toggleCurrentPw').addEventListener('click', function () {
        togglePassword('currentPw', 'currentPwIcon');
    });

    document.getElementById('toggleNewPw').addEventListener('click', function () {
        togglePassword('newPw', 'newPwIcon');
    });

    document.getElementById('toggleConfirmPw').addEventListener('click', function () {
        togglePassword('confirmPw', 'confirmPwIcon');
    });

    document.getElementById('changePasswordForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const errEl  = document.getElementById('pwError');
        const sucEl  = document.getElementById('pwSuccess');
        errEl.classList.add('d-none');
        sucEl.classList.add('d-none');

        const formData = new FormData(this);
        formData.append('action', 'changePassword');

        const newPw  = formData.get('new_password');
        const confPw = formData.get('confirm_password');

        if (newPw !== confPw) {
            errEl.textContent = 'New passwords do not match.';
            errEl.classList.remove('d-none');
            return;
        }

        const strongPassword = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/;
        if (!strongPassword.test(newPw)) {
            errEl.textContent = 'Password must be at least 8 characters and include both letters and numbers.';
            errEl.classList.remove('d-none');
            return;
        }

        const submitButton = this.querySelector('button[type="submit"]');
        LoadingUI.setButton(submitButton, true, 'Saving…');
        try {
            const res = await fetch('../../controllers/patientController.php', {
                method: 'POST',
                body: formData
            });
            const result = await res.json();

            if (result.success) {
                sucEl.classList.remove('d-none');
                this.reset();
            } else {
                errEl.textContent = result.message;
                errEl.classList.remove('d-none');
            }
        } catch (err) {
            errEl.textContent = 'Network error. Please try again.';
            errEl.classList.remove('d-none');
        } finally {
            LoadingUI.setButton(submitButton, false);
        }
    });
})();

</script>
