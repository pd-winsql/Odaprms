<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}
require_once __DIR__ . '/../../../config/conn.php';
require_once __DIR__ . '/../../models/staffModel.php';
require_once __DIR__ . '/../../helpers/csrf.php';
$account = (new Staff((new Database())->connect()))->getMyAccount((int) $_SESSION['user_id']);
if (!$account) {
    echo '<div class="vd-empty-state">Account details are unavailable.</div>';
    exit;
}
$escape = static fn($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="vd-my-account" aria-labelledby="myAccountTitle">
    <header class="vd-password-heading">
        <span class="vd-welcome-greet">Account</span>
        <h1 class="vd-welcome-name" id="myAccountTitle">My Account</h1>
        <p>Keep your contact details up to date for clinic communication.</p>
    </header>

    <div class="vd-dash-card vd-my-account-panel">
        <div class="vd-my-account-panel-body">
            <form id="myAccountForm" class="vd-my-account-form" novalidate>
                <div class="vd-my-account-grid">
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountFirst">First name</label>
                        <input class="vd-input form-control" id="myAccountFirst" name="firstname" maxlength="100" autocomplete="given-name" value="<?= $escape($account['firstname']) ?>" required>
                    </div>
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountMiddle">Middle name <span class="vd-label-optional">Optional</span></label>
                        <input class="vd-input form-control" id="myAccountMiddle" name="middlename" maxlength="100" autocomplete="additional-name" value="<?= $escape($account['middlename']) ?>">
                    </div>
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountLast">Last name</label>
                        <input class="vd-input form-control" id="myAccountLast" name="lastname" maxlength="100" autocomplete="family-name" value="<?= $escape($account['lastname']) ?>" required>
                    </div>
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountPhone">Phone number</label>
                        <input class="vd-input form-control" id="myAccountPhone" name="phone_number" type="tel" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" autocomplete="tel" value="<?= $escape($account['phone_number']) ?>" required>
                    </div>
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountEmail">Login email</label>
                        <input class="vd-input form-control" id="myAccountEmail" name="email" type="email" maxlength="255" autocomplete="email" value="<?= $escape($account['login_email']) ?>" required>
                    </div>
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountRole">Role</label>
                        <input class="vd-input form-control" id="myAccountRole" value="<?= $escape($account['user_role'] === 'Admin' ? 'Admin / Dentist' : 'Dental Assistant') ?>" readonly aria-readonly="true">
                    </div>
                </div>
                <div class="vd-my-account-email-check" id="myAccountEmailCheck" hidden>
                    <label class="vd-label form-label" for="myAccountPassword">Current password</label>
                    <input class="vd-input form-control" id="myAccountPassword" name="current_password" type="password" autocomplete="current-password">
                    <p>Confirm your password to change your login email.</p>
                </div>
                <p id="myAccountMessage" class="vd-my-account-message" role="status" aria-live="polite"></p>
                <div class="vd-my-account-actions">
                    <button class="btn vd-btn-gold" id="myAccountSave" type="submit" disabled>Save changes</button>
                    <span id="myAccountChangeState" aria-live="polite">No unsaved changes</span>
                </div>
            </form>
        </div>
    </div>
</section>
<script>
(() => {
    const form = document.getElementById('myAccountForm');
    if (!form) return;
    const fields = ['firstname', 'middlename', 'lastname', 'phone_number', 'email'];
    const original = Object.fromEntries(fields.map(key => [key, form.elements[key].value.trim()]));
    const save = document.getElementById('myAccountSave');
    const message = document.getElementById('myAccountMessage');
    const state = document.getElementById('myAccountChangeState');
    const password = document.getElementById('myAccountPassword');
    const emailCheck = document.getElementById('myAccountEmailCheck');

    const values = () => Object.fromEntries(fields.map(key => [key, form.elements[key].value.trim()]));
    const updateState = () => {
        const current = values();
        const changed = fields.some(key => current[key] !== original[key]);
        const emailChanged = current.email !== original.email;
        emailCheck.hidden = !emailChanged;
        password.required = emailChanged;
        if (!emailChanged) password.value = '';
        save.disabled = !changed;
        state.textContent = changed ? 'Unsaved changes' : 'No unsaved changes';
    };
    form.addEventListener('input', () => {
        message.textContent = '';
        message.classList.remove('is-error');
        updateState();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (save.disabled) return;
        const current = values();
        if (!current.firstname || !current.lastname || !current.phone_number || !current.email) {
            message.textContent = 'Complete all required fields.';
            return;
        }
        if (!/^\d{11}$/.test(current.phone_number)) {
            message.textContent = 'Phone number must contain exactly 11 digits.';
            return;
        }
        if (!form.elements.email.checkValidity()) {
            message.textContent = 'Enter a valid email address.';
            return;
        }
        if (current.email !== original.email && !password.value) {
            message.textContent = 'Enter your current password to change your email.';
            password.focus();
            return;
        }
        save.disabled = true;
        const body = new FormData(form);
        body.set('action', 'updateMyAccount');
        body.set('csrf_token', <?= json_encode(get_csrf_token()) ?>);
        try {
            const response = await fetch(window.vdAppUrl('apps/controllers/myAccountController.php'), {method: 'POST', body, credentials: 'same-origin'});
            const result = await response.json();
            message.textContent = result.message || (result.success ? 'Account details saved.' : 'Unable to save your account.');
            message.classList.toggle('is-error', !result.success);
            if (result.success) {
                Object.assign(original, current);
                password.value = '';
                document.querySelectorAll('.vd-user-name').forEach(node => { node.textContent = result.display_name; });
                const initials = result.display_name.split(/\s+/).filter(Boolean).map(word => word[0]).join('').toUpperCase().slice(0, 2);
                document.querySelectorAll('.vd-user-avatar').forEach(node => { node.textContent = initials; });
            } else if (result.field === 'current_password') {
                password.focus();
            }
        } catch (error) {
            message.textContent = 'Network error. Please try again.';
            message.classList.add('is-error');
        } finally {
            updateState();
        }
    });
})();
</script>
