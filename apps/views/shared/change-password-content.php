<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$allowedRoles = ['Admin', 'Dental Assistant', 'Patient'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', $allowedRoles, true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>'; exit;
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
?>

<section class="vd-password-page" aria-labelledby="accountPasswordTitle">
    <header class="vd-password-heading">
        <span class="vd-welcome-greet">Account security</span>
        <h1 class="vd-welcome-name" id="accountPasswordTitle">Change your password</h1>
        <p>Use a unique password you do not use elsewhere.</p>
    </header>

    <div class="vd-dash-card vd-password-panel">
        <div class="vd-password-panel-body">
            <form id="accountChangePasswordForm" class="vd-change-password-form" novalidate>
                <div class="vd-password-section">
                    <label class="vd-label form-label" for="accountCurrentPw">Current password</label>
                    <div class="vd-auth-input-wrap">
                        <input type="password" name="current_password" id="accountCurrentPw"
                            class="vd-input vd-auth-input" autocomplete="current-password" required
                            aria-describedby="accountCurrentPwFeedback">
                        <button type="button" class="vd-pw-toggle" data-toggle-account-password="accountCurrentPw"
                            aria-label="Show current password">
                            <i class="ti ti-eye" aria-hidden="true"></i><span>Show</span>
                        </button>
                    </div>
                    <p class="vd-field-feedback" id="accountCurrentPwFeedback" aria-live="polite"></p>
                </div>

                <div class="vd-password-divider" aria-hidden="true"></div>

                <div class="vd-password-section">
                    <label class="vd-label form-label" for="accountNewPw">New password</label>
                    <div class="vd-auth-input-wrap">
                        <input type="password" name="new_password" id="accountNewPw"
                            class="vd-input vd-auth-input" autocomplete="new-password" required
                            aria-describedby="accountPasswordRequirements accountNewPwFeedback">
                        <button type="button" class="vd-pw-toggle" data-toggle-account-password="accountNewPw"
                            aria-label="Show new password">
                            <i class="ti ti-eye" aria-hidden="true"></i><span>Show</span>
                        </button>
                    </div>
                    <ul class="vd-password-requirements" id="accountPasswordRequirements" aria-label="Password requirements" aria-live="polite">
                        <li data-password-rule="length">At least 8 characters</li>
                        <li data-password-rule="letter">Contains a letter</li>
                        <li data-password-rule="number">Contains a number</li>
                        <li data-password-rule="match">Passwords match</li>
                    </ul>
                    <p class="vd-field-feedback" id="accountNewPwFeedback" aria-live="polite"></p>
                </div>

                <div class="vd-password-section">
                    <label class="vd-label form-label" for="accountConfirmPw">Confirm new password</label>
                    <div class="vd-auth-input-wrap">
                        <input type="password" name="confirm_password" id="accountConfirmPw"
                            class="vd-input vd-auth-input" autocomplete="new-password" required
                            aria-describedby="accountConfirmPwFeedback">
                        <button type="button" class="vd-pw-toggle" data-toggle-account-password="accountConfirmPw"
                            aria-label="Show confirmation password">
                            <i class="ti ti-eye" aria-hidden="true"></i><span>Show</span>
                        </button>
                    </div>
                    <p class="vd-field-feedback" id="accountConfirmPwFeedback" aria-live="polite"></p>
                </div>

                <div id="accountPasswordMessage" class="alert d-none mb-0" role="status" aria-live="polite"></div>

                <div class="vd-password-actions">
                    <button type="submit" class="btn vd-btn-gold">Update password</button>
                    <button type="button" class="btn vd-btn-outline" id="accountPasswordCancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</section>
<script>
(function () {
    const form = document.getElementById('accountChangePasswordForm');
    if (!form) return;

    const message = document.getElementById('accountPasswordMessage');
    const currentInput = document.getElementById('accountCurrentPw');
    const newInput = document.getElementById('accountNewPw');
    const confirmInput = document.getElementById('accountConfirmPw');
    const cancelButton = document.getElementById('accountPasswordCancel');
    const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
    const feedback = {
        current: document.getElementById('accountCurrentPwFeedback'),
        next: document.getElementById('accountNewPwFeedback'),
        confirmation: document.getElementById('accountConfirmPwFeedback')
    };
    const ruleElements = Object.fromEntries(
        Array.from(form.querySelectorAll('[data-password-rule]')).map(item => [item.dataset.passwordRule, item])
    );

    function setFieldFeedback(input, element, text = '') {
        element.textContent = text;
        input.setAttribute('aria-invalid', text ? 'true' : 'false');
    }

    function passwordState() {
        const next = newInput.value;
        const confirmation = confirmInput.value;
        return {
            length: next.length >= 8,
            letter: /[A-Za-z]/.test(next),
            number: /\d/.test(next),
            match: next.length > 0 && confirmation.length > 0 && next === confirmation
        };
    }

    function updateRequirements() {
        const state = passwordState();
        Object.entries(state).forEach(([rule, isMet]) => {
            ruleElements[rule]?.classList.toggle('is-met', isMet);
        });

        if (confirmInput.value && !state.match) {
            setFieldFeedback(confirmInput, feedback.confirmation, 'Passwords do not match.');
        } else {
            setFieldFeedback(confirmInput, feedback.confirmation);
        }

        return state;
    }

    function clearFormFeedback() {
        setFieldFeedback(currentInput, feedback.current);
        setFieldFeedback(newInput, feedback.next);
        setFieldFeedback(confirmInput, feedback.confirmation);
        message.className = 'alert d-none mb-0';
        message.textContent = '';
        updateRequirements();
    }

    document.querySelectorAll('[data-toggle-account-password]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.toggleAccountPassword);
            const showing = input.type === 'text';
            const fieldName = input === currentInput ? 'current' : input === newInput ? 'new' : 'confirmation';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-label', `${showing ? 'Show' : 'Hide'} ${fieldName} password`);
            button.querySelector('i').className = showing ? 'ti ti-eye' : 'ti ti-eye-off';
            button.querySelector('span').textContent = showing ? 'Show' : 'Hide';
        });
    });

    newInput.addEventListener('input', () => {
        setFieldFeedback(newInput, feedback.next);
        updateRequirements();
    });
    confirmInput.addEventListener('input', updateRequirements);
    currentInput.addEventListener('input', () => setFieldFeedback(currentInput, feedback.current));

    [currentInput, newInput, confirmInput].forEach(input => {
        input.addEventListener('blur', () => {
            if (!input.value) {
                const target = input === currentInput ? feedback.current : input === newInput ? feedback.next : feedback.confirmation;
                setFieldFeedback(input, target, 'This field is required.');
            }
        });
    });

    cancelButton.addEventListener('click', () => {
        form.reset();
        clearFormFeedback();
        currentInput.focus();
    });

    function showMessage(text, success) {
        message.textContent = text;
        message.className = `alert ${success ? 'alert-success' : 'alert-danger'} mb-0`;
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        clearFormFeedback();

        const data = new FormData(form);
        const current = String(data.get('current_password') || '');
        const next = String(data.get('new_password') || '');
        const confirmation = String(data.get('confirm_password') || '');
        const state = updateRequirements();
        let firstInvalid = null;

        if (!current) {
            setFieldFeedback(currentInput, feedback.current, 'Enter your current password.');
            firstInvalid ??= currentInput;
        }
        if (!next) {
            setFieldFeedback(newInput, feedback.next, 'Enter a new password.');
            firstInvalid ??= newInput;
        } else if (!state.length || !state.letter || !state.number) {
            setFieldFeedback(newInput, feedback.next, 'Complete all password requirements.');
            firstInvalid ??= newInput;
        } else if (current === next) {
            setFieldFeedback(newInput, feedback.next, 'Choose a password different from your current password.');
            firstInvalid ??= newInput;
        }
        if (!confirmation) {
            setFieldFeedback(confirmInput, feedback.confirmation, 'Confirm your new password.');
            firstInvalid ??= confirmInput;
        } else if (!state.match) {
            setFieldFeedback(confirmInput, feedback.confirmation, 'Passwords do not match.');
            firstInvalid ??= confirmInput;
        }

        if (firstInvalid) {
            showMessage('Review the highlighted fields and try again.', false);
            firstInvalid.focus();
            return;
        }

        data.append('action', 'changePassword');
        const submit = form.querySelector('button[type="submit"]');
        LoadingUI.setButton(submit, true, 'Updating...');

        try {
            const response = await fetch('../../controllers/accountController.php', {
                method: 'POST',
                headers: {'X-CSRF-Token': csrfToken},
                body: data
            });
            const result = await response.json();
            showMessage(result.message || (result.success ? 'Password updated successfully.' : 'Unable to update password.'), Boolean(result.success));
            if (!result.success && /current password/i.test(result.message || '')) {
                setFieldFeedback(currentInput, feedback.current, result.message);
                currentInput.focus();
            }
            if (result.success) {
                form.reset();
                setFieldFeedback(currentInput, feedback.current);
                setFieldFeedback(newInput, feedback.next);
                setFieldFeedback(confirmInput, feedback.confirmation);
                updateRequirements();
            }
        } catch (error) {
            showMessage('Network error. Please try again.', false);
        } finally {
            LoadingUI.setButton(submit, false);
        }
    });

    updateRequirements();
})();
</script>
