(function () {
    'use strict';

    let pendingResolution = null;
    let releaseRestoredFocus = null;
    let actionBackdrop = null;

    function resetStacking(modal) {
        modal.classList.remove('vd-stacked-action-modal');
        modal.style.removeProperty('--vd-action-modal-z');
        if (actionBackdrop) {
            // Bootstrap detaches but reuses this node on the next opening.
            actionBackdrop.classList.remove('vd-stacked-action-backdrop');
            actionBackdrop.style.removeProperty('--vd-action-backdrop-z');
        }
    }

    function restoreParentFocus(parent, trigger) {
        if (!parent.isConnected || !parent.classList.contains('show')) return;
        const focusableElements = () => Array.from(parent.querySelectorAll(
            'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )).filter(element => !element.disabled && !element.closest('[inert]') && element.getClientRects().length);
        const focusInside = () => {
            const target = trigger?.isConnected && parent.contains(trigger) && !trigger.disabled
                ? trigger : (focusableElements()[0] || parent);
            target.focus({ preventScroll: true });
        };
        const onFocus = event => {
            if (!parent.contains(event.target)) focusInside();
        };
        const onKeydown = event => {
            if (event.key !== 'Tab') return;
            const elements = focusableElements();
            const first = elements[0] || parent;
            const last = elements[elements.length - 1] || parent;
            if ((event.shiftKey && document.activeElement === first)
                || (!event.shiftKey && document.activeElement === last)
                || document.activeElement === parent) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus({ preventScroll: true });
            }
        };
        const release = () => {
            document.removeEventListener('focusin', onFocus);
            document.removeEventListener('keydown', onKeydown);
            parent.removeEventListener('hide.bs.modal', release);
            if (releaseRestoredFocus === release) releaseRestoredFocus = null;
        };
        document.addEventListener('focusin', onFocus);
        document.addEventListener('keydown', onKeydown);
        parent.addEventListener('hide.bs.modal', release, { once: true });
        releaseRestoredFocus = release;
        focusInside();
    }

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

            let input;
            if (Array.isArray(field.options)) {
                input = document.createElement('select');
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = field.placeholder || 'Select an option';
                input.appendChild(placeholder);
                field.options.forEach((option) => {
                    const element = document.createElement('option');
                    element.value = option.value;
                    element.textContent = option.label;
                    input.appendChild(element);
                });
            } else {
                input = field.multiline ? document.createElement('textarea') : document.createElement('input');
            }
            input.id = id;
            input.name = field.name || `field_${index}`;
            input.className = 'form-control vd-input';
            input.dataset.actionField = 'true';
            input.placeholder = field.placeholder || '';
            input.value = field.value || '';
            input.required = Boolean(field.required);
            if (!field.multiline && input.tagName === 'INPUT') input.type = field.type || 'text';
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
        if (releaseRestoredFocus) releaseRestoredFocus();
        resetStacking(elements.modal);

        const trigger = document.activeElement;
        const parents = Array.from(document.querySelectorAll('.modal.show')).filter(modal => modal !== elements.modal);
        const parentState = parents.map(modal => ({
            modal, inert: modal.inert, ariaModal: modal.getAttribute('aria-modal'),
            ariaHidden: modal.getAttribute('aria-hidden')
        }));
        const parent = parents[parents.length - 1];
        const backdropsBefore = new Set(document.querySelectorAll('.modal-backdrop'));
        let result = { confirmed: false, values: {} };
        if (parent) {
            const topZ = Math.max(...parents.map(modal => Number.parseInt(getComputedStyle(modal).zIndex, 10) || 1055));
            elements.modal.classList.add('vd-stacked-action-modal');
            elements.modal.style.setProperty('--vd-action-modal-z', String(topZ + 20));
        }

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
            result = { confirmed: true, values };
            elements.instance.hide();
        };

        elements.confirm.addEventListener('click', confirmHandler);
        elements.modal.addEventListener('hidden.bs.modal', () => {
            elements.confirm.removeEventListener('click', confirmHandler);
            parentState.forEach(state => {
                state.modal.inert = state.inert;
                if (!state.modal.classList.contains('show')) return;
                for (const [attribute, value] of [['aria-modal', state.ariaModal], ['aria-hidden', state.ariaHidden]]) {
                    if (value === null) state.modal.removeAttribute(attribute);
                    else state.modal.setAttribute(attribute, value);
                }
            });
            resetStacking(elements.modal);
            if (parents.some(modal => modal.isConnected && modal.classList.contains('show'))) {
                document.body.classList.add('modal-open');
                restoreParentFocus(parent, trigger);
            } else if (trigger?.isConnected && typeof trigger.focus === 'function') {
                trigger.focus({ preventScroll: true });
            }
            finish(result);
        }, { once: true });

        return new Promise((resolve) => {
            pendingResolution = resolve;
            elements.modal.addEventListener('shown.bs.modal', () => {
                parentState.forEach(state => {
                    state.modal.inert = true;
                    state.modal.removeAttribute('aria-modal');
                    state.modal.setAttribute('aria-hidden', 'true');
                });
                const firstInput = elements.fields.querySelector('[data-action-field]');
                (firstInput || elements.confirm).focus();
            }, { once: true });
            elements.instance.show();
            actionBackdrop = Array.from(document.querySelectorAll('.modal-backdrop')).find(element => !backdropsBefore.has(element)) || null;
            if (parent && actionBackdrop) {
                actionBackdrop.classList.add('vd-stacked-action-backdrop');
                actionBackdrop.style.setProperty('--vd-action-backdrop-z', String(Number(elements.modal.style.getPropertyValue('--vd-action-modal-z')) - 10));
            }
        });
    };
})();
