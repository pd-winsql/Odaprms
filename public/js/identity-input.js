(() => {
    const names = new Set([
        'firstname', 'middlename', 'lastname', 'firstName', 'middleName', 'lastName',
        'guardian_name', 'guardianName', 'physician_name', 'physicianName',
        'consent_name', 'consentName', 'previous_dentist', 'previousDentist'
    ]);
    const contacts = new Set([
        'phone_number', 'mobile', 'phone', 'guardian_contact', 'guardianContact',
        'office_contact', 'officeContact', 'physician_contact', 'physicianContact',
        'gcash_account_number', 'contact_phone'
    ]);
    const namePattern = String.raw` *\p{L}[\p{L}\p{M}]*(?: +\p{L}[\p{L}\p{M}]*)* *`;
    const fieldName = field => field.name || field.dataset.field;

    function configure(field) {
        if (field instanceof HTMLTextAreaElement && fieldName(field) === 'contact_phone') {
            const valid = field.value.split(/\r?\n/).every(line => !line.trim() || /^[0-9]{11}$/.test(line.trim()));
            field.setCustomValidity(valid ? '' : 'Enter one 11-digit clinic phone number per line.');
            return;
        }
        if (!(field instanceof HTMLInputElement)) return;
        const name = fieldName(field);
        if (names.has(name)) {
            field.maxLength = 100;
            field.pattern = namePattern;
            field.title = 'Use letters and spaces only.';
        } else if (contacts.has(name)) {
            field.type = 'tel';
            field.inputMode = 'numeric';
            field.maxLength = 11;
            field.pattern = '[0-9]{11}';
            field.title = 'Enter exactly 11 digits.';
        }
    }

    function clean(value, name) {
        if (name === 'contact_phone') {
            return value.split(/\r?\n/).map(line => line.replace(/[^0-9]/g, '').slice(0, 11)).join('\n');
        }
        return names.has(name) ? value.replace(/[^\p{L}\p{M} ]/gu, '')
            : value.replace(/[^0-9]/g, '').slice(0, 11);
    }

    function constrain(event) {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)
            || (!names.has(fieldName(field)) && !contacts.has(fieldName(field)))) return;
        if (field.readOnly || field.matches(':disabled')) return;
        const name = fieldName(field);
        configure(field);
        // Do not interrupt an IME while it is composing an accented name.
        if (event.isComposing) return;
        const original = field.value;
        const sanitized = clean(original, name);
        if (sanitized === original) return;
        const position = field.selectionStart;
        field.value = sanitized;
        if (position !== null) {
            const offset = clean(original.slice(0, position), name).length;
            field.setSelectionRange(offset, offset);
        }
        configure(field);
    }

    function configureTree(root) {
        configure(root);
        root.querySelectorAll?.('input,textarea').forEach(configure);
    }

    // Capture before form-specific handlers calculate readiness or dirty state.
    document.addEventListener('paste', event => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)
            || (!names.has(fieldName(field)) && !contacts.has(fieldName(field)))
            || !event.clipboardData) return;
        if (field.readOnly || field.matches(':disabled')) return;
        const name = fieldName(field);
        const start = field.selectionStart ?? field.value.length;
        const end = field.selectionEnd ?? start;
        const inserted = clean(event.clipboardData.getData('text/plain'), name);
        const merged = field.value.slice(0, start) + inserted + field.value.slice(end);
        event.preventDefault();
        // Sanitize before maxlength can truncate the raw clipboard text.
        field.value = clean(merged, name).slice(0, names.has(name) ? 100 : name === 'contact_phone' ? undefined : 11);
        const position = Math.min(start + inserted.length, field.value.length);
        field.setSelectionRange(position, position);
        configure(field);
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }, true);
    document.addEventListener('input', constrain, true);
    document.addEventListener('change', constrain, true);
    document.addEventListener('compositionend', constrain, true);
    document.addEventListener('focusin', event => configure(event.target), true);
    configureTree(document);
    // Dashboard modules and edit dialogs are inserted after initial page load.
    new MutationObserver(records => {
        for (const record of records) {
            record.addedNodes.forEach(configureTree);
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
