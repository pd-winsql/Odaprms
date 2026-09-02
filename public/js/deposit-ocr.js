(function () {
    const initializedForms = new WeakSet();

    function setStatus(form, state, message) {
        const status = form.querySelector('[data-ocr-status]');
        if (!status) return;
        status.dataset.state = state;
        const messageNode = status.querySelector('[data-ocr-message]');
        if (messageNode) messageNode.textContent = message;
    }

    function showPreview(form, file) {
        const preview = form.querySelector('[data-receipt-preview]');
        const empty = form.querySelector('[data-receipt-empty]');
        const filename = form.querySelector('[data-receipt-filename]');
        if (!preview || !empty) return;

        if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
        const objectUrl = URL.createObjectURL(file);
        preview.dataset.objectUrl = objectUrl;
        preview.src = objectUrl;
        preview.classList.remove('d-none');
        empty.classList.add('d-none');
        if (filename) filename.textContent = file.name;
    }

    function resetFields(form) {
        form.querySelectorAll('[data-ocr-field]').forEach(field => {
            field.value = '';
            field.classList.remove('is-ocr-filled');
        });
    }

    function fillFields(form, fields) {
        const values = {
            receipt_amount: fields.amount,
            gcash_reference: fields.reference_number,
            gcash_transaction_at: fields.transaction_at,
        };

        Object.entries(values).forEach(([name, value]) => {
            const field = form.elements.namedItem(name);
            if (!field || !value) return;
            field.value = value;
            field.classList.add('is-ocr-filled');
        });
    }

    async function scanReceipt(form, file) {
        const endpoint = form.dataset.ocrEndpoint;
        const submit = form.querySelector('button[type="submit"]');
        const payload = new FormData();
        payload.append('action', 'extract');
        ['csrf_token', 'appointment_id', 'payment_token'].forEach(name => {
            const input = form.elements.namedItem(name);
            if (input?.value) payload.append(name, input.value);
        });
        payload.append('receipt', file);

        setStatus(form, 'scanning', 'Reading amount, reference number, and transaction date…');
        if (submit) submit.disabled = true;

        try {
            const response = await fetch(endpoint, { method: 'POST', body: payload });
            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'The receipt could not be scanned.');

            fillFields(form, result.fields || {});
            const expected = Number(form.dataset.requiredAmount || 0);
            const detected = Number(result.fields?.amount || 0);
            const mismatch = expected > 0 && detected > 0 && Math.abs(expected - detected) > 0.009;

            if (mismatch) {
                setStatus(form, 'manual', `Receipt scanned, but the amount does not match the required ₱${expected.toFixed(2)} deposit.`);
            } else if ((result.missing || []).length) {
                setStatus(form, 'manual', result.message);
            } else {
                setStatus(form, 'ready', result.message);
            }
        } catch (error) {
            setStatus(form, 'manual', error.message || 'Automatic reading failed. Enter the receipt details manually.');
        } finally {
            if (submit) submit.disabled = false;
        }
    }

    function init(form) {
        if (!form || initializedForms.has(form)) return;
        initializedForms.add(form);

        const input = form.querySelector('input[type="file"][name="receipt"]');
        if (!input || !form.dataset.ocrEndpoint) return;

        form.querySelectorAll('[data-ocr-field]').forEach(field => {
            field.addEventListener('input', () => field.classList.remove('is-ocr-filled'));
        });

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            resetFields(form);
            if (!file) {
                setStatus(form, 'idle', 'Choose a receipt screenshot to fill the details automatically.');
                return;
            }
            if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                input.value = '';
                setStatus(form, 'manual', 'Choose a JPG or PNG image no larger than 5 MB.');
                return;
            }

            showPreview(form, file);
            scanReceipt(form, file);
        });
    }

    window.DepositOcr = {
        init,
        initAll(root = document) {
            root.querySelectorAll('[data-deposit-ocr-form]').forEach(init);
        },
    };

    document.addEventListener('DOMContentLoaded', () => window.DepositOcr.initAll());
})();
