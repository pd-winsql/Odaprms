(function () {
    'use strict';

    const buttonState = new WeakMap();

    function setButtonLoading(button, isLoading, label = 'Loading…') {
        if (!button) return;

        if (isLoading) {
            if (buttonState.has(button)) return;

            buttonState.set(button, {
                html: button.innerHTML,
                disabled: button.disabled,
                width: button.style.width
            });

            const width = button.getBoundingClientRect().width;
            if (width) button.style.width = `${Math.ceil(width)}px`;

            const content = document.createElement('span');
            content.className = 'vd-button-loading__content';

            const spinner = document.createElement('span');
            spinner.className = 'vd-spinner';
            spinner.setAttribute('aria-hidden', 'true');

            const text = document.createElement('span');
            text.textContent = label;

            content.append(spinner, text);
            button.replaceChildren(content);
            button.disabled = true;
            button.classList.add('vd-button-loading');
            button.setAttribute('aria-busy', 'true');
            return;
        }

        const state = buttonState.get(button);
        if (!state) return;

        button.innerHTML = state.html;
        button.disabled = state.disabled;
        button.style.width = state.width;
        button.classList.remove('vd-button-loading');
        button.removeAttribute('aria-busy');
        buttonState.delete(button);
    }

    function contentMarkup(type, label) {
        if (type === 'spinner') {
            return `<div class="vd-content-loading__label"><span class="vd-spinner" aria-hidden="true"></span><span>${label}</span></div>`;
        }

        return '<div class="vd-skeleton" aria-hidden="true">' +
            '<div class="vd-skeleton__line vd-skeleton__line--short"></div>' +
            '<div class="vd-skeleton__block"></div>' +
            '<div class="vd-skeleton__line vd-skeleton__line--medium"></div>' +
            '<div class="vd-skeleton__block"></div>' +
            '</div><span class="visually-hidden">' + label + '</span>';
    }

    function showContentLoading(container, options = {}) {
        if (!container) return;
        const type = options.type || 'skeleton';
        const label = options.label || 'Loading content…';
        container.classList.add('vd-content-loading');
        container.setAttribute('aria-busy', 'true');
        container.setAttribute('aria-live', 'polite');
        container.innerHTML = contentMarkup(type, label);
    }

    function finishContentLoading(container) {
        if (!container) return;
        container.classList.remove('vd-content-loading');
        container.removeAttribute('aria-busy');
    }

    window.LoadingUI = {
        setButton: setButtonLoading,
        showContent: showContentLoading,
        finishContent: finishContentLoading
    };
})();
