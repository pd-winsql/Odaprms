(() => {
    const root = document.querySelector('.vd-my-account');
    if (!root) return;
    const controller = root.dataset.controller || 'apps/controllers/myAccountController.php';
    const form = root.querySelector('#myAccountForm');
    const fields = ['firstname', 'middlename', 'lastname', 'phone_number'];
    const values = () => Object.fromEntries(fields.map(key => [key, form.elements[key].value.trim()]));
    const original = values();
    const save = root.querySelector('#myAccountSave');
    const message = root.querySelector('#myAccountMessage');
    const state = root.querySelector('#myAccountChangeState');
    let saving = false;
    fields.forEach(key => {
        const input = form.elements[key];
        const feedback = document.createElement('p');
        feedback.className = 'vd-field-feedback';
        feedback.dataset.basicError = key;
        feedback.setAttribute('aria-live', 'polite');
        input.after(feedback);
    });
    function show(node, result) {
        node.textContent = result.message;
        node.classList.toggle('is-error', !result.success);
    }
    async function post(body, action) {
        body.set('action', action);
        body.set('csrf_token', root.dataset.csrf);
        const response = await fetch(window.vdAppUrl(controller), { method: 'POST', body, credentials: 'same-origin' });
        return response.json();
    }
    function syncBasic() {
        const current = values();
        const changed = fields.some(key => current[key] !== original[key]);
        save.disabled = saving || !changed;
        state.textContent = changed ? 'Unsaved changes' : 'No unsaved changes';
    }
    form.addEventListener('input', () => {
        message.textContent = '';
        form.querySelectorAll('[data-basic-error]').forEach(node => { node.textContent = ''; });
        fields.forEach(key => form.elements[key].removeAttribute('aria-invalid'));
        syncBasic();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (save.disabled || !form.reportValidity()) return;
        const current = values();
        saving = true; syncBasic();
        try {
            const result = await post(new FormData(form), 'updateMyAccount');
            show(message, result);
            if (!result.success && fields.includes(result.field)) {
                form.querySelector(`[data-basic-error="${result.field}"]`).textContent = result.message;
                form.elements[result.field].setAttribute('aria-invalid', 'true');
                form.elements[result.field].focus();
            }
            if (result.success) {
                Object.assign(original, current);
                if (result.display_name) {
                    document.querySelectorAll('.vd-user-name').forEach(node => { node.textContent = result.display_name; });
                    const initials = result.display_name.split(/\s+/).filter(Boolean).map(word => word[0]).join('').toUpperCase().slice(0, 2);
                    document.querySelectorAll('.vd-user-avatar').forEach(node => { node.textContent = initials; });
                }
            }
        } catch (_) { show(message, { success: false, message: 'Network error. Please try again.' }); }
        finally { saving = false; syncBasic(); }
    });
    const emailForm = root.querySelector('#myAccountEmailForm');
    const email = emailForm.elements.email;
    const password = emailForm.elements.current_password;
    const code = emailForm.elements.code;
    const send = root.querySelector('#myAccountSendCode');
    const verify = root.querySelector('#myAccountVerify');
    const verification = root.querySelector('#myAccountVerification');
    const emailMessage = root.querySelector('#myAccountEmailMessage');
    const currentEmail = root.querySelector('#myAccountCurrentEmail');
    let busy = false;
    let resendAt = 0;
    let timer;
    root.querySelectorAll('[data-toggle-my-account-password]').forEach(button => {
        button.addEventListener('click', () => {
            const input = root.querySelector(`#${button.dataset.toggleMyAccountPassword}`);
            if (!input) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-label', `${showing ? 'Show' : 'Hide'} current password`);
            button.querySelector('i').className = showing ? 'ti ti-eye' : 'ti ti-eye-off';
            const text = button.querySelector('span');
            if (text) text.textContent = showing ? 'Show password' : 'Hide password';
        });
    });
    function syncEmail() {
        const address = email.value.trim();
        const cooldown = Math.max(0, Math.ceil((resendAt - Date.now()) / 1000));
        send.disabled = busy || cooldown > 0 || !address || !email.checkValidity() || !password.value || address.toLowerCase() === currentEmail.textContent.trim().toLowerCase();
        send.textContent = cooldown ? `Resend code in ${cooldown}s` : verification.hidden ? 'Send verification code' : 'Resend verification code';
        verify.disabled = busy || verification.hidden || address !== verification.dataset.pendingEmail || !password.value || !/^[0-9]{6}$/.test(code.value);
        email.readOnly = busy; password.readOnly = busy; code.readOnly = busy;
    }
    function clearErrors() {
        emailForm.querySelectorAll('[data-error-for]').forEach(node => { node.textContent = ''; });
        [email,password,code].forEach(input => input.removeAttribute('aria-invalid'));
    }
    function feedback(result) {
        show(emailMessage, result);
        if (result.field) {
            const error = emailForm.querySelector(`[data-error-for="${result.field}"]`);
            if (error) error.textContent = result.message;
            emailForm.elements[result.field]?.setAttribute('aria-invalid', 'true');
            emailForm.elements[result.field]?.focus();
        }
    }
    emailForm.addEventListener('input', event => {
        if (event.target === code) code.value = code.value.replace(/\D/g, '').slice(0, 6);
        clearErrors(); emailMessage.textContent = ''; syncEmail();
    });
    async function emailAction(action) {
        if (busy || (action === 'requestEmailChange' ? send.disabled : verify.disabled)) return;
        clearErrors(); busy = true; syncEmail();
        try {
            const result = await post(new FormData(emailForm), action);
            feedback(result);
            if (result.success && action === 'requestEmailChange') {
                verification.hidden = false;
                verification.dataset.pendingEmail = result.pending_email;
                code.value = '';
                resendAt = Date.now() + 60000;
                clearInterval(timer);
                timer = setInterval(() => {
                    if (!root.isConnected || Date.now() >= resendAt) clearInterval(timer);
                    if (root.isConnected) syncEmail();
                }, 1000);
                busy = false; syncEmail(); code.focus();
            } else if (result.success) {
                currentEmail.textContent = result.email;
                emailForm.reset(); email.value = ''; password.value = ''; code.value = '';
                verification.hidden = true; verification.dataset.pendingEmail = '';
                clearInterval(timer); resendAt = 0;
            }
        } catch (_) { feedback({ success: false, message: 'Network error. Your current email is unchanged; refresh to check verification status.' }); }
        finally { busy = false; syncEmail(); }
    }
    emailForm.addEventListener('submit', event => { event.preventDefault(); emailAction('requestEmailChange'); });
    verify.addEventListener('click', () => emailAction('verifyEmailChange'));
    if (root.dataset.openPassword === '1' || window.vdAccountOpenPassword) {
        root.querySelector('#myAccountPasswordSection').open = true;
        window.vdAccountOpenPassword = false;
        root.querySelector('#myAccountPasswordSection').scrollIntoView({ block: 'start' });
    }
    syncBasic(); syncEmail();
})();
