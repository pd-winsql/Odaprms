(function () {
    'use strict';

    let pendingResolution = null;

    function getElements() {
        const modal = document.getElementById('staffActionModal');
        if (!modal || typeof bootstrap === 'undefined') return null;
        return {
            modal,
            instance: bootstrap.Modal.getOrCreateInstance(modal),
            title: document.getElementById('staffActionModalTitle'),
            kicker: document.getElementById('staffActionModalKicker'),
            message: document.getElementById('staffActionModalMessage'),
            icon: document.getElementById('staffActionModalIcon'),
            details: document.getElementById('staffActionModalDetails'),
            fields: document.getElementById('staffActionModalFields'),
            error: document.getElementById('staffActionModalError'),
            cancel: document.getElementById('staffActionModalCancel'),
            confirm: document.getElementById('staffActionModalConfirm')
        };
    }

    function finish(result) {
        if (!pendingResolution) return;
        const resolve = pendingResolution;
        pendingResolution = null;
        resolve(result);
    }

    function renderDetails(container, details) {
        container.replaceChildren();
        if (!Array.isArray(details) || !details.length) {
            container.classList.add('d-none');
            return;
        }

        details.forEach((detail) => {
            const row = document.createElement('div');
            row.className = 'vd-action-modal-detail';
            const label = document.createElement('span');
            label.className = 'vd-action-modal-detail-label';
            label.textContent = detail.label || '';
            const value = document.createElement('span');
            value.className = 'vd-action-modal-detail-value';
            value.textContent = detail.value || '—';
            row.append(label, value);
            container.appendChild(row);
        });
        container.classList.remove('d-none');
    }

    function renderFields(container, fields) {
        container.replaceChildren();
        if (!Array.isArray(fields) || !fields.length) {
            container.classList.add('d-none');
            return;
        }

        fields.forEach((field, index) => {
            const group = document.createElement('div');
            group.className = 'vd-action-modal-field';
            const id = `staffActionField-${field.name || index}`;
            const label = document.createElement('label');
            label.className = 'vd-label form-label';
            label.htmlFor = id;
            label.textContent = field.label || 'Details';

            const input = field.multiline ? document.createElement('textarea') : document.createElement('input');
            input.id = id;
            input.name = field.name || `field_${index}`;
            input.className = 'form-control vd-input';
            input.dataset.actionField = 'true';
            input.placeholder = field.placeholder || '';
            input.value = field.value || '';
            input.required = Boolean(field.required);
            if (!field.multiline) input.type = field.type || 'text';
            if (field.multiline) input.rows = field.rows || 3;
            if (field.minlength) input.minLength = field.minlength;
            if (field.maxlength) input.maxLength = field.maxlength;

            group.append(label, input);
            if (field.help) {
                const help = document.createElement('div');
                help.className = 'vd-action-modal-help';
                help.textContent = field.help;
                group.appendChild(help);
            }
            container.appendChild(group);
        });
        container.classList.remove('d-none');
    }

    window.showActionModal = function (options = {}) {
        const elements = getElements();
        if (!elements) return Promise.resolve({ confirmed: false, values: {} });
        if (pendingResolution) finish({ confirmed: false, values: {} });

        const tone = ['info', 'warning', 'danger', 'success'].includes(options.tone) ? options.tone : 'info';
        elements.title.textContent = options.title || 'Confirm Action';
        elements.kicker.textContent = options.kicker || 'Please confirm';
        elements.message.textContent = options.message || 'Would you like to continue?';
        elements.confirm.textContent = options.confirmText || 'Confirm';
        elements.cancel.textContent = options.cancelText || 'Cancel';
        elements.cancel.classList.toggle('d-none', options.cancelText === false);
        elements.icon.className = `vd-action-modal-icon vd-action-modal-icon-${tone}`;
        const icon = document.createElement('i');
        icon.className = `ti ${options.icon || 'ti-help'}`;
        elements.icon.replaceChildren(icon);
        elements.error.textContent = '';
        elements.error.classList.add('d-none');
        renderDetails(elements.details, options.details);
        renderFields(elements.fields, options.fields);

        const confirmHandler = () => {
            const values = {};
            const inputs = Array.from(elements.fields.querySelectorAll('[data-action-field]'));
            for (const input of inputs) {
                const value = input.value.trim();
                if (input.required && !value) {
                    elements.error.textContent = `Please enter ${input.previousElementSibling?.textContent?.toLowerCase() || 'the required information'}.`;
                    elements.error.classList.remove('d-none');
                    input.focus();
                    return;
                }
                if (input.minLength > 0 && value.length < input.minLength) {
                    elements.error.textContent = `${input.previousElementSibling?.textContent || 'This field'} must contain at least ${input.minLength} characters.`;
                    elements.error.classList.remove('d-none');
                    input.focus();
                    return;
                }
                if (!input.checkValidity()) {
                    elements.error.textContent = input.type === 'email'
                        ? 'Please enter a valid email address.'
                        : (input.validationMessage || 'Please enter a valid value.');
                    elements.error.classList.remove('d-none');
                    input.focus();
                    return;
                }
                values[input.name] = value;
            }
            elements.confirm.removeEventListener('click', confirmHandler);
            elements.instance.hide();
            finish({ confirmed: true, values });
        };

        elements.confirm.addEventListener('click', confirmHandler);
        elements.modal.addEventListener('hidden.bs.modal', () => {
            elements.confirm.removeEventListener('click', confirmHandler);
            finish({ confirmed: false, values: {} });
        }, { once: true });

        return new Promise((resolve) => {
            pendingResolution = resolve;
            elements.instance.show();
            elements.modal.addEventListener('shown.bs.modal', () => {
                const firstInput = elements.fields.querySelector('[data-action-field]');
                (firstInput || elements.confirm).focus();
            }, { once: true });
        });
    };
})();
